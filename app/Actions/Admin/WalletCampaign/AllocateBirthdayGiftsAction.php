<?php

declare(strict_types=1);

namespace App\Actions\Admin\WalletCampaign;

use App\Data\Admin\WalletCampaign\TriggerCampaignAllocationData;
use App\Enums\Wallet\TransactionSourceEnum;
use App\Enums\Wallet\TransactionTypeEnum;
use App\Enums\WalletCampaign\CampaignTypeEnum;
use App\Exceptions\CustomValidationException;
use App\Exceptions\Wallet\WalletInsufficientBalanceException;
use App\Exceptions\Wallet\WalletNotFoundException;
use App\Exceptions\Wallet\WalletUserNotFoundException;
use App\Models\User;
use App\Models\WalletCampaign;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\Log;

/**
 * Daily sweep that allocates every active birthday_gift campaign to customers
 * whose birthday is today. Allocation goes through the shared
 * TriggerCampaignAllocationAction, so campaign activity, date window, and
 * per-user/total limits are honored, and the deterministic idempotency key
 * plus the birthday_sweep trigger-event dedupe make re-runs safe.
 */
final readonly class AllocateBirthdayGiftsAction
{
    public function __construct(
        private TriggerCampaignAllocationAction $allocationAction
    ) {}

    /**
     * @return array{allocated: int, skipped: int}
     */
    public function execute(bool $dryRun = false): array
    {
        $campaigns = WalletCampaign::query()
            ->activeOfType(CampaignTypeEnum::BIRTHDAY_GIFT)
            ->get();

        if ($campaigns->isEmpty()) {
            return ['allocated' => 0, 'skipped' => 0];
        }

        $users = User::query()
            ->with('wallet')
            ->whereNotNull('date_of_birth')
            ->whereMonth('date_of_birth', now()->month)
            ->whereDay('date_of_birth', now()->day)
            ->get();

        $allocated = 0;
        $skipped   = 0;

        foreach ($users as $user) {
            foreach ($campaigns as $campaign) {
                // Already allocated by an earlier sweep (or an overlapping run) —
                // never credit the same customer twice from the same campaign.
                if ($this->alreadyAllocated($user, $campaign)) {
                    continue;
                }

                if ($dryRun) {
                    if ($campaign->allocationStatus($user)->isError()) {
                        $skipped++;

                        continue;
                    }

                    $allocated++;

                    continue;
                }

                try {
                    $this->allocationAction->handle(
                        data: new TriggerCampaignAllocationData(
                            trigger_type: 'event',
                            trigger_event: 'birthday_sweep',
                            metadata: ['birthday_sweep_at' => now()->toISOString()],
                        ),
                        user: $user,
                        campaign: $campaign,
                    );

                    $allocated++;
                } catch (CustomValidationException|WalletUserNotFoundException|WalletNotFoundException|WalletInsufficientBalanceException) {
                    // Campaign no longer eligible (inactive, expired, limits
                    // reached) or the user has no wallet — count and move on.
                    Log::warning('Skipped birthday gift allocation', [
                        'user_id'     => $user->id,
                        'campaign_id' => $campaign->id,
                        'campaign'    => $campaign->name,
                    ]);

                    $skipped++;
                }
            }
        }

        return ['allocated' => $allocated, 'skipped' => $skipped];
    }

    /**
     * Has this customer already received this campaign's birthday gift?
     * Mirrors the allocation action's duplicate-trigger-event check so the
     * sweep can count a re-run without reaching the ledger.
     */
    private function alreadyAllocated(User $user, WalletCampaign $campaign): bool
    {
        return WalletTransaction::query()
            ->where('user_id', $user->id)
            ->where('source_type', TransactionSourceEnum::CAMPAIGN)
            ->where('source_id', $campaign->id)
            ->where('type', TransactionTypeEnum::GIFT)
            ->whereJsonContains('metadata->trigger_event', 'birthday_sweep')
            ->exists();
    }
}
