<?php

declare(strict_types=1);

namespace App\Actions\Shop\Student;

use App\Actions\Order\CancelOrderAction;
use App\Actions\Payment\FailPendingOrderPaymentsAction;
use App\Enums\Order\OrderCancellationReasonEnum;
use App\Enums\Order\OrderStatusEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Models\Order;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class CancelOrderByCustomerAction
{
    public function __construct(
        private CancelOrderAction $cancelOrder,
        private FailPendingOrderPaymentsAction $failPendingOrderPayments,
    ) {}

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

        $cancelledOrder = DB::transaction(
            fn (): Order => $this->cancelOrder->handle($order, OrderCancellationReasonEnum::CUSTOMER)
        );

        // Close the order's open payment attempts in the same request, after the
        // order transaction commits: the order row is no longer locked, so this
        // cannot deadlock with a concurrent gateway verification.
        $this->failPendingOrderPayments->handle($cancelledOrder->id);

        return $cancelledOrder->load('payments');
    }
}
