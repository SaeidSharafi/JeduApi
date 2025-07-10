<?php

namespace App\Actions\Payment;

use App\Data\Admin\Payment\PaymentUpdateData;
use App\Enums\PaymentStatusEnum;
use App\Models\Order;
use App\Models\Payment;

class UpdatePaymentAction
{
    /**
     * Updates an existing Payment record for an Order.
     */
    public function handle(Order $order, Payment $payment, PaymentUpdateData $paymentData): Payment
    {

        if ($paymentData->status) {
            $payment->status = PaymentStatusEnum::from($paymentData->status);
        }
        if ($paymentData->admin_notes) {
            $payment->admin_notes = $paymentData->admin_notes;
        }
        $payment->save();

        return $payment->fresh();
    }
}
