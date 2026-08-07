<?php

declare(strict_types=1);

namespace App\Actions\Admin\WalletCampaign;

use App\Actions\Wallet\RecordWalletTransactionAction;
use App\Data\Admin\Wallet\RecordTransactionData;
use App\Data\Admin\WalletCampaign\TriggerCampaignAllocationData;
use App\Enums\Wallet\TransactionSourceEnum;
use App\Enums\Wallet\TransactionTypeEnum;
use App\Events\Wallet\WalletCampaignAllocationTriggeredEvent;
use App\Exceptions\CustomValidationException;
use App\Exceptions\Wallet\WalletInsufficientBalanceException;
use App\Exceptions\Wallet\WalletNotFoundException;
use App\Exceptions\Wallet\WalletUserNotFoundException;
use App\Models\User;
use App\Models\WalletCampaign;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;

final readonly class TriggerCampaignAllocationAction
{
    public function __construct(
        private RecordWalletTransactionAction $recordWalletTransactionAction
    ) {}

    /**
     * Trigger a campaign allocation for a user (manual or event-based)
     *
     * @throws CustomValidationException|WalletUserNotFoundException|WalletNotFoundException|WalletInsufficientBalanceException
     */
    public function handle(TriggerCampaignAllocationData $data, User $user, WalletCampaign $campaign): WalletTransaction
    {
        return DB::transaction(function () use ($data, $campaign, $user) {
            $campaign = WalletCampaign::query()->whereKey($campaign->id)->lockForUpdate()->firstOrFail();

            // Ensure user has a wallet
            if (! $user->wallet) {
                throw new CustomValidationException(__('validation.custom.wallet_not_found'));
            }

            // Check campaign allocation eligibility
            $status = $campaign->allocationStatus($user);
            if ($status->isError()) {
                throw new CustomValidationException($status->message());
            }

            // Check for duplicate allocations based on trigger type
            $existingTransaction = $this->checkForDuplicateAllocation($user, $campaign, $data);
            if ($existingTransaction) {
                return $existingTransaction;
            }

            // Create transaction description
            $description = $this->buildTransactionDescription($campaign, $data);

            // Record the transaction
            $idempotencyKey = $this->generateIdempotencyKey($campaign, $user, $data);

            $existingByIdempotencyKey = WalletTransaction::query()
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existingByIdempotencyKey) {
                return $existingByIdempotencyKey;
            }

            $transactionData = new RecordTransactionData(
                user_id: $user->id,
                type: TransactionTypeEnum::GIFT, // Simplified: use GIFT for all campaign allocations
                amount: $campaign->amount,
                source_type: TransactionSourceEnum::CAMPAIGN,
                source_id: $campaign->id,
                description: $description,
                metadata: $this->buildTransactionMetadata($campaign, $data),
                idempotency_key: $idempotencyKey,
            );

            $transaction = $this->recordWalletTransactionAction->execute($transactionData);

            // Update campaign usage
            $campaign->incrementUsageCount();

            // Dispatch unified event
            WalletCampaignAllocationTriggeredEvent::dispatch($transaction, $campaign, $user, $data->trigger_type);

            return $transaction;
        });
    }

    /**
     * Check for existing allocation based on trigger type
     */
    private function checkForDuplicateAllocation(User $user, WalletCampaign $campaign, TriggerCampaignAllocationData $data): ?WalletTransaction
    {
        $query = WalletTransaction::where([
            'user_id'     => $user->id,
            'source_type' => TransactionSourceEnum::CAMPAIGN,
            'source_id'   => $campaign->id,
            'type'        => TransactionTypeEnum::GIFT,
        ]);

        // For event-based triggers, check for specific trigger event
        if ($data->trigger_type === 'event' && $data->trigger_event) {
            $query->whereJsonContains('metadata->trigger_event', $data->trigger_event);
        }

        // For manual triggers, check if any manual allocation exists
        if ($data->trigger_type === 'manual') {
            $query->whereJsonContains('metadata->trigger_type', 'manual');
        }

        return $query->first();
    }

    /**
     * Build transaction description based on trigger type
     */
    private function buildTransactionDescription(WalletCampaign $campaign, TriggerCampaignAllocationData $data): string
    {
        if ($data->trigger_type === 'manual') {
            return $data->reason ?? __('wallet.campaign.gift_allocated', [
                'campaign' => $campaign->name,
            ]);
        }

        return __('wallet.campaign.bonus_processed', [
            'campaign' => $campaign->name,
            'event'    => $data->trigger_event ?? 'system event',
        ]);
    }

    /**
     * Build transaction metadata
     *
     * @return array<string, mixed>
     */
    private function buildTransactionMetadata(WalletCampaign $campaign, TriggerCampaignAllocationData $data): array
    {
        return array_merge($data->metadata ?? [], [
            'campaign_name'     => $campaign->name,
            'campaign_type'     => $campaign->type,
            'trigger_type'      => $data->trigger_type,
            'trigger_event'     => $data->trigger_event,
            'allocation_reason' => $data->reason,
        ]);
    }

    private function generateIdempotencyKey(WalletCampaign $campaign, User $user, TriggerCampaignAllocationData $data): string
    {
        return sprintf(
            'wallet-campaign:%d:user:%d:trigger:%s:event:%s',
            $campaign->id,
            $user->id,
            $data->trigger_type,
            $data->trigger_event ?? 'manual'
        );
    }
}
