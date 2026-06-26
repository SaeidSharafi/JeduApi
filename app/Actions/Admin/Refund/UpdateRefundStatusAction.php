<?php

declare(strict_types=1);

namespace App\Actions\Admin\Refund;

use App\Data\Admin\Refund\RefundStatusUpdateData;
use App\Enums\Order\OrderItemStatusEnum;
use App\Enums\Order\RefundStatusEnum;
use App\Enums\Payment\PaymentMethodEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Events\RefundCompletedEvent;
use App\Models\Refund;
use App\Services\OrderStatusService;
use App\Services\Payment\Refund\RefundProcessorFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdateRefundStatusAction
{
    public function __construct(
        private readonly OrderStatusService $orderStatusService,
        private readonly RefundProcessorFactory $processorFactory,
        private readonly UpdateOrderRefundedAmountAction $updateOrderRefundedAmount,
    ) {}

    public function handle(Refund $refund, RefundStatusUpdateData $data): Refund
    {
        $this->validateStatusTransition($refund->status, $data->status);

        $newStatus = RefundStatusEnum::from($data->status);

        return DB::transaction(function () use ($refund, $data, $newStatus) {
            match ($newStatus) {
                RefundStatusEnum::COMPLETED => $this->handleCompletion($refund, $data),
                RefundStatusEnum::FAILED,
                RefundStatusEnum::CANCELLED,
                RefundStatusEnum::PROCESSING => $this->handleSimpleStatusUpdate($refund, $newStatus,
                    $data->admin_notes),
            };

            return $refund->fresh();
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

    /**
     * Handles the complex logic for when a refund is marked 'COMPLETED'.
     * Gateway-first: call Digipay processor BEFORE DB writes inside this method.
     */
    private function handleCompletion(Refund $refund, RefundStatusUpdateData $data): void
    {
        $refund->loadMissing('orderItem.order.payments');
        $orderItem = $refund->orderItem;
        $order     = $orderItem->order;

        // Resolve payment method and processor
        $payment = $order->payments()
            ->where('status', PaymentStatusEnum::COMPLETED)
            ->oldest()
            ->first();

        $paymentMethod = $payment?->method?->value ?? PaymentMethodEnum::BANK_TRANSFER->value;
        $processor     = $this->processorFactory->make($paymentMethod);

        // ——— Gateway-first for Digipay (call BEFORE DB writes) ———
        $gatewayTrackingCode = null;
        if ($paymentMethod === PaymentMethodEnum::DIGIPAY->value && ! ($data->skip_gateway ?? false)) {
            $gatewayTrackingCode = $processor->process($refund, $order, $refund->amount);
        }

        // Update tracking code in transaction_details
        $details = $refund->transaction_details ?? [];
        if ($data->tracking_code) {
            $details['tracking_code'] = $data->tracking_code;
        }
        if ($gatewayTrackingCode) {
            $details['gateway_tracking_code'] = $gatewayTrackingCode;
        }

        $adminNotes = $data->admin_notes ?? $refund->admin_notes;
        if (($data->skip_gateway ?? false)) {
            $adminNotes = mb_trim(($adminNotes ?? '')."\n[Gateway skipped by Admin at ".now().']');
        }

        $refund->status              = RefundStatusEnum::COMPLETED;
        $refund->refunded_at         = now();
        $refund->admin_notes         = $adminNotes;
        $refund->transaction_details = $details;
        $refund->save();

        $orderItem->total_refunded = $refund->amount;
        $orderItem->qty_refunded   = $orderItem->qty_ordered;
        $orderItem->status         = OrderItemStatusEnum::REFUNDED;
        $orderItem->saveQuietly();

        // Wallet: credit inside the same transaction
        if ($paymentMethod === PaymentMethodEnum::WALLET->value && ! ($data->skip_gateway ?? false)) {
            $processor->process($refund, $order, $refund->amount);
        }

        // Manual: log-only
        if (in_array($paymentMethod, [PaymentMethodEnum::BANK_TRANSFER->value, PaymentMethodEnum::MELLAT_GATEWAY->value], true)) {
            $processor->process($refund, $order, $refund->amount);
        }

        $this->orderStatusService->updateEnrollmentStatus($orderItem);
        $this->orderStatusService->updateParentOrderStatus($order);
        $this->updateOrderRefundedAmount->handle($order->fresh());

        RefundCompletedEvent::dispatch($refund);
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
