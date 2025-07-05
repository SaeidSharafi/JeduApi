<?php

namespace App\Actions\Payment;

use App\Data\Payment\PaymentCreateData;
use App\Data\Payment\PaymentUpdateData;
use App\Enums\OrderItemPaymentTypeEnum;
use App\Enums\PaymentStatusEnum;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Staff;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
