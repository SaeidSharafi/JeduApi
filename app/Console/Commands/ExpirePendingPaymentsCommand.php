<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Payment\FailPaymentAttemptAction;
use App\Enums\Order\OrderStatusEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Enums\Payment\PaymentTransactionErrorCodeEnum;
use App\Enums\Payment\PaymentTransactionStatusEnum;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

final class ExpirePendingPaymentsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payments:expire-pending
                            {--threshold= : Minutes after which a pending payment attempt is expired (defaults to config order.abandonment_minutes)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Expire pending payment attempts that no pending order owns after the threshold';

    public function __construct(
        private FailPaymentAttemptAction $failPaymentAttempt,
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * Attempts on a PENDING order belong to orders:cancel-abandoned: once the
     * order is inactive it cancels the order and cascades the attempt as
     * ORDER_CLOSED, so this command never competes with it for the same row.
     * Everything else has no other owner and is expired here:
     * - wallet top-ups (order_id null) have no order to close them;
     * - a PROCESSING order can no longer start an attempt and has no canceller;
     * - a terminal order whose cancellation cascade was missed (crash, manual
     *   status edit) is the safety-net case.
     *
     * An attempt is stale when nothing on it is still in flight: it has no
     * initiated transaction newer than the threshold, and either an initiated
     * transaction older than it, or no initiated transaction at all and an old
     * own timestamp (the processor call died before the gateway was reached).
     * Terminal orders are closed with ORDER_CLOSED, every other attempt with
     * STUCK_TIMEOUT.
     */
    public function handle(): int
    {
        $thresholdMinutes = (int) ($this->option('threshold') ?? config('order.abandonment_minutes'));
        $thresholdTime    = now()->subMinutes($thresholdMinutes);

        $this->info("Checking for pending payment attempts older than {$thresholdMinutes} minutes...");

        $pendingPayments = $this->expirablePayments($thresholdTime);

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

            $errorCode = ($payment->order?->status?->closesPaymentAttempts() ?? false)
                ? PaymentTransactionErrorCodeEnum::ORDER_CLOSED
                : PaymentTransactionErrorCodeEnum::STUCK_TIMEOUT;

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
                errorCode: $errorCode,
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

    /**
     * @return Collection<int, Payment>
     */
    private function expirablePayments(CarbonInterface $threshold): Collection
    {
        return Payment::query()
            ->with(['order', 'transactions' => function ($query): void {
                $query->latest('initiated_at');
            }])
            ->where('status', PaymentStatusEnum::PENDING)
            // A PENDING order can still be paid, so its attempts are the
            // canceller's to close. This command only owns rows with no live
            // order behind them.
            ->where(fn (Builder $query): Builder => $query
                ->whereNull('order_id')
                ->orWhereHas('order', fn (Builder $order): Builder => $order->where('status', '!=', OrderStatusEnum::PENDING->value)))
            ->where(fn (Builder $query): Builder => $this->staleAttemptScope($query, $threshold))
            ->get();
    }

    /**
     * No in-flight transaction newer than the threshold, and no recent activity.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private function staleAttemptScope(Builder $query, CarbonInterface $threshold): Builder
    {
        return $query
            ->whereDoesntHave('transactions', fn (Builder $transaction): Builder => $this->inFlightTransactionScope($transaction, $threshold, newerThanThreshold: true))
            ->where(function (Builder $query) use ($threshold): void {
                $query
                    ->whereHas('transactions', fn (Builder $transaction): Builder => $this->inFlightTransactionScope($transaction, $threshold, newerThanThreshold: false))
                    ->orWhere(fn (Builder $query) => $query
                        ->whereDoesntHave('transactions', fn (Builder $transaction): Builder => $transaction
                            ->where('status', PaymentTransactionStatusEnum::INITIATED->value))
                        ->where('created_at', '<=', $threshold)
                        ->where(fn (Builder $query) => $query
                            ->whereNull('last_attempted_at')
                            ->orWhere('last_attempted_at', '<=', $threshold)));
            });
    }

    /**
     * A gateway transaction that started but has not completed.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private function inFlightTransactionScope(Builder $query, CarbonInterface $threshold, bool $newerThanThreshold): Builder
    {
        return $query
            ->where('status', PaymentTransactionStatusEnum::INITIATED->value)
            ->whereNull('completed_at')
            ->where('initiated_at', $newerThanThreshold ? '>' : '<=', $threshold);
    }
}
