<?php

declare(strict_types=1);

namespace Tests\Support\Fakes\Payment;

use App\Contracts\Payment\PaymentProcessorContract;
use App\Data\Admin\Payment\PaymentProcessResultData;
use App\Enums\Payment\PaymentMethodEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Models\Payment;
use Illuminate\Support\Str;

final class MockMultiStepProcessor implements PaymentProcessorContract
{
    public function canHandle(PaymentMethodEnum $paymentMethod): bool
    {
        return $paymentMethod === PaymentMethodEnum::MELLAT_GATEWAY;
    }

    public function process(Payment $payment): PaymentProcessResultData
    {
        $fakeRefId = 'FAKE_REF_'.Str::random(10);

        $payment->update([
            'status' => PaymentStatusEnum::PENDING,
            'data'   => [
                'gateway'        => 'mellat_mock',
                'transaction_id' => $fakeRefId,
                'initiated_at'   => now()->toISOString(),
            ],
        ]);

        return PaymentProcessResultData::pendingWithRedirect(
            payment: $payment,
            redirectUrl: 'https://fake-mellat-gateway.test/payment',
            redirectData: [
                'RefId' => $fakeRefId,
            ],
            method: 'POST'
        );
    }

    public function requiresRedirect(): bool
    {
        return true;
    }

    public function verify(Payment $payment, array $callbackData): Payment
    {
        return $payment;
    }
}
