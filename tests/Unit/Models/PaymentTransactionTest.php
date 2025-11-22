<?php

declare(strict_types=1);

use App\Enums\Payment\PaymentMethodEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Enums\Payment\PaymentTransactionStatusEnum;
use App\Models\Payment;
use App\Models\PaymentTransaction;

it('has a payment relationship', function (): void {
    $payment = Payment::factory()->create([
        'method' => PaymentMethodEnum::WALLET->value,
        'status' => PaymentStatusEnum::PENDING->value,
        'amount' => 100000,
    ]);

    $transaction = PaymentTransaction::create([
        'payment_id'            => $payment->id,
        'transaction_reference' => 'TXN-TEST-123',
        'attempt_number'        => 1,
        'status'                => PaymentTransactionStatusEnum::INITIATED->value,
        'gateway_request'       => ['test' => 'data'],
        'gateway_response'      => [],
        'initiated_at'          => now(),
    ]);

    expect($transaction->payment)->toBeInstanceOf(Payment::class)
        ->and($transaction->payment->id)->toBe($payment->id);
});
