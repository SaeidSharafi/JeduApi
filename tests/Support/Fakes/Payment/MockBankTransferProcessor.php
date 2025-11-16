<?php

declare(strict_types=1);

namespace Tests\Support\Fakes\Payment;

use App\Contracts\Payment\PaymentProcessorContract;
use App\Data\Admin\Payment\PaymentCreateData;
use App\Data\Admin\Payment\PaymentProcessResultData;
use App\Enums\Payment\PaymentMethodEnum;
use App\Models\Order;
use App\Models\Payment;
use BadMethodCallException;
use Illuminate\Contracts\Auth\Authenticatable;

final class MockBankTransferProcessor implements PaymentProcessorContract
{
    public function canHandle(PaymentMethodEnum $paymentMethod): bool
    {
        return $paymentMethod === PaymentMethodEnum::BANK_TRANSFER;
    }

    public function process(Order $order, PaymentCreateData $paymentData, Authenticatable $adminUser, int $amountToPay): PaymentProcessResultData
    {
        return PaymentProcessResultData::completed(new Payment());
    }

    public function requiresRedirect(): bool
    {
        return false;
    }

    public function verify(Payment $payment, array $callbackData): Payment
    {
        throw new BadMethodCallException('MockBankTransferProcessor does not support verification.');
    }
}

