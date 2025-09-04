<?php

declare(strict_types=1);

namespace App\Contracts\Payment;

use App\Data\Admin\Payment\PaymentCreateData;
use App\Enums\Payment\PaymentMethodEnum;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Contracts\Auth\Authenticatable;

interface PaymentProcessorContract
{
    /**
     * Process the payment for the given order.
     */
    public function process(
        Order $order,
        PaymentCreateData $paymentData,
        Authenticatable $adminUser,
        int $amountToPay
    ): Payment;

    /**
     * Check if this processor can handle the given payment method.
     */
    public function canHandle(PaymentMethodEnum $paymentMethod): bool;
}
