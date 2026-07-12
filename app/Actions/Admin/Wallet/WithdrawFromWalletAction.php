<?php

declare(strict_types=1);

namespace App\Actions\Admin\Wallet;

use App\Actions\Wallet\RecordWalletTransactionAction;
use App\Data\Admin\Wallet\RecordTransactionData;
use App\Data\Admin\Wallet\WithdrawFromWalletData;
use App\Enums\Wallet\TransactionSourceEnum;
use App\Enums\Wallet\TransactionTypeEnum;
use App\Exceptions\Wallet\WalletInsufficientBalanceException;
use App\Exceptions\Wallet\WalletNotActive;
use App\Models\Staff;
use App\Models\Wallet;
use Exception;

final readonly class WithdrawFromWalletAction
{
    public function __construct(
        private RecordWalletTransactionAction $recordTransactionAction
    ) {}

    /**
     * Withdraw amount from user's wallet.
     *
     * @throws Exception
     */
    public function handle(WithdrawFromWalletData $data, Staff $staff, Wallet $wallet): \App\Models\WalletTransaction
    {
        if (! $wallet->isActive()) {
            throw new WalletNotActive();
        }

        if (! $wallet->canWithdraw($data->amount)) {
            throw new WalletInsufficientBalanceException(
                availableBalance: $wallet->balance,
                requiredBalance: $data->amount,
                shortfall: abs($data->amount) - $wallet->balance,
                sourceType: TransactionSourceEnum::STAFF,
                sourceId: $staff->id,
            );
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
