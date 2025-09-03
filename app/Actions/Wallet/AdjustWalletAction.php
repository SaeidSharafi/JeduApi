<?php

declare(strict_types=1);

namespace App\Actions\Wallet;

use App\Actions\Wallet\RecordWalletTransactionAction;
use App\Data\Wallet\AdjustWalletData;
use App\Data\Wallet\RecordTransactionData;
use App\Enums\Wallet\TransactionSourceEnum;
use App\Enums\Wallet\TransactionTypeEnum;
use App\Models\User;
use Illuminate\Support\Facades\Lang;

readonly class AdjustWalletAction
{
    public function __construct(
        private RecordWalletTransactionAction $recordTransactionAction
    ) {}

    /**
     * Adjust wallet balance for dispute resolution or error correction.
     * Can be positive (credit) or negative (debit).
     *
     * @throws \Exception
     */
    public function execute(AdjustWalletData $data): \App\Models\WalletTransaction
    {
        $user = User::find($data->user_id);
        if (!$user) {
            throw new \Exception(Lang::get('validation.user_not_found'));
        }

        $wallet = $user->wallet;
        if (!$wallet) {
            throw new \Exception(Lang::get('validation.wallet_not_found'));
        }

        if (!$wallet->isActive()) {
            throw new \Exception(Lang::get('validation.wallet_not_active'));
        }

        // For negative adjustments, check if there's sufficient balance
        if ($data->amount < 0 && !$wallet->canWithdraw(abs($data->amount))) {
            throw new \Exception(Lang::get('validation.insufficient_balance'));
        }

        $description = $data->description ?? __('wallet.adjustment_description', ['reason' => $data->reason]);

        return $this->recordTransactionAction->execute(new RecordTransactionData(
            user_id: $user->id,
            type: TransactionTypeEnum::ADJUSTMENT,
            amount: $data->amount,
            source_type: TransactionSourceEnum::STAFF,
            description: $description,
            metadata: array_merge($data->metadata ?? [], [
                'reason' => $data->reason,
                'adjustment_type' => $data->amount > 0 ? 'credit' : 'debit',
            ]),
        ));
    }
}
