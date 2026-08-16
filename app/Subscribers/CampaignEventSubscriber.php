<?php

declare(strict_types=1);

namespace App\Subscribers;

use App\Actions\Admin\WalletCampaign\TriggerCampaignAllocationAction;
use App\Data\Admin\WalletCampaign\TriggerCampaignAllocationData;
use App\Enums\WalletCampaign\CampaignTypeEnum;
use App\Events\ProfileCompletedEvent;
use App\Exceptions\CustomValidationException;
use App\Exceptions\Wallet\WalletInsufficientBalanceException;
use App\Exceptions\Wallet\WalletNotFoundException;
use App\Exceptions\Wallet\WalletUserNotFoundException;
use App\Models\WalletCampaign;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Log;

/**
 * Maps domain events to active wallet campaigns and allocates through the
 * existing allocation action. Registered explicitly via Event::subscribe —
 * the codebase's first subscriber precedent (everything else is
 * auto-discovered one-listener-per-event).
 */
final readonly class CampaignEventSubscriber
{
    public function __construct(
        private TriggerCampaignAllocationAction $allocationAction
    ) {}

    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            ProfileCompletedEvent::class => 'handleProfileCompleted',
        ];
    }

    public function handleProfileCompleted(ProfileCompletedEvent $event): void
    {
        $campaigns = WalletCampaign::query()->activeOfType(CampaignTypeEnum::REGISTRATION_BONUS)->get();

        foreach ($campaigns as $campaign) {
            $this->allocate($event, $campaign);
        }
    }

    private function allocate(ProfileCompletedEvent $event, WalletCampaign $campaign): void
    {
        try {
            $this->allocationAction->handle(
                data: new TriggerCampaignAllocationData(
                    trigger_type: 'event',
                    trigger_event: 'profile_completed',
                    metadata: ['profile_completed_at' => now()->toISOString()],
                ),
                user: $event->user,
                campaign: $campaign,
            );
        } catch (CustomValidationException|WalletUserNotFoundException|WalletNotFoundException|WalletInsufficientBalanceException) {
            // Campaign no longer eligible (inactive, expired, limits reached)
            // or the user has no wallet — log and skip, never break the event flow.
            Log::warning('Skipped campaign allocation for profile-completed event', [
                'user_id'     => $event->user->id,
                'campaign_id' => $campaign->id,
                'campaign'    => $campaign->name,
            ]);
        }
    }
}
