<?php

declare(strict_types=1);

namespace App\Actions\Wallet;

use App\Data\Admin\Wallet\RecordTransactionData;
use App\Models\User;
use App\Models\WalletTransaction;
use Exception;
use Facades\App\Models\Wallet;
use Illuminate\Support\Facades\DB;

final class RecordWalletTransactionAction
{
    /**
     * Record a wallet transaction with atomic balance update.
     * Uses database locking to prevent race conditions.
     *
     * @throws Exception
     */
    public function execute(RecordTransactionData $data): WalletTransaction
    {
        $user = User::find($data->user_id);
        if (! $user) {
            throw new Exception(__('validation.custom.user_not_found'));
        }

        $wallet = $user->wallet;
        if (! $wallet) {
            throw new Exception(__('validation.custom.wallet_not_found'));
        }

        return DB::transaction(function () use ($wallet, $user, $data) {
            // Lock the wallet row to prevent race conditions
            $wallet = Wallet::where('id', $wallet->id)->lockForUpdate()->first();

            // @codeCoverageIgnoreStart
            if (! $wallet) {
                throw new Exception(__('validation.custom.wallet_not_found'));
            }
            // @codeCoverageIgnoreEnd

            if ($data->type->isDebit()) {
                $data->amount = -abs($data->amount);
            }

            if ($data->type->isCredit()) {
                $data->amount = abs($data->amount);
            }

            // Calculate new balances
            $newBalance     = $wallet->balance + $data->amount;
            $newGiftBalance = $wallet->gift_balance;

            // For gift transactions, update gift balance instead
            if ($data->type->isGift()) {
                $newGiftBalance = $wallet->gift_balance + $data->amount;
                $newBalance     = $wallet->balance; // Don't change regular balance for gifts
            }

            // Validate balance constraints
            if ($newBalance < 0) {
                throw new Exception(__('validation.custom.insufficient_balance'));
            }

            // Update wallet balance atomically
            $wallet->update([
                'balance'      => $newBalance,
                'gift_balance' => $newGiftBalance,
            ]);

            // Create transaction record with enhanced audit metadata
            $auditMetadata = array_merge($data->metadata ?? [], [
                'audit' => [
                    'ip_address'         => request()->ip(),
                    'user_agent'         => request()->userAgent(),
                    'session_id'         => session()->getId(),
                    'admin_id'           => auth('staff')->id(),
                    'admin_name'         => auth('staff')->user()?->name,
                    'timestamp'          => now()->toISOString(),
                    'request_id'         => request()->header('X-Request-ID') ?? uniqid(),
                    'risk_level'         => $this->assessTransactionRisk($data->amount, $data->type->value),
                    'is_admin_initiated' => auth('staff')->check(),
                    'source_details'     => [
                        'source_type' => $data->source_type->value,
                        'source_id'   => $data->source_id,
                        'description' => $data->description,
                    ],
                ],
            ]);

            $transaction = WalletTransaction::create([
                'wallet_id'          => $wallet->id,
                'user_id'            => $user->id,
                'type'               => $data->type,
                'amount'             => $data->amount,
                'balance_after'      => $newBalance,
                'gift_balance_after' => $newGiftBalance,
                'source_type'        => $data->source_type,
                'source_id'          => $data->source_id,
                'description'        => $data->description,
                'metadata'           => $auditMetadata,
                'expires_at'         => $data->expires_at ? now()->parse($data->expires_at) : null,
            ]);

            return $transaction;
        });
    }

    /**
     * Assess the risk level of a transaction based on amount and type
     */
    private function assessTransactionRisk(int $amount, string $transactionType): string
    {
        $absoluteAmount = abs($amount);

        // High-risk thresholds (in rials)
        $highRiskAmount   = 50000000; // 50M IRR (approx $1000)
        $mediumRiskAmount = 5000000; // 5M IRR (approx $100)

        // High-risk transaction types
        $highRiskTypes = ['withdrawal', 'adjustment'];

        // Check amount-based risk
        if ($absoluteAmount >= $highRiskAmount) {
            return 'high';
        }

        if ($absoluteAmount >= $mediumRiskAmount) {
            return 'medium';
        }

        // Check type-based risk
        if (in_array($transactionType, $highRiskTypes) && $absoluteAmount >= 1000000) {
            return 'medium';
        }

        // Check time-based risk (transactions outside business hours)
        $hour = now()->hour;
        if (($hour < 6 || $hour > 22)) {
            return 'medium';
        }

        return 'low';
    }
}
