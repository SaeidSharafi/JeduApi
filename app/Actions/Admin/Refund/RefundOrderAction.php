<?php

declare(strict_types=1);

namespace App\Actions\Admin\Refund;

use App\Data\Admin\Refund\RefundOrderData;
use App\Enums\Order\OrderItemStatusEnum;
use App\Enums\Order\RefundStatusEnum;
use App\Enums\Payment\PaymentMethodEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Events\RefundCompletedEvent;
use App\Exceptions\RefundGatewayException;
use App\Exceptions\RefundValidationException;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use App\Services\OrderStatusService;
use App\Services\Payment\Digipay\DigipayException;
use App\Services\Payment\Refund\RefundProcessorFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use SmartCache\Facades\SmartCache;
use Throwable;

final class RefundOrderAction
{
    public function __construct(
        private readonly OrderStatusService $orderStatusService,
        private readonly RefundProcessorFactory $processorFactory,
        private readonly UpdateOrderRefundedAmountAction $updateOrderRefundedAmount,
    ) {}

    public function handle(Order $order, RefundOrderData $data): Collection
    {
        $lockKey = "refund_order_{$order->id}";

        return SmartCache::lock($lockKey, 20)->block(5, function () use ($order, $data) {

            $order->refresh()->loadMissing('items', 'payments');

            // 1. Resolve & Validate
            $refundableItems = $this->getRefundableItems($order);
            $payment         = $this->resolvePayment($order);
            $paymentMethod   = $payment?->method?->value ?? PaymentMethodEnum::BANK_TRANSFER->value;
            $processor       = $this->processorFactory->make($paymentMethod);
            $requiresGateway = $paymentMethod === PaymentMethodEnum::DIGIPAY->value && ! $data->skip_gateway;

            // 2. Calculate Amounts
            $itemAmounts = collect();
            foreach ($refundableItems as $item) {
                $amountPaid = $this->calculateAmountPaidForItem($item);
                $deduction  = $this->calculateItemDeduction($data, $item->price);

                $itemAmounts->push([
                    'item'          => $item,
                    'paid'          => $amountPaid,
                    'deduction'     => $deduction,
                    'refund_amount' => max(0, $amountPaid - $deduction),
                ]);
            }

            $totalRefundAmount = $itemAmounts->sum('refund_amount');

            // Pre-flight validation for Gateway
            if ($requiresGateway) {
                $this->validateGatewayLimits($payment, $totalRefundAmount);
            }

            $processingRefunds = DB::transaction(function () use ($order, $itemAmounts, $payment, $data) {
                $refunds = new Collection();
                foreach ($itemAmounts as $itemData) {
                    $refunds->push(Refund::create([
                        'order_id'            => $order->id,
                        'order_item_id'       => $itemData['item']->id,
                        'payment_id'          => $payment?->id,
                        'customer_id'         => $order->customer_id,
                        'amount'              => $itemData['refund_amount'],
                        'deduction_amount'    => $itemData['deduction'],
                        'status'              => RefundStatusEnum::PROCESSING,
                        'transaction_details' => [
                            'receiver_name'       => $data->receiver_name,
                            'card_number'         => $data->card_number,
                            'iban'                => $data->iban,
                        ],
                        'refunded_at'         => null,
                        'admin_notes'         => $data->admin_notes,
                    ]));
                }
                return $refunds;
            });

            $gatewayTrackingCode = null;
            try {
                if ($requiresGateway) {
                    $gatewayTrackingCode = $processor->process(
                        new Refund(['payment_id' => $payment->id]),
                        $order,
                        $totalRefundAmount,
                    );
                }
            } catch (RefundGatewayException $e) {
                $processingRefunds->each(fn($r) => $r->update([
                    'status' => RefundStatusEnum::FAILED,
                    'admin_notes' => ($r->admin_notes ?? '') . PHP_EOL . $e->getMessage()
                ]));
                throw new RefundValidationException($e->getMessage());
            } catch (Throwable $e) {
                $processingRefunds->each(fn($r) => $r->update(['status' => RefundStatusEnum::FAILED]));
                throw $e;
            }

            try {
                return DB::transaction(function () use (
                    $order, $data, $itemAmounts, $processingRefunds, $paymentMethod,
                    $gatewayTrackingCode, $processor,
                ) {
                    foreach ($itemAmounts as $itemData) {
                        $item         = $itemData['item'];
                        $refundAmount = $itemData['refund_amount'];
                        $refund       = $processingRefunds->firstWhere('order_item_id', $item->id);

                        $adminNotes = $refund->admin_notes;
                        if ($data->skip_gateway) {
                            $adminNotes = mb_trim(($adminNotes ?? '')."\n[Gateway skipped by Admin at ".now().']');
                        }

                        $refund->update([
                            'status'              => RefundStatusEnum::COMPLETED,
                            'transaction_details' => $gatewayTrackingCode ? ['gateway_tracking_code' => $gatewayTrackingCode] : [],
                            'refunded_at'         => now(),
                            'admin_notes'         => $adminNotes,
                        ]);

                        $item->update([
                            'status'         => OrderItemStatusEnum::REFUNDED,
                            'total_refunded' => $refundAmount,
                            'qty_refunded'   => $item->qty_ordered,
                        ]);

                        if ($paymentMethod === PaymentMethodEnum::WALLET->value && ! $data->skip_gateway) {
                            $processor->process($refund, $order, $refundAmount);
                        }

                        $this->orderStatusService->updateEnrollmentStatus($item);
                    }

                    $this->orderStatusService->updateParentOrderStatus($order->fresh());
                    $this->updateOrderRefundedAmount->handle($order->fresh());

                    $processingRefunds->each(fn (Refund $r) => RefundCompletedEvent::dispatch($r));

                    return $processingRefunds->load('order');
                });

            } catch (Throwable $e) {
                Log::emergency('Partial Failure (Bulk Refund): Digipay API succeeded but DB update failed.', [
                    'order_id'      => $order->id,
                    'refund_ids'    => $processingRefunds->pluck('id')->toArray(),
                    'tracking_code' => $gatewayTrackingCode,
                    'error'         => $e->getMessage()
                ]);
                throw $e;
            }
        });
    }

    private function getRefundableItems(Order $order): Collection
    {
        $refundableItems = $order->items()
            ->whereNotIn('status', [OrderItemStatusEnum::REFUNDED, OrderItemStatusEnum::CANCELLED])
            ->whereDoesntHave('refunds', fn ($q) => $q->where('status', '!=', RefundStatusEnum::FAILED))
            ->get();

        if ($refundableItems->isEmpty()) {
            throw new RefundValidationException(__('messages.order.refund.no_refundable_items'));
        }

        return $refundableItems;
    }

    private function resolvePayment(Order $order): ?Payment
    {
        return $order->payments()
            ->where('status', PaymentStatusEnum::COMPLETED)
            ->oldest()
            ->first();
    }

    private function validateGatewayLimits(Payment $payment, int $totalRefundAmount): void
    {
        $alreadyRefunded = Refund::where('payment_id', $payment->id)
            ->where('status', RefundStatusEnum::COMPLETED)
            ->sum('amount');

        if (($alreadyRefunded + $totalRefundAmount) > $payment->amount) {
            throw new RefundGatewayException('Total refund exceeds original payment amount.');
        }
    }

    private function calculateAmountPaidForItem($orderItem): int
    {
        $order = $orderItem->order;

        if ($order->balance_due <= 0) {
            return (int) (($orderItem->price - $orderItem->discount_amount + $orderItem->tax_amount)
                * $orderItem->qty_ordered);
        }

        return $orderItem->total;
    }

    private function calculateItemDeduction(RefundOrderData $data, int $originalPrice): int
    {
        // Notice: This now matches the logic from CreateRefundAction (percentage vs absolute)
        if ($data->deduction_amount !== null && $data->deduction_percent !== null) {
            $dedcutionAmount = (int) floor(($originalPrice * $data->deduction_percent) / 100);
            if ($dedcutionAmount !== $data->deduction_amount) {
                throw new RefundValidationException(__('messages.order.refund.deduction_conflict'));
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
}
