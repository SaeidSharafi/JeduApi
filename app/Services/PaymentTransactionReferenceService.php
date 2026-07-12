<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Payment\PaymentTransactionStatusEnum;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use Illuminate\Support\Facades\DB;

final readonly class PaymentTransactionReferenceService
{
    /**
     * Generate the next unique transaction reference for a payment transaction.
     *
     * This method uses the payment_transactions table directly with a transaction lock
     * to ensure uniqueness even under concurrent requests.
     *
     * Transaction references are numeric-only sequential IDs starting from 200000001
     * to differentiate from order increment_ids (which start at 100000001).
     */
    public function generate(): string
    {
        return DB::transaction(function (): string {
            // Lock the last transaction row to prevent race conditions
            $lastTransaction = PaymentTransaction::query()
                ->select('id', 'transaction_reference')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            // Calculate the next sequential number
            $nextNumber = $lastTransaction
                ? (int) $lastTransaction->transaction_reference + 1
                : config('payments.transaction_reference.start_from');

            return (string) $nextNumber;
        });
    }

    /**
     * Reserve the next unique transaction reference AND persist the
     * PaymentTransaction row in the same locked database transaction.
     *
     * Splitting "compute the next number" from "insert the row" (the old
     * generate(): string design) is unsafe: the row-lock is released the
     * moment the number is computed, but the row using that number isn't
     * written until after a gateway network round-trip. Two concurrent
     * calls can both read the same "last number" before either has
     * written its row, handing out duplicate references. Doing both
     * inside the same lockForUpdate() transaction closes that gap.
     */
    public function generateFor(Payment $payment): PaymentTransaction
    {
        return DB::transaction(function () use ($payment): PaymentTransaction {
            $lastTransaction = PaymentTransaction::query()
                ->select('id', 'transaction_reference')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            $nextNumber = $lastTransaction
                ? (int) $lastTransaction->transaction_reference + 1
                : config('payments.transaction_reference.start_from');

            $attemptNumber = $payment->transactions()->count() + 1;

            return $payment->transactions()->create([
                'transaction_reference' => (string) $nextNumber,
                'attempt_number'        => $attemptNumber,
                'status'                => PaymentTransactionStatusEnum::INITIATED,
                'initiated_at'          => now(),
                'ip_address'            => request()->ip(),
                'user_agent'            => request()->userAgent(),
            ]);
        });
    }
}
