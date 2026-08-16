<?php

declare(strict_types=1);

namespace App\Actions\Wallet;

use App\Data\Admin\Wallet\RecordTransactionData;
use App\Enums\Wallet\TransactionSourceEnum;
use App\Enums\Wallet\TransactionTypeEnum;
use App\Enums\Wallet\WalletStatusEnum;
use App\Exceptions\Wallet\GiftAlreadyFullyReclaimedException;
use App\Models\WalletTransaction;

final class ReclaimExpiredGiftsAction
{
    public function __construct(
        private readonly RecordWalletTransactionAction $recordTransactionAction,
    ) {}

    /**
     * Reclaim every expired, unspent gift credit through an EXPIRY ledger debit.
     *
     * Gifts already fully spent, gifts without a past expiry, and gifts in
     * non-active wallets are never candidates. Already-reclaimed gifts are
     * excluded by the existence of their deterministic EXPIRY transaction.
     *
     * @return array{reclaimed: int, skipped: int}
     */
    public function execute(bool $dryRun = false): array
    {
        $candidates = WalletTransaction::query()
            ->whereIn('type', [TransactionTypeEnum::GIFT, TransactionTypeEnum::BONUS])
            ->where('amount', '>', 0)
            ->where('remaining_amount', '>', 0)
            ->where('expires_at', '<', now())
            ->whereHas('wallet', fn ($query) => $query->where('status', WalletStatusEnum::ACTIVE))
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('wallet_transactions as expiry_tx')
                    ->whereColumn('expiry_tx.source_id', 'wallet_transactions.id')
                    ->where('expiry_tx.type', TransactionTypeEnum::EXPIRY->value);
            })
            ->orderBy('id')
            ->get();

        if ($dryRun) {
            return ['reclaimed' => $candidates->count(), 'skipped' => 0];
        }

        $reclaimed = 0;
        $skipped   = 0;

        foreach ($candidates as $gift) {
            try {
                $this->recordTransactionAction->execute(
                    RecordTransactionData::from([
                        'user_id'     => $gift->user_id,
                        'type'        => TransactionTypeEnum::EXPIRY,
                        'amount'      => (int) $gift->remaining_amount,
                        'source_type' => TransactionSourceEnum::SYSTEM,
                        'source_id'   => $gift->id,
                        'description' => __('wallet.gift_expiry_reclaimed'),
                        'metadata'    => [
                            'gift_expires_at' => $gift->expires_at?->toISOString(),
                        ],
                        'idempotency_key'     => "wallet-gift-expiry:{$gift->id}",
                        'gift_transaction_id' => $gift->id,
                    ])
                );

                $reclaimed++;
            } catch (GiftAlreadyFullyReclaimedException) {
                // A concurrent order payment consumed the gift between the
                // candidate query and the wallet lock; nothing left to reclaim.
                $skipped++;
            }
        }

        return ['reclaimed' => $reclaimed, 'skipped' => $skipped];
    }
}
