<?php

declare(strict_types=1);

namespace App\Actions\Admin\Wallet;

use App\Actions\Wallet\RecordWalletTransactionAction;
use App\Data\Wallet\RecordTransactionData;
use App\Data\Wallet\WithdrawFromWalletData;
use App\Enums\Wallet\TransactionSourceEnum;
use App\Enums\Wallet\TransactionTypeEnum;
use App\Models\Staff;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\Lang;

readonly class WithdrawFromWalletAction
{
    public function __construct(
        private RecordWalletTransactionAction $recordTransactionAction
    ) {
    }

    /**
     * Withdraw amount from user's wallet.
     *
     * @throws \Exception
     */
    public function handle(WithdrawFromWalletData $data, Staff $staff, Wallet $wallet): \App\Models\WalletTransaction
    {
        if (!$wallet->isActive()) {
            throw new \Exception(__('validation.custom.wallet_not_active'));
        }

        if (!$wallet->canWithdraw($data->amount)) {
            throw new \Exception(__('validation.custom.insufficient_balance'));
        }

        return $this->recordTransactionAction->execute(new RecordTransactionData(
            user_id     : $wallet->user_id,
            type        : TransactionTypeEnum::WITHDRAWAL,
            amount      : $data->amount,
            source_type : TransactionSourceEnum::STAFF,
            source_id   : $staff->id,
            description : $data->description,
            metadata    : $data->metadata ?? [],
        ));
    }
}
