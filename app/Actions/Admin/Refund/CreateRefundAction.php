<?php

declare(strict_types=1);

namespace App\Actions\Admin\Refund;

use App\Data\Admin\Refund\RefundCreateData;
use App\Enums\Order\OrderItemStatusEnum;
use App\Enums\Order\RefundStatusEnum;
use App\Enums\Payment\PaymentMethodEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Events\RefundCompletedEvent;
use App\Exceptions\RefundValidationException;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Refund;
use App\Services\OrderStatusService;
use App\Services\Payment\Digipay\DigipayException;
use App\Services\Payment\Refund\RefundProcessorFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use SmartCache\Facades\SmartCache;
use Throwable;

final class CreateRefundAction
{
    public function __construct(
        private readonly OrderStatusService $orderStatusService,
        private readonly RefundProcessorFactory $processorFactory,
        private readonly UpdateOrderRefundedAmountAction $updateOrderRefundedAmount,
    ) {}

    public function handle(RefundCreateData $data, OrderItem $orderItem): Refund
    {
        $lockKey = "refund_order_item_{$orderItem->id}";

        return SmartCache::lock($lockKey, 15)->block(5, function () use ($data, $orderItem) {

            $orderItem->refresh()->loadMissing('order.payments', 'enrollment');
            $order = $orderItem->order;

            $this->validateOrderItemIsRefundable($orderItem, $data);

            $amountPaidForItem = $this->calculateAmountPaidForItem($orderItem);
            $deductionAmount   = $this->calculateDeductionAmount($data, $orderItem->price);
            $refundAmount      = max(0, $amountPaidForItem - $deductionAmount);

            $payment       = $this->resolvePayment($orderItem);
            $paymentMethod = $payment?->method?->value ?? PaymentMethodEnum::BANK_TRANSFER->value;
            $processor     = $this->processorFactory->make($paymentMethod);

            $isImmediateCompletion = $data->status                                                     === RefundStatusEnum::COMPLETED->value;
            $requiresGateway       = $isImmediateCompletion && ! $data->skip_gateway && $paymentMethod === PaymentMethodEnum::DIGIPAY->value;

            $refund = DB::transaction(function () use ($order, $orderItem, $payment, $refundAmount, $deductionAmount, $data) {
                return Refund::create([
                    'order_id'         => $order->id,
                    'order_item_id'    => $orderItem->id,
                    'payment_id'       => $payment?->id,
                    'customer_id'      => $order->customer_id,
                    'amount'           => $refundAmount,
                    'deduction_amount' => $deductionAmount,
                    // FORCE status to processing while we talk to gateway
                    'status'              => RefundStatusEnum::PROCESSING,
                    'transaction_details' => $data->transaction_details->toArray(),
                    'refunded_at'         => null, // Not refunded yet
                    'admin_notes'         => $data->admin_notes,
                ]);
            });

            $gatewayTrackingCode = null;
            try {
                if ($requiresGateway) {
                    $gatewayTrackingCode = $processor->process(
                        new Refund(['payment_id' => $payment?->id]),
                        $order,
                        $refundAmount,
                    );
                }
            } catch (DigipayException $e) {
                $refund->update(['status' => RefundStatusEnum::FAILED, 'admin_notes' => ($refund->admin_notes ?? '').PHP_EOL.$e->getUserMessage()]);
                throw new RefundValidationException($e->getUserMessage());
            } catch (Throwable $e) {
                // API Failed! Mark our refund as failed so we don't try again blindly.
                $refund->update(['status' => RefundStatusEnum::FAILED]);
                throw $e;
            }

            try {
                return DB::transaction(function () use (
                    $refund, $orderItem, $order, $refundAmount, $paymentMethod,
                    $processor, $gatewayTrackingCode, $isImmediateCompletion, $data
                ) {

                    // Update the Refund record
                    $transactionDetails = array_merge(
                        $refund->transaction_details ?? [],
                        $gatewayTrackingCode ? ['gateway_tracking_code' => $gatewayTrackingCode] : []
                    );

                    $adminNotes = $refund->admin_notes;
                    if ($data->skip_gateway && $isImmediateCompletion) {
                        $adminNotes = mb_trim(($adminNotes ?? '').PHP_EOL.__('messages.order.refund.gateway_skipped_by_admin_at', ['date' => verta()->formatDatetime()]));
                    }

                    $refund->update([
                        'status'              => $isImmediateCompletion ? RefundStatusEnum::COMPLETED : RefundStatusEnum::from($data->status),
                        'transaction_details' => $transactionDetails,
                        'refunded_at'         => $isImmediateCompletion ? now() : null,
                        'admin_notes'         => $adminNotes,
                    ]);

                    if ($isImmediateCompletion) {
                        // Process Wallet/Offline methods
                        if (! $data->skip_gateway && $paymentMethod === PaymentMethodEnum::WALLET->value) {
                            $processor->process($refund, $order, $refundAmount);
                        }
                        if (in_array($paymentMethod, [PaymentMethodEnum::BANK_TRANSFER->value, PaymentMethodEnum::MELLAT_GATEWAY->value], true)) {
                            $processor->process($refund, $order, $refundAmount);
                        }

                        // Update Order Item
                        $orderItem->total_refunded = $refundAmount;
                        $orderItem->qty_refunded   = $orderItem->qty_ordered;
                        $orderItem->status         = OrderItemStatusEnum::REFUNDED;
                        $orderItem->saveQuietly();

                        // Update parent statuses
                        $this->orderStatusService->updateEnrollmentStatus($orderItem);
                        $this->orderStatusService->updateParentOrderStatus($order->fresh());
                        $this->updateOrderRefundedAmount->handle($order->fresh());

                        RefundCompletedEvent::dispatch($refund);
                    }

                    return $refund;
                });

            } catch (Throwable $e) {
                // CRITICAL FAILURE: Gateway succeeded, but DB failed to update to COMPLETED.
                Log::emergency('Partial Failure: Refund API succeeded but DB update failed. Record is stuck in PROCESSING state.', [
                    'refund_id'     => $refund->id,
                    'tracking_code' => $gatewayTrackingCode,
                    'error'         => $e->getMessage(),
                ]);

                // Optionally dispatch a retry job here, or just let your Reconciliation command handle it.
                throw $e;
            }
        });
    }

    private function resolvePayment(OrderItem $orderItem): ?Payment
    {
        return $orderItem->order->payments()
            ->where('status', PaymentStatusEnum::COMPLETED)
            ->oldest()
            ->first();
    }

    /**
     * Calculates how much has actually been paid towards a specific item.
     * This is the definitive logic for our "Single Ledger" model.
     */
    private function calculateAmountPaidForItem(OrderItem $orderItem): int
    {
        $order = $orderItem->order;

        if ($order->balance_due <= 0) {
            return (int) (($orderItem->price - $orderItem->discount_amount + $orderItem->tax_amount)
                * $orderItem->qty_ordered);
        }

        return $orderItem->total;
    }

    /**
     * Calculates the final deduction amount based on either a fixed amount or a percentage of the original price.
     */
    private function calculateDeductionAmount(RefundCreateData $data, int $originalPrice): int
    {
        if ($data->deduction_amount !== null && $data->deduction_percent !== null) {
            $dedcutionAmount = (int) floor(($originalPrice * $data->deduction_percent) / 100);
            if ($dedcutionAmount !== $data->deduction_amount) {
                throw ValidationException::withMessages([
                    'deduction_amount' => __('messages.order.refund.deduction_conflict'),
                ]);
            }

            return $data->deduction_amount;
        }
        if ($data->deduction_amount !== null) {
            return $data->deduction_amount;
        }

        if ($data->deduction_percent !== null) {
            return (int) floor(($originalPrice * $data->deduction_percent) / 100);
        }

        return 0;
    }

    /**
     * Ensures the item is in a state where it can be refunded.
     */
    private function validateOrderItemIsRefundable(OrderItem $orderItem, RefundCreateData $data): void
    {
        $order = $orderItem->order;

        if ($order->total_paid <= 0) {
            throw ValidationException::withMessages([
                'order_item_id' => __('messages.order.refund.no_completed_payments'),
            ]);
        }

        if ($orderItem->status === OrderItemStatusEnum::REFUNDED) {
            throw ValidationException::withMessages([
                'order_item_id' => __('messages.order.refund.already_refunded'),
            ]);
        }

        if ($orderItem->status === OrderItemStatusEnum::CANCELLED) {
            throw ValidationException::withMessages([
                'order_item_id' => __('messages.order.refund.not_allowed'),
            ]);
        }

        if ($orderItem->refunds()->whereNot('status', RefundStatusEnum::FAILED)->exists()) {
            throw ValidationException::withMessages([
                'order_item_id' => __('messages.order.refund.refund_request_exists'),
            ]);
        }

        // ——— Digipay partial refund gate ———
        $paymentMethod = $order->payments()
            ->where('status', PaymentStatusEnum::COMPLETED)
            ->oldest()->value('method');

        if ($paymentMethod === PaymentMethodEnum::DIGIPAY->value && ! config('payments.digipay.allow_partial_refund')) {
            throw new RefundValidationException(__('messages.order.refund.digipay_partial_refund_not_supported'));
        }
    }
}
