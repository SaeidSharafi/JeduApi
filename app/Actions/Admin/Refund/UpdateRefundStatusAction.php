<?php

declare(strict_types=1);

namespace App\Actions\Admin\Refund;

use App\Data\Admin\Refund\RefundStatusUpdateData;
use App\Enums\Order\OrderItemStatusEnum;
use App\Enums\Order\RefundStatusEnum;
use App\Enums\Payment\PaymentMethodEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Events\RefundCompletedEvent;
use App\Exceptions\RefundGatewayException;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Refund;
use App\Services\OrderStatusService;
use App\Services\Payment\Refund\RefundProcessorFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

final class UpdateRefundStatusAction
{
    public function __construct(
        private readonly OrderStatusService $orderStatusService,
        private readonly RefundProcessorFactory $processorFactory,
        private readonly UpdateOrderRefundedAmountAction $updateOrderRefundedAmount,
    ) {}

    public function handle(Refund $refund, RefundStatusUpdateData $data): Refund
    {
        $newStatus = RefundStatusEnum::from($data->status);

        if ($newStatus === RefundStatusEnum::COMPLETED) {
            return $this->handleCompletionSafely($refund, $data);
        }

        return DB::transaction(function () use ($refund, $data, $newStatus): Refund {
            $lockedRefund = Refund::query()->whereKey($refund->id)->lockForUpdate()->firstOrFail();
            $this->validateStatusTransition($lockedRefund->status, $data->status);
            $this->handleSimpleStatusUpdate($lockedRefund, $newStatus, $data->admin_notes);

            return $lockedRefund->fresh();
        });
    }

    private function handleCompletionSafely(Refund $refund, RefundStatusUpdateData $data): Refund
    {
        $state = DB::transaction(function () use ($refund, $data): object {
            $lockedRefund = Refund::query()->whereKey($refund->id)->lockForUpdate()->firstOrFail();
            $this->validateStatusTransition($lockedRefund->status, RefundStatusEnum::COMPLETED->value);

            $lockedRefund->loadMissing('orderItem.order.payments');
            /** @var OrderItem $orderItem */
            $orderItem = $lockedRefund->orderItem;
            /** @var Order $order */
            $order = $orderItem->order;

            $payment = $order->payments()
                ->where('status', PaymentStatusEnum::COMPLETED)
                ->oldest()
                ->first();

            $paymentMethod = $payment?->method->value ?? PaymentMethodEnum::BANK_TRANSFER->value;
            $processor     = $this->processorFactory->make($paymentMethod);

            return (object) [
                'refund'          => $lockedRefund,
                'orderItem'       => $orderItem,
                'order'           => $order,
                'paymentMethod'   => $paymentMethod,
                'processor'       => $processor,
                'requiresGateway' => $paymentMethod === PaymentMethodEnum::DIGIPAY->value && ! $data->skip_gateway,
            ];
        });

        $gatewayTrackingCode = null;
        if ($state->requiresGateway) {
            try {
                $gatewayTrackingCode = $state->processor->process($state->refund, $state->order, $state->refund->amount);
            } catch (Throwable $e) {
                DB::transaction(function () use ($refund, $e): void {
                    $lockedRefund = Refund::query()->whereKey($refund->id)->lockForUpdate()->firstOrFail();

                    if ($lockedRefund->status === RefundStatusEnum::COMPLETED) {
                        return;
                    }

                    $notes                     = mb_trim(($lockedRefund->admin_notes ?? '')."\n".$e->getMessage());
                    $lockedRefund->status      = RefundStatusEnum::FAILED;
                    $lockedRefund->admin_notes = $notes;
                    $lockedRefund->save();
                });

                if ($e instanceof RefundGatewayException) {
                    throw ValidationException::withMessages([
                        'status' => $e->getMessage(),
                    ]);
                }

                throw $e;
            }
        }

        return DB::transaction(function () use ($refund, $data, $gatewayTrackingCode): Refund {
            $lockedRefund = Refund::query()->whereKey($refund->id)->lockForUpdate()->firstOrFail();

            if ($lockedRefund->status === RefundStatusEnum::COMPLETED) {
                return $lockedRefund->fresh();
            }

            if ($lockedRefund->status === RefundStatusEnum::FAILED || $lockedRefund->status === RefundStatusEnum::CANCELLED) {
                throw ValidationException::withMessages([
                    'status' => __('messages.order.refund.invalid_status_transition', [
                        'from' => $lockedRefund->status->translate(),
                        'to'   => RefundStatusEnum::COMPLETED->translate(),
                    ]),
                ]);
            }

            $lockedRefund->loadMissing('orderItem.order.payments');
            /** @var OrderItem $orderItem */
            $orderItem = $lockedRefund->orderItem;
            /** @var Order $order */
            $order = $orderItem->order;

            $payment = $order->payments()
                ->where('status', PaymentStatusEnum::COMPLETED)
                ->oldest()
                ->first();

            $paymentMethod = $payment?->method->value ?? PaymentMethodEnum::BANK_TRANSFER->value;
            $processor     = $this->processorFactory->make($paymentMethod);

            $details = $lockedRefund->transaction_details ?? [];
            if ($data->tracking_code) {
                $details['tracking_code'] = $data->tracking_code;
            }
            if ($gatewayTrackingCode) {
                $details['gateway_tracking_code'] = $gatewayTrackingCode;
            }

            $adminNotes = $data->admin_notes ?? $lockedRefund->admin_notes;
            if ($data->skip_gateway) {
                $adminNotes = mb_trim(($adminNotes ?? '')."\n".__('messages.admin.gateway_skipped_note', ['datetime' => now()->toDateTimeString()]));
            }

            $lockedRefund->status              = RefundStatusEnum::COMPLETED;
            $lockedRefund->refunded_at         = now();
            $lockedRefund->admin_notes         = $adminNotes;
            $lockedRefund->transaction_details = $details;
            $lockedRefund->save();

            $orderItem->total_refunded = $lockedRefund->amount;
            $orderItem->qty_refunded   = $orderItem->qty_ordered;
            $orderItem->status         = OrderItemStatusEnum::REFUNDED;
            $orderItem->saveQuietly();

            if ($paymentMethod === PaymentMethodEnum::WALLET->value && ! $data->skip_gateway) {
                $processor->process($lockedRefund, $order, $lockedRefund->amount);
            }

            if (in_array($paymentMethod, [PaymentMethodEnum::BANK_TRANSFER->value, PaymentMethodEnum::MELLAT_GATEWAY->value], true)) {
                $processor->process($lockedRefund, $order, $lockedRefund->amount);
            }

            $this->orderStatusService->updateEnrollmentStatus($orderItem);
            $this->orderStatusService->updateParentOrderStatus($order);
            $this->updateOrderRefundedAmount->handle($order->fresh());

            RefundCompletedEvent::dispatch($lockedRefund);

            return $lockedRefund->fresh();
        });
    }

    private function validateStatusTransition(RefundStatusEnum $from, string $to): void
    {
        $allowedTransitions = [
            RefundStatusEnum::PENDING->value => [
                RefundStatusEnum::PROCESSING->value,
                RefundStatusEnum::COMPLETED->value,
                RefundStatusEnum::CANCELLED->value,
            ],
            RefundStatusEnum::PROCESSING->value => [
                RefundStatusEnum::COMPLETED->value,
                RefundStatusEnum::FAILED->value,
            ],
            RefundStatusEnum::COMPLETED->value => [],
            RefundStatusEnum::FAILED->value    => [],
            RefundStatusEnum::CANCELLED->value => [],
        ];

        if (! in_array($to, $allowedTransitions[$from->value], true)) {
            $to = RefundStatusEnum::tryFrom($to);
            throw ValidationException::withMessages([
                'status' => __('messages.order.refund.invalid_status_transition', [
                    'from' => $from->translate(),
                    'to'   => $to?->translate(),
                ]),
            ]);
        }
    }

    private function handleSimpleStatusUpdate(Refund $refund, RefundStatusEnum $newStatus, ?string $notes): void
    {
        $refund->status = $newStatus;
        if ($notes) {
            $refund->admin_notes = $notes;
        }
        $refund->save();
    }
}
