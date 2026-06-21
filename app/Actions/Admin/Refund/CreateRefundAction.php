<?php

declare(strict_types=1);

namespace App\Actions\Admin\Refund;

use App\Data\Admin\Refund\RefundCreateData;
use App\Enums\Order\OrderItemStatusEnum;
use App\Enums\Order\RefundStatusEnum;
use App\Events\RefundCompletedEvent;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Refund;
use App\Services\OrderStatusService;
use App\Services\Payment\Digipay\DigipayAdminService;
use App\Services\Payment\Digipay\DigipayException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final readonly class CreateRefundAction
{
    public function __construct(
        private OrderStatusService $orderStatusService,
        private DigipayAdminService $digipayService,
    ) {}

    public function handle(RefundCreateData $data, OrderItem $orderItem): Refund
    {
        $orderItem->loadMissing('order', 'enrollment');
        $this->validateOrderItemIsRefundable($orderItem);

        $amountPaidForItem = $this->calculateAmountPaidForItem($orderItem);
        // Deduction is calculated from the original price.
        $deductionAmount = $this->calculateDeductionAmount($data, $orderItem->price);

        $refundAmount = max(0, $amountPaidForItem - $deductionAmount);

        return DB::transaction(function () use ($data, $orderItem, $refundAmount, $deductionAmount) {
            // 1. Create the single refund record for this item.
            $refund = Refund::create([
                'order_id'            => $orderItem->order_id,
                'order_item_id'       => $orderItem->id,
                'customer_id'         => $orderItem->order->customer_id,
                'amount'              => $refundAmount,
                'deduction_amount'    => $deductionAmount,
                'status'              => $data->status,
                'transaction_details' => $data->transaction_details->toArray(),
                'refunded_at'         => $data->status === RefundStatusEnum::COMPLETED->value ? now() : null,
                'admin_notes'         => $data->admin_notes,
            ]);

            // 2. Update the OrderItem's status and refunded total.
            if ($data->status === RefundStatusEnum::COMPLETED->value) {
                $orderItem->total_refunded = $refundAmount;
                $orderItem->status         = OrderItemStatusEnum::REFUNDED;
                $orderItem->saveQuietly();
                $this->orderStatusService->updateEnrollmentStatus($orderItem);
                $this->orderStatusService->updateParentOrderStatus($orderItem->order);
                RefundCompletedEvent::dispatch($refund);
                $this->processDigipayRefund($orderItem->order, $refundAmount);
            }

            return $refund;
        });
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

        // If the order is not fully paid, the amount paid for any item is simply its 'total'
        // (which represents either the full value or the prepayment amount).
        return $orderItem->total;
    }

    /**
     * Calculates the final deduction amount based on either a fixed amount or a percentage of the original price.
     */
    private function calculateDeductionAmount(RefundCreateData $data, int $originalPrice): int
    {
        if ($data->deduction_amount !== null && $data->deduction_percent !== null) {
            $dedcutionAmount = (int) floor(($originalPrice * $data->deduction_percent) / 100);
            // If both are provided, we take the maximum of the two.
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
            // Perform percentage calculation carefully to avoid float issues.
            return (int) floor(($originalPrice * $data->deduction_percent) / 100);
        }

        return 0; // Should not be reached due to DTO validation, but as a fallback.
    }

    /**
     * Ensures the item is in a state where it can be refunded.
     *
     * @throws ValidationException
     */
    private function validateOrderItemIsRefundable(OrderItem $orderItem): void
    {
        if ($orderItem->order->total_paid <= 0) {
            throw ValidationException::withMessages([
                'order_item_id' => __('messages.order.refund.no_completed_payments'),
            ]);
        }
        // Rule 2: Can't refund an item that has already been refunded.
        // This is the key change. We check the status directly.
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
    }

    private function processDigipayRefund(Order $order, int $amount): void
    {
        $payment = $order->payments()->where('method', 'digipay')->latest()->first();

        if (! $payment) {
            return;
        }

        try {
            $response = $this->digipayService->refund($payment, $amount);

            Log::channel('digipay')->info('[Digipay] Refund successful', [
                'order_id'      => $order->id,
                'amount'        => $amount,
                'tracking_code' => $response->trackingCode,
            ]);
        } catch (DigipayException $e) {
            // NON-BLOCKING: refund record already created, admin retries via controller
            Log::channel('digipay')->error('[Digipay] Refund failed', [
                'order_id' => $order->id,
                'amount'   => $amount,
                'error'    => $e->getMessage(),
            ]);
        }
    }
}
