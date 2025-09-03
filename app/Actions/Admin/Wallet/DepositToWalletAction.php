<?php

declare(strict_types=1);

namespace App\Actions\Admin\Wallet;

use App\Actions\Wallet\RecordWalletTransactionAction;
use App\Data\Wallet\DepositToWalletData;
use App\Data\Wallet\RecordTransactionData;
use App\Enums\Wallet\TransactionSourceEnum;
use App\Enums\Wallet\TransactionTypeEnum;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Support\Facades\Lang;

readonly class DepositToWalletAction
{
    public function __construct(
        private RecordWalletTransactionAction $recordTransactionAction
    ) {
    }

    /**
     * Deposit amount to user's wallet.
     *
     * @throws \Exception
     */
    public function execute(DepositToWalletData $data, Staff $staff): \App\Models\WalletTransaction
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

        return $this->recordTransactionAction->execute(new RecordTransactionData(
            user_id: $user->id,
            type: TransactionTypeEnum::DEPOSIT,
            amount: $data->amount,
            source_type: TransactionSourceEnum::STAFF,
            source_id: $staff->id,
            description: $data->description ?? __('wallet.deposit_description'),
            metadata: $data->metadata ?? [],
        ));
    }
}
