<?php

namespace App\Actions\Payment;

use App\Models\Order;
use App\Models\Payment;

class DeletePaymentAction
{
    /**
     * Updates an existing Payment record for an Order.
     */
    public function handle(Order $order, Payment $payment): void
    {
        $payment->delete();
    }
}
