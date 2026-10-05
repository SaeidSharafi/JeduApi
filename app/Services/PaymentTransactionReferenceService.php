<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Payment\PaymentTransactionStatusEnum;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Random\Randomizer;

final readonly class PaymentTransactionReferenceService
{
    public function __construct(private Randomizer $randomizer = new Randomizer()) {}

    /**
     * Generate a secure random numeric string within Mellat's signed long range.
     */
    public function generate(): string
    {
        return (string) $this->randomizer->getInt(1, PHP_INT_MAX);
    }

    /**
     * Persist the attempt before gateway contact; retry local reference collisions.
     */
    public function generateFor(Payment $payment): PaymentTransaction
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                return DB::transaction(function () use ($payment): PaymentTransaction {
                    return $payment->transactions()->create([
                        'transaction_reference' => $this->generate(),
                        'attempt_number'        => $payment->transactions()->count() + 1,
                        'status'                => PaymentTransactionStatusEnum::INITIATED,
                        'initiated_at'          => now(),
                        'ip_address'            => request()->ip(),
                        'user_agent'            => request()->userAgent(),
                    ]);
                });
            } catch (UniqueConstraintViolationException $exception) {
                if ($attempt >= 3) {
                    throw $exception;
                }
            }
        }
    }
}
