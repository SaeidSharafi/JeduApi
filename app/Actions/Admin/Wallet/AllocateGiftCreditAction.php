<?php

declare(strict_types=1);

namespace App\Actions\Admin\Wallet;

use App\Actions\Wallet\RecordWalletTransactionAction;
use App\Data\Admin\Wallet\AllocateGiftCreditData;
use App\Data\Wallet\RecordTransactionData;
use App\Enums\Wallet\TransactionSourceEnum;
use App\Enums\Wallet\TransactionTypeEnum;
use App\Events\Wallet\WalletGiftCreditAllocatedEvent;
use App\Exceptions\CustomValidationException;
use App\Models\WalletCampaign;
use App\Models\WalletTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

readonly class AllocateGiftCreditAction
{
    public function __construct(
        private RecordWalletTransactionAction $recordWalletTransactionAction
    ) {}

    /**
     * Allocate gift credit from a campaign to a user with idempotency checks
     *
     * @throws \Exception
     */
    public function handle(AllocateGiftCreditData $data, WalletCampaign $campaign): WalletTransaction
    {
        return DB::transaction(function () use ($data, $campaign) {
            // Get campaign and user
            $user = User::findOrFail($data->user_id);

            // Ensure user has a wallet
            if (!$user->wallet) {
                throw new CustomValidationException(__('validation.custom.wallet_not_found'));
            }

            // Check campaign eligibility
            if (!$campaign->canAllocate($user)) {
                $this->throwEligibilityError($campaign, $user);
            }

            // Check for idempotency - prevent duplicate allocations
            $existingTransaction = WalletTransaction::where([
                'user_id' => $user->id,
                'source_type' => TransactionSourceEnum::CAMPAIGN,
                'source_id' => $campaign->id,
                'type' => TransactionTypeEnum::GIFT,
            ])->first();

            if ($existingTransaction) {
                // Return existing transaction for idempotency
                return $existingTransaction;
            }

            // Prepare transaction description
            $description = $data->reason ?? __('wallet.campaign.gift_allocated', [
                'campaign' => $campaign->name
            ]);

            // Record gift transaction
            $transactionData = new RecordTransactionData(
                user_id: $user->id,
                type: TransactionTypeEnum::GIFT,
                amount: $campaign->amount,
                source_type: TransactionSourceEnum::CAMPAIGN,
                source_id: $campaign->id,
                description: $description,
                metadata: array_merge($data->metadata ?? [], [
                    'campaign_name' => $campaign->name,
                    'campaign_type' => $campaign->type,
                    'allocation_reason' => $data->reason,
                ])
            );

            $transaction = $this->recordWalletTransactionAction->execute($transactionData);

            // Update campaign usage count
            $campaign->incrementUsageCount();

            // Dispatch event
            WalletGiftCreditAllocatedEvent::dispatch($transaction, $campaign, $user);

            return $transaction;
        });
    }

    /**
     * Throw appropriate eligibility error based on campaign state
     */
    private function throwEligibilityError(WalletCampaign $campaign, User $user): never
    {
        if (!$campaign->isActive()) {
            throw new CustomValidationException(__('validation.custom.campaign_not_active'));
        }

        if (!$campaign->isWithinDateRange) {
            throw new CustomValidationException(__('validation.custom.campaign_expired'));
        }

        if ($campaign->hasReachedTotalLimit()) {
            throw new CustomValidationException(__('validation.custom.usage_limit_reached'));
        }

        if ($campaign->hasReachedUserLimit($user)) {
            throw new CustomValidationException(__('validation.custom.already_claimed'));
        }

        throw new CustomValidationException(__('validation.custom.user_not_eligible'));
    }
}
