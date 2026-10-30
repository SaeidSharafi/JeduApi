<?php

declare(strict_types=1);

namespace App\Enums\WalletCampaign;

use App\Traits\AdvanceEnum;

enum CampaignTypeEnum: string
{
    /** @use AdvanceEnum<value-of<self>> */
    use AdvanceEnum;

    case REGISTRATION_BONUS = 'registration_bonus';
    case BIRTHDAY_GIFT      = 'birthday_gift';
    case REFERRAL_BONUS     = 'referral_bonus';
    case LOYALTY_REWARD     = 'loyalty_reward';
    case SEASONAL_BONUS     = 'seasonal_bonus';
    case MILESTONE_REWARD   = 'milestone_reward';
    case MANUAL_ALLOCATION  = 'manual_allocation';

    /**
     * @return array<int, string>
     */
    public static function creatableValues(): array
    {
        return array_values(array_map(
            static fn (self $type): string => $type->value,
            array_filter(self::cases(), static fn (self $type): bool => $type->isCreatable()),
        ));
    }

    /**
     * Whether the admin API may create campaigns of this type. Reserved types
     * stay in the enum so historical rows still resolve and label correctly.
     */
    public function isCreatable(): bool
    {
        return ! in_array($this, [self::REFERRAL_BONUS, self::SEASONAL_BONUS], true);
    }

    /**
     * @codeCoverageIgnore
     */
    public function getDescription(): string
    {
        return match ($this) {
            self::REGISTRATION_BONUS => __('wallet.campaign.descriptions.registration_bonus'),
            self::BIRTHDAY_GIFT      => __('wallet.campaign.descriptions.birthday_gift'),
            self::REFERRAL_BONUS     => __('wallet.campaign.descriptions.referral_bonus'),
            self::LOYALTY_REWARD     => __('wallet.campaign.descriptions.loyalty_reward'),
            self::SEASONAL_BONUS     => __('wallet.campaign.descriptions.seasonal_bonus'),
            self::MILESTONE_REWARD   => __('wallet.campaign.descriptions.milestone_reward'),
            self::MANUAL_ALLOCATION  => __('wallet.campaign.descriptions.manual_allocation'),
        };
    }
}
