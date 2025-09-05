<?php

declare(strict_types=1);

namespace App\Actions\Admin\Wallet;

use App\Actions\Wallet\RecordWalletTransactionAction;
use App\Data\Admin\Wallet\DepositToWalletData;
use App\Data\Admin\Wallet\RecordTransactionData;
use App\Enums\Wallet\TransactionSourceEnum;
use App\Enums\Wallet\TransactionTypeEnum;
use App\Models\Staff;
use App\Models\Wallet;

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
    public function handle(DepositToWalletData $data, Staff $staff, Wallet $wallet): \App\Models\WalletTransaction
    {
        if (!$wallet->isActive()) {
            throw new \Exception(__('validation.custom.wallet_not_active'));
        }

        return $this->recordTransactionAction->execute(new RecordTransactionData(
            user_id: $wallet->user_id,
            type: TransactionTypeEnum::DEPOSIT,
            amount: $data->amount,
            source_type: TransactionSourceEnum::STAFF,
            source_id: $staff->id,
            description: $data->description,
            metadata: $data->metadata ?? [],
        ));
    }
}
