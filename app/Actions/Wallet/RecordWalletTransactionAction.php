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
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Facades\App\Models\Wallet as WalletFacade;
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
            $wallet = WalletFacade::where('id', $wallet->id)->lockForUpdate()->first();

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

            $newBalance       = $wallet->balance;
            $newGiftBalance   = $wallet->gift_balance;
            $fromGiftBalance  = 0;
            $remainingAmount  = null;
            $giftConsumptions = [];
            $isOrderPayment   = $data->type === TransactionTypeEnum::PAYMENT && $data->source_type === TransactionSourceEnum::ORDER;

            if ($data->type->isGift()) {
                $newGiftBalance  = $wallet->gift_balance + $data->amount;
                $remainingAmount = $data->amount;
            } elseif ($isOrderPayment) {
                $debitAmount    = abs($data->amount);
                $availableTotal = $wallet->balance + $wallet->gift_balance;

                if ($availableTotal + $data->amount < 0) {
                    throw new WalletInsufficientBalanceException(
                        availableBalance: $availableTotal,
                        requiredBalance: $debitAmount,
                        shortfall: abs($availableTotal + $data->amount),
                        sourceType: $data->source_type,
                        sourceId: $data->source_id,
                    );
                }

                $giftSplit = $this->consumeGiftFifo($wallet, $debitAmount);

                $fromBalance      = $giftSplit['from_balance'];
                $fromGiftBalance  = $giftSplit['from_gift_balance'];
                $giftConsumptions = $giftSplit['gift_consumptions'];

                $newBalance     = $wallet->balance      - $fromBalance;
                $newGiftBalance = $wallet->gift_balance - $fromGiftBalance;
            } else {
                $newBalance = $wallet->balance + $data->amount;
            }

            if (! $isOrderPayment && $newBalance < 0) {
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
                        'gift_consumptions' => $giftConsumptions ?: null,
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
                'remaining_amount'   => $remainingAmount,
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
     * Consume gift balance oldest-first (FIFO by receipt) for an order debit.
     * Gift credits are depleted by their tracked remaining slice; gift balance
     * without a ledger row (e.g. initial gift_balance set at wallet creation)
     * is consumed as untracked gift before falling back to normal balance.
     *
     * @return array{from_balance: int, from_gift_balance: int, gift_consumptions: list<array{transaction_id: int, amount: int}>}
     */
    private function consumeGiftFifo(Wallet $wallet, int $debitAmount): array
    {
        $fromGift         = 0;
        $giftConsumptions = [];

        $remainingToConsume = min($debitAmount, $wallet->gift_balance);

        $giftCredits = WalletTransaction::query()
            ->where('wallet_id', $wallet->id)
            ->whereIn('type', [TransactionTypeEnum::GIFT, TransactionTypeEnum::BONUS])
            ->where('amount', '>', 0)
            ->where('remaining_amount', '>', 0)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        foreach ($giftCredits as $credit) {
            if ($remainingToConsume <= 0) {
                break;
            }

            $consume = min((int) $credit->remaining_amount, $remainingToConsume);
            $fromGift += $consume;
            $remainingToConsume -= $consume;

            $credit->update(['remaining_amount' => (int) $credit->remaining_amount - $consume]);

            $giftConsumptions[] = [
                'transaction_id' => $credit->id,
                'amount'         => $consume,
            ];
        }

        // Any remaining slice of the debit is untracked gift balance, then normal balance.
        $fromGift += $remainingToConsume;

        return [
            'from_balance'      => $debitAmount - $fromGift,
            'from_gift_balance' => $fromGift,
            'gift_consumptions' => $giftConsumptions,
        ];
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
