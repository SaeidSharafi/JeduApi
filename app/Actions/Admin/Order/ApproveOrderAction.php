<?php

declare(strict_types=1);

namespace App\Actions\Admin\Order;

use App\Enums\Order\OrderItemPaymentTypeEnum;
use App\Enums\Order\OrderItemStatusEnum;
use App\Enums\Order\OrderStatusEnum;
use App\Exceptions\Gateway\DigipayException;
use App\Models\Order;
use App\Models\Payment;
use App\Services\OrderStatusService;
use App\Services\Payment\Digipay\DigipayAdminService;
use App\Services\ProductReservationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use SmartCache\Facades\SmartCache;

final readonly class ApproveOrderAction
{
    public function __construct(
        private OrderStatusService $orderStatusService,
        private DigipayAdminService $digipayService,
        private ProductReservationService $productReservationService,
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
        $lockKey = "approve_order_{$order->id}";

        return SmartCache::lock($lockKey, 15)->block(5, function () use ($order): Order {
            $order = Order::query()->whereKey($order->id)->with(['items', 'payments'])->firstOrFail();
            $this->validateOrderEligibility($order);

            // External call first to avoid holding DB lock during long HTTP operations.
            $deliveryPayment = $this->resolveDeliveryPayment($order);
            if ($deliveryPayment) {
                $this->confirmDigipayDelivery($order, $deliveryPayment);
            }

            return DB::transaction(function () use ($order): Order {
                $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
                $order->loadMissing('items', 'payments');
                $this->validateOrderEligibility($order);

                // Mark parent order completed
                $order->status = OrderStatusEnum::COMPLETED;
                $order->save();

                // Manually mark each item as completed (manual approval provisioning)
                foreach ($order->items as $item) {
                    if ($item->status !== OrderItemStatusEnum::COMPLETED) {
                        // Payment coverage confirmed → reserved seat becomes occupied.
                        $this->productReservationService->consume($item->product_delivery_option_id, $item->qty_ordered);
                        $item->status = OrderItemStatusEnum::COMPLETED;
                        $item->saveQuietly();
                    }
                    $this->orderStatusService->updateEnrollmentStatus($item);
                }

                // Recalculate parent order status from items to keep single source of truth
                $this->orderStatusService->updateParentOrderStatus($order->fresh());

                return $order->fresh();
            });
        });
    }

    private function resolveDeliveryPayment(Order $order): ?Payment
    {
        $payment = $order->payments()->where('method', 'digipay')->latest()->first();

        if (! $payment) {
            return null;
        }

        if (! $this->digipayService->requiresDeliveryConfirmation($payment)) {
            return null;
        }

        return $payment;
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
    private function confirmDigipayDelivery(Order $order, Payment $payment): void
    {
        try {
            $this->digipayService->deliver($payment);

            Log::channel(config('payments.digipay.logging.channel', 'stack'))->info('[Digipay] Delivery confirmed on order approval', [
                'order_id'   => $order->id,
                'payment_id' => $payment->id,
            ]);
        } catch (DigipayException $e) {
            // THROW to rollback the DB::transaction
            throw ValidationException::withMessages([
                'order' => __('messages.digipay.delivery_error', ['message' => $e->getUserMessage()]),
            ]);
        }
    }
}
