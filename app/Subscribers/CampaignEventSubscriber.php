<?php

declare(strict_types=1);

namespace App\Subscribers;

use App\Actions\Admin\WalletCampaign\EvaluateThresholdRewardAction;
use App\Actions\Admin\WalletCampaign\TriggerCampaignAllocationAction;
use App\Data\Admin\WalletCampaign\TriggerCampaignAllocationData;
use App\Enums\Payment\PaymentPurposeEnum;
use App\Enums\WalletCampaign\CampaignTypeEnum;
use App\Events\PaymentCompletedEvent;
use App\Events\ProfileCompletedEvent;
use App\Exceptions\CustomValidationException;
use App\Exceptions\Wallet\WalletInsufficientBalanceException;
use App\Exceptions\Wallet\WalletNotFoundException;
use App\Exceptions\Wallet\WalletUserNotFoundException;
use App\Models\User;
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
        private TriggerCampaignAllocationAction $allocationAction,
        private EvaluateThresholdRewardAction $thresholdRewardAction
    ) {}

    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            ProfileCompletedEvent::class => 'handleProfileCompleted',
            PaymentCompletedEvent::class => 'handlePaymentCompleted',
        ];
    }

    public function handleProfileCompleted(ProfileCompletedEvent $event): void
    {
        $campaigns = WalletCampaign::query()->activeOfType(CampaignTypeEnum::REGISTRATION_BONUS)->get();

        foreach ($campaigns as $campaign) {
            $this->allocate($event->user, $campaign, 'profile_completed', [
                'profile_completed_at' => now()->toISOString(),
            ]);
        }
    }

    public function handlePaymentCompleted(PaymentCompletedEvent $event): void
    {
        $payment = $event->payment;

        // Only order payments move loyalty/milestone thresholds; wallet
        // top-ups are not purchases.
        if ($payment->purpose !== PaymentPurposeEnum::ORDER) {
            return;
        }

        $user = $payment->customer;

        if (! $user) {
            return;
        }

        foreach ([CampaignTypeEnum::LOYALTY_REWARD, CampaignTypeEnum::MILESTONE_REWARD] as $type) {
            $campaigns = WalletCampaign::query()->activeOfType($type)->get();

            foreach ($campaigns as $campaign) {
                $this->evaluateThreshold($user, $campaign);
            }
        }
    }

    private function evaluateThreshold(User $user, WalletCampaign $campaign): void
    {
        try {
            $this->thresholdRewardAction->handle($user, $campaign);
        } catch (CustomValidationException|WalletUserNotFoundException|WalletNotFoundException|WalletInsufficientBalanceException) {
            // Campaign no longer eligible (inactive, expired, limits reached)
            // or the user has no wallet — log and skip, never break the event flow.
            Log::warning('Skipped threshold campaign allocation for payment-completed event', [
                'user_id'     => $user->id,
                'campaign_id' => $campaign->id,
                'campaign'    => $campaign->name,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function allocate(User $user, WalletCampaign $campaign, string $triggerEvent, array $metadata): void
    {
        try {
            $this->allocationAction->handle(
                data: new TriggerCampaignAllocationData(
                    trigger_type: 'event',
                    trigger_event: $triggerEvent,
                    metadata: $metadata,
                ),
                user: $user,
                campaign: $campaign,
            );
        } catch (CustomValidationException|WalletUserNotFoundException|WalletNotFoundException|WalletInsufficientBalanceException) {
            // Campaign no longer eligible (inactive, expired, limits reached)
            // or the user has no wallet — log and skip, never break the event flow.
            Log::warning("Skipped campaign allocation for {$triggerEvent} event", [
                'user_id'     => $user->id,
                'campaign_id' => $campaign->id,
                'campaign'    => $campaign->name,
            ]);
        }
    }
}
