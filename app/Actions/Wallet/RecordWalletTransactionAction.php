<?php

declare(strict_types=1);

namespace App\Actions\Wallet;

use App\Data\Wallet\RecordTransactionData;
use App\Enums\Wallet\TransactionTypeEnum;
use Facades\App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;

class RecordWalletTransactionAction
{
    /**
     * Record a wallet transaction with atomic balance update.
     * Uses database locking to prevent race conditions.
     *
     * @throws \Exception
     */
    public function execute(RecordTransactionData $data): WalletTransaction
    {
        $user = User::find($data->user_id);
        if (!$user) {
            throw new \Exception(__('validation.custom.user_not_found'));
        }

        $wallet = $user->wallet;
        if (!$wallet) {
            throw new \Exception(__('validation.custom.wallet_not_found'));
        }

        return DB::transaction(function () use ($wallet, $user, $data) {
            // Lock the wallet row to prevent race conditions
            $wallet = Wallet::where('id', $wallet->id)->lockForUpdate()->first();

            //@codeCoverageIgnoreStart
            if (!$wallet) {
                throw new \Exception(__('validation.custom.wallet_not_found'));
            }
            //@codeCoverageIgnoreEnd


            if ($data->type->isDebit()){
                $data->amount = -abs($data->amount);
            }

            if ($data->type->isCredit()){
                $data->amount = abs($data->amount);
            }

            // Calculate new balances
            $newBalance = $wallet->balance + $data->amount;
            $newGiftBalance = $wallet->gift_balance;

            // For gift transactions, update gift balance instead
            if ($data->type->isGift()) {
                $newGiftBalance = $wallet->gift_balance + $data->amount;
                $newBalance = $wallet->balance; // Don't change regular balance for gifts
            }

            // Validate balance constraints
            if ($newBalance < 0) {
                throw new \Exception(__('validation.custom.insufficient_balance'));
            }

            // Update wallet balance atomically
            $wallet->update([
                'balance' => $newBalance,
                'gift_balance' => $newGiftBalance,
            ]);

            // Create transaction record
            $transaction = WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'user_id' => $user->id,
                'type' => $data->type,
                'amount' => $data->amount,
                'balance_after' => $newBalance,
                'gift_balance_after' => $newGiftBalance,
                'source_type' => $data->source_type,
                'source_id' => $data->source_id,
                'description' => $data->description,
                'metadata' => $data->metadata ?? [],
                'expires_at' => $data->expires_at ? now()->parse($data->expires_at) : null,
            ]);

            return $transaction;
        });
    }
}
