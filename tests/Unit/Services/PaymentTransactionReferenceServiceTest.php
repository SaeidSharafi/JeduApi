<?php

declare(strict_types=1);

use App\Enums\Payment\PaymentTransactionStatusEnum;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Services\PaymentTransactionReferenceService;
use Illuminate\Database\UniqueConstraintViolationException;
use Random\Engine;
use Random\Randomizer;

covers(PaymentTransactionReferenceService::class);

/** @param list<int> $values */
function paymentReferenceServiceWithValues(array $values): PaymentTransactionReferenceService
{
    return new PaymentTransactionReferenceService(new Randomizer(new class($values) implements Engine
    {
        /** @param list<int> $values */
        public function __construct(private array $values) {}

        public function generate(): string
        {
            return pack('P', array_shift($this->values) - 1);
        }
    }));
}

it('generates positive numeric strings within the signed long range', function (): void {
    $service = paymentReferenceServiceWithValues([1, PHP_INT_MAX]);

    expect($service->generate())->toBe('1');
    expect($service->generate())->toBe('9223372036854775807');
});

it('generates references independently of the latest stored reference', function (): void {
    PaymentTransaction::factory()->create(['transaction_reference' => '9223372036854775807']);
    $service = paymentReferenceServiceWithValues([750000000000000000]);

    expect($service->generate())->toBe('750000000000000000');
});

it('persists a fresh initiated transaction for each payment attempt', function (): void {
    $payment = Payment::factory()->create();
    $service = paymentReferenceServiceWithValues([750000000000000000, 850000000000000000]);

    $first  = $service->generateFor($payment);
    $second = $service->generateFor($payment);

    expect($first->fresh()->transaction_reference)->toBe('750000000000000000');
    expect($second->fresh()->transaction_reference)->toBe('850000000000000000');
    expect($first->status)->toBe(PaymentTransactionStatusEnum::INITIATED);
    expect($first->attempt_number)->toBe(1);
    expect($second->attempt_number)->toBe(2);
    expect($payment->transactions()->count())->toBe(2);
});

it('regenerates a reference when it collides with a persisted transaction', function (int $collisionCount): void {
    $payment = Payment::factory()->create();
    PaymentTransaction::factory()->create([
        'payment_id'            => $payment->id,
        'transaction_reference' => '750000000000000000',
    ]);
    $service = paymentReferenceServiceWithValues([
        ...array_fill(0, $collisionCount, 750000000000000000),
        850000000000000000,
    ]);

    $transaction = $service->generateFor($payment);

    expect($transaction->fresh()->transaction_reference)->toBe('850000000000000000');
    expect($transaction->attempt_number)->toBe(2);
    expect($payment->transactions()->count())->toBe(2);
})->with([1, 2]);

it('stops after three local reference collisions without persisting another attempt', function (): void {
    $payment = Payment::factory()->create();
    PaymentTransaction::factory()->create([
        'payment_id'            => $payment->id,
        'transaction_reference' => '750000000000000000',
    ]);
    $service = paymentReferenceServiceWithValues(array_fill(0, 3, 750000000000000000));

    expect(fn () => $service->generateFor($payment))->toThrow(UniqueConstraintViolationException::class);
    expect($payment->transactions()->count())->toBe(1);
});
