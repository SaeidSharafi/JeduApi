<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Payment\FailPaymentAttemptAction;
use App\Enums\Payment\PaymentStatusEnum;
use App\Enums\Payment\PaymentTransactionErrorCodeEnum;
use App\Enums\Payment\PaymentTransactionStatusEnum;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

final class ExpirePendingPaymentsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payments:expire-pending
                            {--threshold=30 : Minutes after which a pending payment attempt is expired}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Expire pending payment attempts that have not completed after the threshold';

    public function __construct(
        private FailPaymentAttemptAction $failPaymentAttempt,
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * This is the timeout owner for every PENDING payment attempt:
     * - wallet top-ups (order_id null) have no order for the cancellation
     *   actions to close, so this is their only lifecycle;
     * - order payments are closed synchronously by the cancellation/terminal
     *   order paths; this command is their safety net when that cascade is
     *   missed (process crash, queue-free same-request gap, manual status edits).
     *
     * A payment is eligible when it is PENDING and has an INITIATED transaction
     * older than the threshold with no newer in-flight attempt. Its stale
     * transactions are closed with ORDER_CLOSED when the order is already
     * terminal, otherwise with STUCK_TIMEOUT.
     */
    public function handle(): int
    {
        $thresholdMinutes = (int) $this->option('threshold');
        $thresholdTime    = now()->subMinutes($thresholdMinutes);

        $this->info("Checking for pending payment attempts older than {$thresholdMinutes} minutes...");

        // Find payments that are PENDING with stale initiated transactions
        $pendingPayments = Payment::query()
            ->with(['order', 'transactions' => function ($query): void {
                $query->latest('initiated_at');
            }])
            ->where('status', PaymentStatusEnum::PENDING)
            ->whereHas('transactions', function ($query) use ($thresholdTime): void {
                $query->where('initiated_at', '<=', $thresholdTime)
                    ->whereNull('completed_at')
                    ->where('status', PaymentTransactionStatusEnum::INITIATED);
            })
            // A newer in-flight attempt means the payment is still active; only
            // expire it once every initiated transaction is past the threshold.
            ->whereDoesntHave('transactions', function ($query) use ($thresholdTime): void {
                $query->where('initiated_at', '>', $thresholdTime)
                    ->whereNull('completed_at')
                    ->where('status', PaymentTransactionStatusEnum::INITIATED);
            })
            ->get();

        if ($pendingPayments->isEmpty()) {
            $this->info('No pending payment attempts to expire.');

            return self::SUCCESS;
        }

        $this->warn("Found {$pendingPayments->count()} pending payment attempt(s):");

        foreach ($pendingPayments as $payment) {
            $stuckTransactions = $payment->transactions->filter(
                fn (PaymentTransaction $transaction): bool => $transaction->status === PaymentTransactionStatusEnum::INITIATED
                    && $transaction->completed_at                                  === null
                    && $transaction->initiated_at !== null
                    && $transaction->initiated_at->lessThanOrEqualTo($thresholdTime)
            );

            $latestTransaction = $stuckTransactions->first();

            $orderClosed = $payment->order?->status?->closesPaymentAttempts() ?? false;

            $logData = [
                'payment_id'            => $payment->id,
                'order_id'              => $payment->order_id,
                'order_increment_id'    => $payment->order?->increment_id,
                'transaction_reference' => $latestTransaction?->transaction_reference,
                'initiated_at'          => $latestTransaction?->initiated_at?->toDateTimeString(),
                'minutes_pending'       => $latestTransaction?->initiated_at?->diffInMinutes(now()),
            ];

            $expired = $this->failPaymentAttempt->handle(
                paymentId: $payment->id,
                errorCode: $orderClosed
                    ? PaymentTransactionErrorCodeEnum::ORDER_CLOSED->value
                    : PaymentTransactionErrorCodeEnum::STUCK_TIMEOUT->value,
                errorMessage: $orderClosed
                    ? 'The order was closed before the payment attempt resolved.'
                    : 'Gateway transaction was left initiated past the payment attempt threshold.',
            );

            if (! $expired) {
                continue;
            }

            Log::warning('Pending payment attempt expired', $logData);

            $this->table(
                ['Field', 'Value'],
                collect($logData)->map(fn ($value, $key): array => [$key, $value])->values()->all()
            );
        }

        return self::SUCCESS;
    }
}
