<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\Payment\PaymentStatusEnum;
use App\Enums\Payment\PaymentTransactionStatusEnum;
use App\Models\Payment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

final class CheckStuckPaymentsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payments:check-stuck
                            {--threshold=30 : Minutes after which a payment is considered stuck}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for stuck payments that have not completed after threshold time';

    /**
     * Execute the console command.
     *
     * This command identifies payments that are stuck in PENDING status where:
     * - The latest payment transaction was initiated more than X minutes ago
     * - The transaction has not completed (no completed_at timestamp)
     *
     * These stuck payments are logged for manual review by the support team.
     */
    public function handle(): int
    {
        $thresholdMinutes = (int) $this->option('threshold');
        $thresholdTime    = now()->subMinutes($thresholdMinutes);

        $this->info("Checking for stuck payments (threshold: {$thresholdMinutes} minutes)...");

        // Find payments that are PENDING with stuck transactions
        $stuckPayments = Payment::query()
            ->with(['order', 'transactions' => function ($query): void {
                $query->latest('initiated_at');
            }])
            ->where('status', PaymentStatusEnum::PENDING)
            ->whereHas('transactions', function ($query) use ($thresholdTime): void {
                $query->where('initiated_at', '<=', $thresholdTime)
                    ->whereNull('completed_at')
                    ->where('status', PaymentTransactionStatusEnum::INITIATED);
            })
            ->get();

        if ($stuckPayments->isEmpty()) {
            $this->info('No stuck payments found.');

            return self::SUCCESS;
        }

        $this->warn("Found {$stuckPayments->count()} stuck payment(s):");

        foreach ($stuckPayments as $payment) {
            $latestTransaction = $payment->transactions->first();

            $logData = [
                'payment_id'            => $payment->id,
                'order_id'              => $payment->order_id,
                'order_increment_id'    => $payment->order?->increment_id,
                'transaction_reference' => $latestTransaction?->transaction_reference,
                'initiated_at'          => $latestTransaction?->initiated_at?->toDateTimeString(),
                'minutes_stuck'         => $latestTransaction?->initiated_at?->diffInMinutes(now()),
            ];

            // Log to application log
            Log::warning('Stuck payment detected', $logData);

            // Output to console
            $this->table(
                ['Field', 'Value'],
                collect($logData)->map(fn ($value, $key): array => [$key, $value])->values()->all()
            );
        }

        return self::SUCCESS;
    }
}
