<?php

declare(strict_types=1);

namespace App\Enums\WalletCampaign;

use App\Traits\AdvanceEnum;

enum ThresholdScopeEnum: string
{
    /** @use AdvanceEnum<value-of<self>> */
    use AdvanceEnum;

    case LIFETIME = 'lifetime';
    case WINDOWED = 'windowed';

    /**
     * @codeCoverageIgnore
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::LIFETIME => __('wallet.campaign.threshold_scopes.lifetime'),
            self::WINDOWED => __('wallet.campaign.threshold_scopes.windowed'),
        };
    }

    /**
     * @codeCoverageIgnore
     */
    public function getDescription(): string
    {
        return match ($this) {
            self::LIFETIME => __('wallet.campaign.threshold_descriptions.lifetime'),
            self::WINDOWED => __('wallet.campaign.threshold_descriptions.windowed'),
        };
    }
}
