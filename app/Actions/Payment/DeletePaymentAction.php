<?php

declare(strict_types=1);

namespace App\Actions\Payment;

use App\Models\Order;
use App\Models\Payment;

final class DeletePaymentAction
{
    /**
     * Updates an existing Payment record for an Order.
     */
    public function handle(Order $order, Payment $payment): void
    {
        $payment->delete();
    }
}
