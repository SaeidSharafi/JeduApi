<?php

declare(strict_types=1);

namespace App\Enums\WalletCampaign;

use App\Traits\AdvanceEnum;

enum AllocationStatusEnum: string
{
    /** @use AdvanceEnum<value-of<self>> */
    use AdvanceEnum;

    case ERROR_INACTIVE            = 'error_inactive';
    case ERROR_EXPIRED             = 'error_expired';
    case ERROR_TOTAL_LIMIT_REACHED = 'error_total_limit_reached';
    case ERROR_USER_LIMIT_REACHED  = 'error_user_limit_reached';
    case ELIGIBLE                  = 'eligible';

    public function isError(): bool
    {
        return match ($this) {
            self::ERROR_INACTIVE,
            self::ERROR_EXPIRED,
            self::ERROR_TOTAL_LIMIT_REACHED,
            self::ERROR_USER_LIMIT_REACHED => true,
            default                        => false,
        };
    }

    public function message(): ?string
    {
        return match ($this) {
            self::ELIGIBLE                  => null,
            self::ERROR_INACTIVE            => __('validation.custom.campaign_not_active'),
            self::ERROR_EXPIRED             => __('validation.custom.campaign_expired'),
            self::ERROR_TOTAL_LIMIT_REACHED => __('validation.custom.usage_limit_reached'),
            self::ERROR_USER_LIMIT_REACHED  => __('validation.custom.already_claimed'),
        };
    }
}
