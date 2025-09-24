<?php

declare(strict_types=1);

namespace Tests\Fakes\Payment;

use App\Contracts\Payment\PaymentProcessorContract;
use App\Data\Admin\Payment\PaymentCreateData;
use App\Enums\Payment\PaymentMethodEnum;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Contracts\Auth\Authenticatable;

final class MockBankTransferProcessor implements PaymentProcessorContract
{
    public function canHandle(PaymentMethodEnum $paymentMethod): bool
    {
        return $paymentMethod === PaymentMethodEnum::BANK_TRANSFER;
    }

    public function process(Order $order, PaymentCreateData $paymentData, Authenticatable $adminUser, int $amountToPay): Payment
    {
        return new Payment();
    }
}
