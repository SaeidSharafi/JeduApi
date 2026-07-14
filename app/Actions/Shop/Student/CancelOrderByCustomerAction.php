<?php

declare(strict_types=1);

namespace App\Actions\Shop\Student;

use App\Enums\EnrollmentStatusEnum;
use App\Enums\Order\OrderStatusEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Events\OrderStatusUpdatedEvent;
use App\Models\Order;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CancelOrderByCustomerAction
{
    /**
     * Cancel an order by the customer.
     *
     * Business rules:
     * - Order must be in PENDING status
     * - Order must not have any COMPLETED payments
     * - Only the customer who owns the order can cancel it
     */
    public function execute(Order $order, int $userId): Order
    {
        // Verify the order belongs to the authenticated customer
        if ($order->customer_id !== $userId) {
            throw new ModelNotFoundException(__('messages.order.not_found'));
        }

        // Verify order is in PENDING status
        if ($order->status !== OrderStatusEnum::PENDING) {
            throw ValidationException::withMessages([
                __('messages.order.only_pending_orders_can_be_cancelled', ['status' => $order->status->translate()]),
            ]);
        }

        // Check if order has any completed payments
        $hasCompletedPayments = $order->payments()
            ->where('status', PaymentStatusEnum::COMPLETED)
            ->exists();

        if ($hasCompletedPayments) {
            throw ValidationException::withMessages([
                __('messages.order.cannot_cancel_order_with_completed_payments', ['order_id' => $order->id]),
            ]);
        }

        return DB::transaction(function () use ($order) {
            // Update order status to CANCELLED
            $order->status = OrderStatusEnum::CANCELLED;
            $order->save();

            // Cancel any enrollments (if they exist, though they shouldn't for unpaid orders)
            // Use each() instead of update() to fire model events for enrolled_count tracking
            $order->enrollments()
                ->where('enrollment_status', '!=', EnrollmentStatusEnum::CANCELLED)
                ->each(function ($enrollment): void {
                    $enrollment->enrollment_status = EnrollmentStatusEnum::CANCELLED;
                    $enrollment->save();
                });

            $order->load([
                'items',
                'payments',
                'enrollments',
            ]);

            OrderStatusUpdatedEvent::dispatch($order);

            return $order;
        });
    }
}
