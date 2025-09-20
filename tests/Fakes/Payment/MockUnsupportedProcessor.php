<?php

namespace Tests\Fakes\Payment;

use App\Contracts\Payment\PaymentProcessorContract;
use App\Data\Admin\Payment\PaymentCreateData;
use App\Enums\Payment\PaymentMethodEnum;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Contracts\Auth\Authenticatable;

final class MockUnsupportedProcessor implements PaymentProcessorContract
{
    public function canHandle(PaymentMethodEnum $paymentMethod): bool
    {
        return false; // Never handles any method
    }

    public function process(Order $order, PaymentCreateData $paymentData, Authenticatable $adminUser, int $amountToPay): Payment
    {
        return new Payment();
    }
}
