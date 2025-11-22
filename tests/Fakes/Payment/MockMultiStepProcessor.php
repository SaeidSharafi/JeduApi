<?php

declare(strict_types=1);

namespace Tests\Fakes\Payment;

use App\Contracts\Payment\PaymentProcessorContract;
use App\Data\Admin\Payment\PaymentCreateData;
use App\Data\Admin\Payment\PaymentProcessResultData;
use App\Enums\Payment\PaymentMethodEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Models\Order;
use App\Models\Payment;
use BadMethodCallException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Str;

final class MockMultiStepProcessor implements PaymentProcessorContract
{
    public function canHandle(PaymentMethodEnum $paymentMethod): bool
    {
        return $paymentMethod === PaymentMethodEnum::MELLAT_GATEWAY;
    }

    public function process(Order $order, PaymentCreateData $paymentData, Authenticatable $adminUser, int $amountToPay): PaymentProcessResultData
    {
        $fakeRefId = 'FAKE_REF_' . Str::random(10);

        $payment = $order->payments()->create([
            'customer_id' => $order->customer_id,
            'created_by'  => $adminUser instanceof Staff ? $adminUser->id : null,
            'amount'      => $amountToPay,
            'method'      => PaymentMethodEnum::MELLAT_GATEWAY,
            'status'      => PaymentStatusEnum::PENDING, // Always PENDING for redirects
            'admin_notes' => $paymentData->admin_notes,
            'data'        => [
                'gateway'        => 'mellat_mock',
                'transaction_id' => $fakeRefId,
                'initiated_at'   => now()->toISOString(),
            ],
        ]);

        return PaymentProcessResultData::pendingWithRedirect(
            payment: $payment, // Use the newly created, real payment model
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

