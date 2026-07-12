<?php

declare(strict_types=1);

namespace Tests\Support\Fakes\Payment;

use App\Contracts\Payment\PaymentProcessorContract;
use App\Data\Admin\Payment\PaymentProcessResultData;
use App\Enums\Payment\PaymentMethodEnum;
use App\Models\Payment;
use BadMethodCallException;

final class MockUnsupportedProcessor implements PaymentProcessorContract
{
    public function canHandle(PaymentMethodEnum $paymentMethod): bool
    {
        return false; // Never handles any method
    }

    public function process(Payment $payment): PaymentProcessResultData
    {
        return PaymentProcessResultData::completed($payment);
    }

    public function requiresRedirect(): bool
    {
        return false;
    }

    public function verify(Payment $payment, array $callbackData): Payment
    {
        throw new BadMethodCallException('MockUnsupportedProcessor does not support verification.');
    }
}
