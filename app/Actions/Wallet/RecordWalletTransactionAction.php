<?php

declare(strict_types=1);

namespace App\Actions\Wallet;

use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use App\Enums\Wallet\TransactionTypeEnum;
use App\Enums\Wallet\TransactionSourceEnum;

class RecordWalletTransactionAction
{
    /**
     * Record a wallet transaction for a user and wallet.
     * Supports polymorphic relation to order/refund.
     *
     * @param array $data
     * @return WalletTransaction
     * @throws \Exception
     */
    public function execute(array $data): WalletTransaction
    {
        $user = User::find($data['user_id'] ?? null);
        if (! $user) {
            throw new \Exception(Lang::get('validation.user_not_found'));
        }
        $wallet = $user->wallet;
        if (! $wallet) {
            throw new \Exception(Lang::get('validation.wallet_not_found'));
        }
        return DB::transaction(function () use ($wallet, $user, $data) {
            $transaction = new WalletTransaction([
                'wallet_id'           => $wallet->id,
                'user_id'             => $user->id,
                'type'                => TransactionTypeEnum::from($data['type']),
                'amount'              => $data['amount'],
                'balance_after'       => $wallet->balance + $data['amount'],
                'gift_balance_after'  => $wallet->gift_balance ?? 0,
                'source_type'         => $data['source_type'] ?? null,
                'source_id'           => $data['source_id'] ?? null,
                'description'         => $data['description'] ?? null,
                'metadata'            => $data['metadata'] ?? [],
                'expires_at'          => $data['expires_at'] ?? null,
            ]);
            $transaction->save();
            // Update wallet balance
            $wallet->balance += $data['amount'];
            $wallet->save();
            // Attach polymorphic source if provided
            if (!empty($data['source_type']) && !empty($data['source_id'])) {
                $transaction->source()->associate([
                    'id'   => $data['source_id'],
                    'type' => $data['source_type'],
                ]);
                $transaction->save();
            }
            return $transaction;
        });
    }
}
