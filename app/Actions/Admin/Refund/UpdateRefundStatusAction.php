<?php

declare(strict_types=1);

namespace App\Actions\Admin\Refund;

use App\Data\Admin\Refund\RefundStatusUpdateData;
use App\Enums\Order\OrderItemStatusEnum;
use App\Enums\Order\RefundStatusEnum;
use App\Events\RefundCompletedEvent;
use App\Models\Refund;
use App\Services\OrderStatusService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class UpdateRefundStatusAction
{
    public function __construct(
        private OrderStatusService $orderStatusService
    ) {}

    public function handle(Refund $refund, RefundStatusUpdateData $data): Refund
    {
        // 1. Validate that the requested transition is allowed.
        $this->validateStatusTransition($refund->status, $data->status);

        return DB::transaction(function () use ($refund, $data) {
            $newStatus = RefundStatusEnum::from($data->status);

            // 2. Perform the specific actions for the new status.
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

    /**
     * This is the state machine. It enforces the rules we defined.
     */
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
            // Terminal states have no allowed transitions.
            RefundStatusEnum::COMPLETED->value => [],
            RefundStatusEnum::FAILED->value    => [],
            RefundStatusEnum::CANCELLED->value => [],
        ];

        if (! in_array($to, $allowedTransitions[$from->value])) {
            $to = RefundStatusEnum::tryFrom($to);
            throw ValidationException::withMessages([
                'status' => __('messages.order.refund.invalid_status_transition', ['from' => $from->translate(), 'to' => $to?->translate()]),
            ]);
        }
    }

    /**
     * Handles the complex logic for when a refund is marked 'COMPLETED'.
     */
    private function handleCompletion(Refund $refund, RefundStatusUpdateData $data): void
    {
        $orderItem = $refund->orderItem;

        $refund->status      = RefundStatusEnum::COMPLETED;
        $refund->refunded_at = now();
        $refund->admin_notes = $data->admin_notes ?? $refund->admin_notes;

        if ($data->tracking_code) {
            $details                     = $refund->transaction_details;
            $details['tracking_code']    = $data->tracking_code;
            $refund->transaction_details = $details;
        }
        $refund->save();

        $orderItem->total_refunded = $refund->amount;
        $orderItem->status         = OrderItemStatusEnum::REFUNDED;
        $orderItem->saveQuietly();

        $this->orderStatusService->updateEnrollmentStatus($orderItem);
        $this->orderStatusService->updateParentOrderStatus($orderItem->order);

        RefundCompletedEvent::dispatch($refund);
    }

    /**
     * Handles simple status updates that don't have major side effects.
     */
    private function handleSimpleStatusUpdate(Refund $refund, RefundStatusEnum $newStatus, ?string $notes): void
    {
        $refund->status = $newStatus;
        if ($notes) {
            $refund->admin_notes = $notes;
        }
        $refund->save();
    }
}
