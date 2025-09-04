<?php

declare(strict_types=1);

namespace App\Actions\Admin\WalletCampaign;

use App\Actions\Wallet\RecordWalletTransactionAction;
use App\Data\Admin\Wallet\ProcessCampaignBonusData;
use App\Data\Wallet\RecordTransactionData;
use App\Enums\Wallet\TransactionSourceEnum;
use App\Enums\Wallet\TransactionTypeEnum;
use App\Events\Wallet\WalletBonusProcessedEvent;
use App\Models\User;
use App\Models\WalletCampaign;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;

readonly class ProcessCampaignBonusAction
{
    public function __construct(
        private RecordWalletTransactionAction $recordWalletTransactionAction
    ) {}

    /**
     * Process campaign bonus for a user based on trigger event
     *
     * @throws \Exception
     */
    public function handle(ProcessCampaignBonusData $data, WalletCampaign $campaign): WalletTransaction
    {
        return DB::transaction(function () use ($data, $campaign) {
            // Get campaign and user
            $user = User::findOrFail($data->user_id);

            // Ensure user has a wallet
            if (!$user->wallet) {
                throw new \Exception(__('validation.custom.wallet_not_found'));
            }

            // Check campaign eligibility
            if (!$campaign->canAllocate($user)) {
                $this->throwEligibilityError($campaign, $user);
            }

            // Check for idempotency based on trigger event
            if ($data->trigger_event) {
                $existingTransaction = WalletTransaction::where([
                    'user_id' => $user->id,
                    'source_type' => TransactionSourceEnum::CAMPAIGN,
                    'source_id' => $campaign->id,
                    'type' => TransactionTypeEnum::BONUS,
                ])
                ->whereJsonContains('metadata->trigger_event', $data->trigger_event)
                ->first();

                if ($existingTransaction) {
                    return $existingTransaction;
                }
            }

            // Prepare transaction description
            $description = __('wallet.campaign.bonus_processed', [
                'campaign' => $campaign->name,
                'event' => $data->trigger_event ?? __('wallet.campaign.manual_trigger')
            ]);

            // Record bonus transaction
            $transactionData = new RecordTransactionData(
                user_id: $user->id,
                type: TransactionTypeEnum::BONUS,
                amount: $campaign->amount,
                source_type: TransactionSourceEnum::CAMPAIGN,
                source_id: $campaign->id,
                description: $description,
                metadata: array_merge($data->metadata ?? [], [
                    'campaign_name' => $campaign->name,
                    'campaign_type' => $campaign->type,
                    'trigger_event' => $data->trigger_event,
                ])
            );

            $transaction = $this->recordWalletTransactionAction->execute($transactionData);

            // Update campaign usage count
            $campaign->incrementUsageCount();

            // Dispatch event
            WalletBonusProcessedEvent::dispatch($transaction, $campaign, $user);

            return $transaction;
        });
    }

    /**
     * Throw appropriate eligibility error based on campaign state
     */
    private function throwEligibilityError(WalletCampaign $campaign, User $user): never
    {
        if (!$campaign->isActive()) {
            throw new \Exception(__('validation.custom.campaign_not_active'));
        }

        if (!$campaign->isWithinDateRange) {
            throw new \Exception(__('validation.custom.campaign_expired'));
        }

        if ($campaign->hasReachedTotalLimit()) {
            throw new \Exception(__('validation.custom.usage_limit_reached'));
        }

        if ($campaign->hasReachedUserLimit($user)) {
            throw new \Exception(__('validation.custom.already_claimed'));
        }

        throw new \Exception(__('validation.custom.user_not_eligible'));
    }
}
