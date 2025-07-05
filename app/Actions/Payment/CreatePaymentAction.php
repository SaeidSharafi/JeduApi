<?php

namespace App\Actions\Payment;

use App\Data\Payment\PaymentCreateData;
use App\Enums\OrderItemPaymentTypeEnum;
use App\Models\Order;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreatePaymentAction
{
    /**
     * Creates a new Payment record for an Order.
     * The amount is NOT taken from user input; it is calculated based on the
     * payment_type ('full_payment' or 'pre_payment') stored on the order items.
     */
    public function handle(Order $order, PaymentCreateData $paymentData, Staff $adminUser): void
    {
        if ($order->payments()->where('status', 'pending')->exists()) {
            throw ValidationException::withMessages([
                'payment' => 'This order already has a pending payment. Please complete or cancel it first.',
            ]);
        }

        // Calculate the required payment amount based on the stored order items
        $amountToPay = $this->calculateRequiredPayment($order);
        // Ensure we don't try to pay more than the outstanding balance.
        // We use the model's accessor here, which is ALWAYS up-to-date.
        $balanceDue = $order->balance_due;

        if ($amountToPay > $balanceDue) {
            // This can happen if a partial payment was already manually applied.
            // We should only charge the remaining balance.
            $amountToPay = $balanceDue;
        }

        if ($amountToPay <= 0) {
            return;
        }

        DB::transaction(function () use ($order, $paymentData, $adminUser, $amountToPay) {
            $order->payments()->create([
                'customer_id' => $order->customer_id,
                'staff_id'    => $adminUser->id,
                'amount'      => $amountToPay,
                'method'      => $paymentData->method,
                'status'      => $paymentData->status,
                'admin_notes' => $paymentData->admin_notes,
            ]);
        });
    }

    /**
     * Calculates the intended initial payment based on the choices made during order creation.
     */
    private function calculateRequiredPayment(Order $order): int
    {
        $amount = 0;
        foreach ($order->items as $item) {
            // Note: We use the `price` from the order_item snapshot, not the live product price.
            $itemTotalAfterDiscounts = ($item->price - $item->discount_amount + $item->tax_amount) * $item->qty_ordered;
            $itemTotalAfterDiscounts = max(0, $itemTotalAfterDiscounts); // Prevent negative totals

            if ($item->payment_type === OrderItemPaymentTypeEnum::PRE_PAYMENT) {
                $amount += ($item->prepayment_amount ?? 0) * $item->qty_ordered;
            } else {
                $amount += $itemTotalAfterDiscounts;
            }
        }
        return $amount;
    }
}
