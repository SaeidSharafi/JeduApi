<?php

declare(strict_types=1);

namespace App\Actions\Admin\Payment;

use App\Enums\Payment\PaymentStatusEnum;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Validation\ValidationException;

final class DeletePaymentAction
{
    /**
     * Updates an existing Payment record for an Order.
     */
    public function handle(Order $order, Payment $payment): void
    {
        if ($payment->status === PaymentStatusEnum::COMPLETED) {
            throw ValidationException::withMessages(
                ['payment' => __('messages.order.payment.delete_completed_payment_error')]
            );
        }
        $payment->delete();
    }
}
