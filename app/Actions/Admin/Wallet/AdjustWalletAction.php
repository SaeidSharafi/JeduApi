<?php

declare(strict_types=1);

namespace App\Actions\Admin\Wallet;

use App\Actions\Wallet\RecordWalletTransactionAction;
use App\Data\Admin\Wallet\AdjustWalletData;
use App\Data\Admin\Wallet\RecordTransactionData;
use App\Enums\Wallet\TransactionSourceEnum;
use App\Enums\Wallet\TransactionTypeEnum;
use App\Models\Staff;
use App\Models\Wallet;
use Exception;

final readonly class AdjustWalletAction
{
    public function __construct(
        private RecordWalletTransactionAction $recordTransactionAction
    ) {}

    /**
     * Adjust wallet balance for dispute resolution or error correction.
     * Can be positive (credit) or negative (debit).
     *
     * @throws Exception
     */
    public function handle(AdjustWalletData $data, Staff $staff, Wallet $wallet): \App\Models\WalletTransaction
    {
        if (! $wallet->isActive()) {
            throw new Exception(__('validation.custom.wallet_not_active'));
        }

        // For negative adjustments, check if there's sufficient balance
        if ($data->amount < 0 && ! $wallet->canWithdraw(abs($data->amount))) {
            throw new Exception(__('validation.custom.insufficient_balance'));
        }

        $description = $data->description;

        return $this->recordTransactionAction->execute(new RecordTransactionData(
            user_id: $wallet->user_id,
            type: TransactionTypeEnum::ADJUSTMENT,
            amount: $data->amount,
            source_type: TransactionSourceEnum::STAFF,
            source_id: $staff->id,
            description: $description,
            metadata: array_merge($data->metadata ?? [], [
                'reason'          => $data->reason,
                'adjustment_type' => $data->amount > 0 ? 'credit' : 'debit',
            ]),
        ));
    }
}
