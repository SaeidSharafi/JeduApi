<?php

declare(strict_types=1);

namespace App\Actions\Admin\WalletCampaign;

use App\Actions\Wallet\RecordWalletTransactionAction;
use App\Data\Admin\Wallet\RecordTransactionData;
use App\Data\Admin\WalletCampaign\TriggerCampaignAllocationData;
use App\Enums\Wallet\TransactionSourceEnum;
use App\Enums\Wallet\TransactionTypeEnum;
use App\Enums\WalletCampaign\CampaignTriggerTypeEnum;
use App\Events\Wallet\WalletCampaignAllocationTriggeredEvent;
use App\Exceptions\CustomValidationException;
use App\Exceptions\Wallet\WalletInsufficientBalanceException;
use App\Exceptions\Wallet\WalletNotFoundException;
use App\Exceptions\Wallet\WalletUserNotFoundException;
use App\Models\User;
use App\Models\WalletCampaign;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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

            // Replays return the original allocation before any eligibility
            // check: a retried request must not fail because the campaign has
            // since expired or its limits were reached by the original grant.
            $idempotencyKey = $this->resolveIdempotencyKey($campaign, $user, $data);

            $existingByIdempotencyKey = WalletTransaction::query()
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existingByIdempotencyKey) {
                return $existingByIdempotencyKey;
            }

            // Event triggers are replayed by their business identity; manual
            // grants repeat and rely on a request-scoped idempotency key.
            $existingTransaction = $this->checkForDuplicateEventAllocation($user, $campaign, $data);
            if ($existingTransaction) {
                return $existingTransaction;
            }

            // Check campaign allocation eligibility
            $status = $campaign->allocationStatus($user, $data->trigger_occurrence);
            if ($status->isError()) {
                throw new CustomValidationException($status->message());
            }

            // Create transaction description
            $description = $this->buildTransactionDescription($campaign, $data);

            $transactionData = new RecordTransactionData(
                user_id: $user->id,
                type: TransactionTypeEnum::GIFT, // Simplified: use GIFT for all campaign allocations
                amount: $campaign->amount,
                source_type: TransactionSourceEnum::CAMPAIGN,
                source_id: $campaign->id,
                description: $description,
                metadata: $this->buildTransactionMetadata($campaign, $data),
                expires_at: $this->resolveExpiryDeadline($campaign),
                idempotency_key: $idempotencyKey,
            );

            $transaction = $this->recordWalletTransactionAction->execute($transactionData);

            // Update campaign usage
            $campaign->incrementUsageCount();

            // Dispatch unified event
            WalletCampaignAllocationTriggeredEvent::dispatch($transaction, $campaign, $user, $data->trigger_type->value);

            return $transaction;
        });
    }

    /**
     * An event occurrence is allocated at most once per user and campaign.
     * Manual grants are repeatable and never dedupe here.
     */
    private function checkForDuplicateEventAllocation(User $user, WalletCampaign $campaign, TriggerCampaignAllocationData $data): ?WalletTransaction
    {
        if ($data->trigger_type !== CampaignTriggerTypeEnum::EVENT || $data->trigger_event === null) {
            return null;
        }

        $query = WalletTransaction::where([
            'user_id'     => $user->id,
            'source_type' => TransactionSourceEnum::CAMPAIGN,
            'source_id'   => $campaign->id,
            'type'        => TransactionTypeEnum::GIFT,
        ])->whereJsonContains('metadata->trigger_event', $data->trigger_event->value);

        if ($data->trigger_occurrence !== null) {
            $query->whereJsonContains('metadata->occurrence', $data->trigger_occurrence);
        }

        return $query->first();
    }

    /**
     * Build transaction description based on trigger type
     */
    private function buildTransactionDescription(WalletCampaign $campaign, TriggerCampaignAllocationData $data): string
    {
        if ($data->trigger_type === CampaignTriggerTypeEnum::MANUAL) {
            return $data->reason ?? __('wallet.campaign.gift_allocated', [
                'campaign' => $campaign->name,
            ]);
        }

        return __('wallet.campaign.bonus_processed', [
            'campaign' => $campaign->name,
            'event'    => $data->trigger_event?->value ?? 'system event',
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
            'campaign_type'     => $campaign->type->value,
            'trigger_type'      => $data->trigger_type->value,
            'trigger_event'     => $data->trigger_event?->value,
            'occurrence'        => $data->trigger_occurrence,
            'allocation_reason' => $data->reason,
        ]);
    }

    /**
     * A supplied key wins (manual grants); event triggers fall back to their
     * deterministic business identity; a keyless manual grant gets a random
     * key so it is never mistaken for an earlier grant.
     */
    private function resolveIdempotencyKey(WalletCampaign $campaign, User $user, TriggerCampaignAllocationData $data): string
    {
        if ($data->idempotency_key !== null) {
            return $data->idempotency_key;
        }

        if ($data->trigger_type === CampaignTriggerTypeEnum::EVENT) {
            return $this->generateBusinessKey($campaign, $user, $data);
        }

        return (string) Str::uuid();
    }

    private function generateBusinessKey(WalletCampaign $campaign, User $user, TriggerCampaignAllocationData $data): string
    {
        return sprintf(
            'wallet-campaign:%d:user:%d:trigger:%s:event:%s:occurrence:%s',
            $campaign->id,
            $user->id,
            $data->trigger_type->value,
            $data->trigger_event?->value ?? 'none',
            $data->trigger_occurrence    ?? 'none',
        );
    }

    /**
     * Resolve the gift expiry deadline from the campaign configuration.
     * Relative config (metadata.expiry_days, days from receipt) wins over
     * the absolute campaign ends_at; no config means the gift never expires.
     *
     * @return string|null formatted 'Y-m-d H:i:s' or null
     */
    private function resolveExpiryDeadline(WalletCampaign $campaign): ?string
    {
        $expiryDays = $campaign->metadata['expiry_days'] ?? null;

        if (is_numeric($expiryDays) && (int) $expiryDays > 0) {
            return now()->addDays((int) $expiryDays)->format('Y-m-d H:i:s');
        }

        if ($campaign->ends_at) {
            return $campaign->ends_at->format('Y-m-d H:i:s');
        }

        return null;
    }
}
