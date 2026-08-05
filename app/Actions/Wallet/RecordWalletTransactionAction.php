<?php

declare(strict_types=1);

namespace App\Actions\Wallet;

use App\Data\Admin\Wallet\RecordTransactionData;
use App\Enums\Wallet\TransactionSourceEnum;
use App\Enums\Wallet\TransactionTypeEnum;
use App\Enums\Wallet\WalletStatusEnum;
use App\Exceptions\Wallet\WalletInsufficientBalanceException;
use App\Exceptions\Wallet\WalletNotActive;
use App\Exceptions\Wallet\WalletNotFoundException;
use App\Exceptions\Wallet\WalletUserNotFoundException;
use App\Models\User;
use App\Models\WalletTransaction;
use Facades\App\Models\Wallet;
use Illuminate\Support\Facades\DB;

final class RecordWalletTransactionAction
{
    /**
     * Record a wallet transaction with atomic balance update.
     * Uses database locking to prevent race conditions.
     *
     * @throws WalletUserNotFoundException|WalletNotFoundException|WalletInsufficientBalanceException
     */
    public function execute(RecordTransactionData $data): WalletTransaction
    {
        $user = User::find($data->user_id);
        if (! $user) {
            throw new WalletUserNotFoundException($data->user_id);
        }

        $wallet = $user->wallet;
        if (! $wallet) {
            throw new WalletNotFoundException($user->id);
        }

        return DB::transaction(function () use ($wallet, $user, $data) {
            // Lock the wallet row to prevent race conditions
            $wallet = Wallet::where('id', $wallet->id)->lockForUpdate()->first();

            // @codeCoverageIgnoreStart
            if (! $wallet) {
                throw new WalletNotFoundException($user->id);
            }
            // @codeCoverageIgnoreEnd

            if ($data->idempotency_key !== null) {
                $existingTransaction = WalletTransaction::query()
                    ->where('idempotency_key', $data->idempotency_key)
                    ->first();

                if ($existingTransaction) {
                    return $existingTransaction;
                }
            }

            if (! $this->canProcessTransactionForStatus($wallet->status, $data->type)) {
                throw new WalletNotActive();
            }

            if ($data->type->isDebit()) {
                $data->amount = -abs($data->amount);
            }

            if ($data->type->isCredit()) {
                $data->amount = abs($data->amount);
            }

            $newBalance      = $wallet->balance;
            $newGiftBalance  = $wallet->gift_balance;
            $fromGiftBalance = 0;

            if ($data->type->isGift()) {
                $newGiftBalance = $wallet->gift_balance + $data->amount;
            } elseif ($data->type === TransactionTypeEnum::PAYMENT && $data->source_type === TransactionSourceEnum::ORDER) {
                $debitAmount     = abs($data->amount);
                $fromBalance     = min($wallet->balance, $debitAmount);
                $fromGiftBalance = $debitAmount - $fromBalance;

                $newBalance     = $wallet->balance      - $fromBalance;
                $newGiftBalance = $wallet->gift_balance - $fromGiftBalance;
            } else {
                $newBalance = $wallet->balance + $data->amount;
            }

            if ($data->type === TransactionTypeEnum::PAYMENT && $data->source_type === TransactionSourceEnum::ORDER) {
                $availableTotal = $wallet->balance + $wallet->gift_balance;
                // dump($availableTotal, $data->amount,$availableTotal + $data->amount,$newBalance < 0);
                if ($availableTotal + $data->amount < 0) {
                    throw new WalletInsufficientBalanceException(
                        availableBalance: $availableTotal,
                        requiredBalance: abs($data->amount),
                        shortfall: abs($availableTotal + $data->amount),
                        sourceType: $data->source_type,
                        sourceId: $data->source_id,
                    );
                }
            } elseif ($newBalance < 0) {
                throw new WalletInsufficientBalanceException(
                    availableBalance: $wallet->balance,
                    requiredBalance: abs($data->amount),
                    shortfall: abs($newBalance),
                    sourceType: $data->source_type,
                    sourceId: $data->source_id,
                );
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
                    'wallet_debit_split' => [
                        'from_balance'      => $fromBalance ?? abs($data->amount),
                        'from_gift_balance' => $fromGiftBalance,
                    ],
                    'source_details' => [
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
                'idempotency_key'    => $data->idempotency_key,
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

    private function canProcessTransactionForStatus(WalletStatusEnum $status, TransactionTypeEnum $type): bool
    {
        if ($status === WalletStatusEnum::ACTIVE) {
            return true;
        }

        if ($status === WalletStatusEnum::SUSPENDED) {
            return in_array($type, [TransactionTypeEnum::REFUND], true);
        }

        return false;
    }
}
