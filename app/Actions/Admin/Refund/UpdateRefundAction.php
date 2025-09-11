<?php

declare(strict_types=1);

namespace App\Actions\Admin\Refund;

use App\Data\Admin\Refund\RefundCreateData;
use App\Enums\Order\RefundStatusEnum;
use App\Models\OrderItem;
use App\Models\Refund;
use Illuminate\Validation\ValidationException;

final class UpdateRefundAction
{
    /**
     * Updates the details of a PENDING refund request.
     * This action is forbidden on refunds that are already processing or completed.
     */
    public function handle(Refund $refund, RefundCreateData $data): Refund
    {
        // CRITICAL: This action's power is safely limited to the PENDING state.
        if ($refund->status !== RefundStatusEnum::PENDING) {
            throw ValidationException::withMessages([
                'refund' => __('messages.order.refund.only_pending_refunds_can_be_edited'),
            ]);
        }

        $orderItem = $refund->orderItem;

        // Recalculate financial details based on potentially new deduction info.
        $amountPaidForItem = $this->calculateAmountPaidForItem($orderItem);
        $deductionAmount   = $this->calculateDeductionAmount($data, $orderItem->price);
        $refundAmount      = max(0, $amountPaidForItem - $deductionAmount);

        // Update the record with the new, corrected data.
        $refund->update([
            'amount'              => $refundAmount,
            'deduction_amount'    => $deductionAmount,
            'transaction_details' => $data->transaction_details->toArray(),
            'admin_notes'         => $data->admin_notes,
            // We do NOT update the status here. That's a separate action.
        ]);

        return $refund;
    }

    /**
     * Calculates how much has actually been paid towards a specific item.
     * This is the definitive logic for our "Single Ledger" model.
     */
    private function calculateAmountPaidForItem(OrderItem $orderItem): int
    {
        $order = $orderItem->order;

        if ($order->balance_due <= 0) {
            return (int) (($orderItem->price - $orderItem->discount_amount + $orderItem->tax_amount) * $orderItem->qty_ordered);
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
        if ($data->deduction_amount !== null) {
            return $data->deduction_amount;
        }

        if ($data->deduction_percent !== null) {
            // Perform percentage calculation carefully to avoid float issues.
            return (int) floor(($originalPrice * $data->deduction_percent) / 100);
        }

        return 0; // Should not be reached due to DTO validation, but as a fallback.
    }
}
