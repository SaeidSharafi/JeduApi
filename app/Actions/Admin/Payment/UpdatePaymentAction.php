<?php

declare(strict_types=1);

namespace App\Actions\Admin\Payment;

use App\Data\Admin\Payment\PaymentUpdateData;
use App\Enums\Payment\PaymentStatusEnum;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Validation\ValidationException;

final class UpdatePaymentAction
{
    /**
     * Updates an existing Payment record for an Order.
     */
    public function handle(Order $order, Payment $payment, PaymentUpdateData $paymentData): Payment
    {
        if ($payment->status === PaymentStatusEnum::COMPLETED) {
            throw ValidationException::withMessages(
                ['status' => __('messages.order.payment.update_completed_payment_status_error')]
            );
        }

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
