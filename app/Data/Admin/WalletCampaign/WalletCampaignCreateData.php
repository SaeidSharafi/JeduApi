<?php

declare(strict_types=1);

namespace App\Data\Admin\WalletCampaign;

use App\Enums\WalletCampaign\CampaignTypeEnum;
use App\Enums\WalletCampaign\ThresholdScopeEnum;
use App\Helpers\JalaliDateHelper;
use App\Rules\ValidNormalizedJalaliDateRule;
use Carbon\Carbon;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class WalletCampaignCreateData extends Data
{
    public function __construct(
        public string $name,
        public ?string $description,
        public string $type,
        public string $threshold_scope,
        public bool $is_active,
        public int $amount,
        public ?int $usage_limit_total,
        public ?int $usage_limit_per_user,
        #[WithCast(DateTimeInterfaceCast::class, 'Y-m-d')]
        public ?Carbon $starts_at,
        #[WithCast(DateTimeInterfaceCast::class, 'Y-m-d')]
        public ?Carbon $ends_at,
        public ?array $metadata
    ) {}

    public static function prepareForPipeline(array $properties): array
    {
        return JalaliDateHelper::toGregorian($properties, [
            'starts_at',
            'ends_at',
        ]);
    }

    public static function rules(?ValidationContext $context = null): array
    {
        $now            = now()->format('Y-m-d');
        $thresholdScope = $context?->payload['threshold_scope'] ?? null;
        $isWindowed     = $thresholdScope === ThresholdScopeEnum::WINDOWED->value;
        $isLifetime     = $thresholdScope === ThresholdScopeEnum::LIFETIME->value;

        return [
            'name'                 => ['required', 'string', 'max:255'],
            'description'          => ['nullable', 'string', 'max:1000'],
            'type'                 => ['required', 'string', Rule::enum(CampaignTypeEnum::class)],
            'threshold_scope'      => ['required', 'string', Rule::enum(ThresholdScopeEnum::class)],
            'is_active'            => ['required', 'boolean'],
            'amount'               => ['required', 'integer', 'min:1'],
            'usage_limit_total'    => ['nullable', 'integer', 'min:1'],
            'usage_limit_per_user' => ['nullable', 'integer', 'min:1'],
            'starts_at'            => [
                'bail',
                'nullable',
                Rule::requiredIf($isWindowed),
                Rule::prohibitedIf($isLifetime),
                new ValidNormalizedJalaliDateRule,
                'date_format:Y-m-d',
                'after_or_equal:'.$now,
            ],
            'ends_at' => [
                'bail',
                'nullable',
                Rule::requiredIf($isWindowed),
                Rule::prohibitedIf($isLifetime),
                new ValidNormalizedJalaliDateRule,
                'date_format:Y-m-d',
                'after:starts_at',
            ],
            'metadata'                       => ['nullable', 'array'],
            'metadata.threshold_amount'      => ['nullable', 'integer', 'min:1'],
            'metadata.threshold_order_count' => ['nullable', 'integer', 'min:1'],
            'metadata.expiry_days'           => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     *
     * @codeCoverageIgnore
     */
    public static function descriptions(): array
    {
        return [
            'name'                 => 'Campaign name for admin reference.',
            'description'          => 'Detailed description of the campaign purpose and terms.',
            'type'                 => 'Type of campaign. One of: registration_bonus, birthday_gift, referral_bonus, loyalty_reward, seasonal_bonus, milestone_reward, manual_allocation. Only loyalty_reward and milestone_reward use threshold_scope and the threshold metadata keys.',
            'threshold_scope'      => 'Threshold measurement scope. Only affects loyalty_reward and milestone_reward (ignored for other types). lifetime measures all order history and requires no dates; windowed measures orders within starts_at..ends_at and requires both dates.',
            'is_active'            => 'Whether the campaign is currently active.',
            'amount'               => 'Gift amount in rials to be awarded.',
            'usage_limit_total'    => 'Total number of times this campaign can be used (null for unlimited).',
            'usage_limit_per_user' => 'Number of times each user can use this campaign (null for unlimited).',
            'starts_at'            => 'Campaign start date (Jalali Y-m-d). Required when threshold_scope is windowed; must be omitted when threshold_scope is lifetime.',
            'ends_at'              => 'Campaign end date (Jalali Y-m-d). Required when threshold_scope is windowed; must be omitted when threshold_scope is lifetime. Must be after starts_at.',
            'metadata'             => 'Campaign-specific configuration. loyalty_reward requires threshold_amount (rials); milestone_reward requires threshold_order_count (paid order count); any gift may set expiry_days (relative expiry, days from receipt, overrides absolute ends_at).',
        ];
    }

    /**
     * @codeCoverageIgnore
     *
     * @return array<string, array<string, mixed>>
     */
    public function bodyParameters(): array
    {
        return [
            'name' => [
                'description' => 'Campaign name for admin reference.',
                'example'     => 'Spring Bonus',
            ],
            'description' => [
                'description' => 'Detailed description of the campaign purpose and terms.',
                'example'     => 'Special bonus for spring season.',
            ],
            'type' => [
                'description' => 'Type of campaign. One of: registration_bonus, birthday_gift, referral_bonus, loyalty_reward, seasonal_bonus, milestone_reward, manual_allocation. Only loyalty_reward and milestone_reward use threshold_scope and the threshold metadata keys.',
                'example'     => 'loyalty_reward',
            ],
            'threshold_scope' => [
                'description' => 'Threshold measurement scope. Only affects loyalty_reward and milestone_reward (ignored for other types). lifetime measures all order history and requires no dates (starts_at and ends_at must be omitted). windowed measures orders within starts_at..ends_at and requires both dates.',
                'example'     => 'windowed',
            ],
            'is_active' => [
                'description' => 'Whether the campaign is currently active.',
                'example'     => true,
            ],
            'amount' => [
                'description' => 'Gift amount in rials to be awarded.',
                'example'     => 50000,
            ],
            'usage_limit_total' => [
                'description' => 'Total number of times this campaign can be used (null for unlimited).',
                'example'     => 100,
            ],
            'usage_limit_per_user' => [
                'description' => 'Number of times each user can use this campaign (null for unlimited).',
                'example'     => 1,
            ],
            'starts_at' => [
                'description' => 'Campaign start date (Jalali Y-m-d). Required when threshold_scope is windowed; must be omitted when threshold_scope is lifetime.',
                'example'     => '1402-01-01',
            ],
            'ends_at' => [
                'description' => 'Campaign end date (Jalali Y-m-d). Required when threshold_scope is windowed; must be omitted when threshold_scope is lifetime. Must be after starts_at.',
                'example'     => '1402-01-30',
            ],
            'metadata' => [
                'description' => 'Campaign-specific configuration object. Only the keys listed below are used; unknown keys are stored but ignored.',
                'example'     => ['expiry_days' => 30],
            ],
            'metadata.expiry_days' => [
                'description' => 'Relative gift expiry in days from receipt. Applies to any gift-producing campaign type. When set, it overrides the absolute ends_at deadline.',
                'example'     => 30,
            ],
            'metadata.threshold_amount' => [
                'description' => 'For loyalty_reward only: the cumulative paid order total (in rials) a user must reach before the reward is granted.',
                'example'     => 5000000,
            ],
            'metadata.threshold_order_count' => [
                'description' => 'For milestone_reward only: the number of paid orders a user must reach before the reward is granted.',
                'example'     => 5,
            ],
        ];
    }
}
