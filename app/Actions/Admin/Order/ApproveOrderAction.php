<?php

declare(strict_types=1);

namespace App\Actions\Admin\Order;

use App\Enums\Order\OrderItemPaymentTypeEnum;
use App\Enums\Order\OrderItemStatusEnum;
use App\Enums\Order\OrderStatusEnum;
use App\Models\Order;
use App\Services\OrderStatusService;
use App\Services\Payment\Digipay\DigipayAdminService;
use App\Services\Payment\Digipay\DigipayException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final readonly class ApproveOrderAction
{
    public function __construct(
        private OrderStatusService $orderStatusService,
        private DigipayAdminService $digipayService,
    ) {}

    /**
     * Approve an order for fulfillment and provisioning.
     *
     * This action validates that the order has sufficient payment coverage
     * (considering pre-payment options), then marks it as COMPLETED and
     * triggers enrollment provisioning.
     *
     * @param  Order  $order  The order to approve
     * @return Order The approved order
     *
     * @throws ValidationException If order cannot be approved
     */
    public function handle(Order $order): Order
    {
        return DB::transaction(function () use ($order): Order {
            $this->validateOrderEligibility($order);

            // Mark parent order completed
            $order->status = OrderStatusEnum::COMPLETED;
            $order->save();

            // Confirm Digipay delivery for CREDIT/BNPL payment types
            $this->confirmDigipayDelivery($order);

            // Manually mark each item as completed (manual approval provisioning)
            foreach ($order->items as $item) {
                if ($item->status !== OrderItemStatusEnum::COMPLETED) {
                    $item->status = OrderItemStatusEnum::COMPLETED;
                    $item->saveQuietly();
                }
                $this->orderStatusService->updateEnrollmentStatus($item);
            }

            // Recalculate parent order status from items to keep single source of truth
            $this->orderStatusService->updateParentOrderStatus($order->fresh());

            return $order->fresh();
        });
    }

    private function validateOrderEligibility(Order $order): void
    {
        if ($order->status === OrderStatusEnum::COMPLETED) {
            throw ValidationException::withMessages([
                'order' => __('validation.custom.order.already_completed'),
            ]);
        }

        if (in_array($order->status, [OrderStatusEnum::CANCELLED, OrderStatusEnum::REFUNDED], true)) {
            throw ValidationException::withMessages([
                'order' => __('validation.custom.order.cannot_approve_cancelled'),
            ]);
        }

        $requiredPayment = 0;
        foreach ($order->items as $item) {
            if ($item->payment_type === OrderItemPaymentTypeEnum::PRE_PAYMENT && $item->prepayment_amount > 0) {
                $requiredPayment += $item->prepayment_amount;
            } else {
                $requiredPayment += $item->total;
            }
        }

        if ($order->total_paid < $requiredPayment) {
            throw ValidationException::withMessages([
                'order' => __('validation.custom.order.insufficient_payment_for_approval', [
                    'required' => number_format($requiredPayment),
                    'paid'     => number_format($order->total_paid),
                ]),
            ]);
        }
    }

    /**
     * Confirm Digipay delivery for CREDIT/BNPL payments on order approval.
     *
     * Only payment gateway types 5 (CREDIT) and 13 (BNPL) require delivery
     * confirmation before Digipay releases funds. Skip silently for other types.
     *
     * @throws ValidationException On Digipay failure — rolls back the DB::transaction
     */
    private function confirmDigipayDelivery(Order $order): void
    {
        $payment = $order->payments()->where('method', 'digipay')->latest()->first();

        if (! $payment || ! $this->digipayService->requiresDeliveryConfirmation($payment)) {
            return;
        }

        try {
            $this->digipayService->deliver($payment);

            Log::channel(config('digipay.logging.channel', 'stack'))->info('[Digipay] Delivery confirmed on order approval', [
                'order_id'   => $order->id,
                'payment_id' => $payment->id,
            ]);
        } catch (DigipayException $e) {
            // THROW to rollback the DB::transaction
            throw ValidationException::withMessages([
                'order' => 'خطا در اعلام تحویل به دیجی‌پی: '.$e->getUserMessage(),
            ]);
        }
    }
}
