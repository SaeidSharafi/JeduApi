<?php

declare(strict_types=1);

namespace App\Actions\Payment;

use App\Enums\Payment\PaymentStatusEnum;
use App\Enums\Payment\PaymentTransactionStatusEnum;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

final readonly class FailPaymentAttemptAction
{
    /**
     * Fail a PENDING payment attempt and close its open gateway transactions.
     *
     * The payment lock serializes this with VerifyPaymentAction (which also
     * locks the payment first), so a payment completed by a concurrent gateway
     * callback is left untouched instead of being overwritten.
     *
     * @return bool Whether the payment was still pending and got failed.
     */
    public function handle(int $paymentId, string $errorCode, string $errorMessage): bool
    {
        return DB::transaction(function () use ($paymentId, $errorCode, $errorMessage): bool {
            $payment = Payment::query()->whereKey($paymentId)->lockForUpdate()->first();

            if ($payment === null || $payment->status !== PaymentStatusEnum::PENDING) {
                return false;
            }

            $payment->transactions()
                ->where('status', PaymentTransactionStatusEnum::INITIATED)
                ->whereNull('completed_at')
                ->update([
                    'status'        => PaymentTransactionStatusEnum::FAILED,
                    'error_code'    => $errorCode,
                    'error_message' => $errorMessage,
                    'completed_at'  => now(),
                ]);

            $payment->update(['status' => PaymentStatusEnum::FAILED]);

            return true;
        });
    }
}
