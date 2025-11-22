<?php

declare(strict_types=1);

namespace App\Services;

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
}
