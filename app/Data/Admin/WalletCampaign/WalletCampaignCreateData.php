<?php

declare(strict_types=1);

namespace App\Data\Admin\WalletCampaign;

use App\Data\Transformer\CarbonFromJalaliString;
use App\Enums\WalletCampaign\CampaignTypeEnum;
use Carbon\Carbon;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class WalletCampaignCreateData extends Data
{
    public function __construct(
        public string $name,
        public ?string $description,
        public string $type,
        public bool $is_active,
        public int $amount,
        public ?int $usage_limit_total,
        public ?int $usage_limit_per_user,
        #[WithCast(CarbonFromJalaliString::class, 'Y-m-d')]
        public ?Carbon $starts_at,
        #[WithCast(CarbonFromJalaliString::class, 'Y-m-d')]
        public ?Carbon $ends_at,
        public ?array $metadata
    ) {}

    public static function rules(?ValidationContext $context = null): array
    {
        $now = verta()->format('Y-m-d');

        return [
            'name'                 => ['required', 'string', 'max:255'],
            'description'          => ['nullable', 'string', 'max:1000'],
            'type'                 => ['required', 'string', Rule::enum(CampaignTypeEnum::class)],
            'is_active'            => ['required', 'boolean'],
            'amount'               => ['required', 'integer', 'min:1'],
            'usage_limit_total'    => ['nullable', 'integer', 'min:1'],
            'usage_limit_per_user' => ['nullable', 'integer', 'min:1'],
            'starts_at'            => ['nullable', 'jdate:Y-m-d', 'jdate_after_equal:'.$now.',Y-m-d'],
            'ends_at'              => ['nullable', 'jdate:Y-m-d', 'jdate_after:'.request('starts_at').',Y-m-d'],
            'metadata'             => ['nullable', 'array'],
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
            'type'                 => 'Type of campaign (registration_bonus, birthday_gift, etc.).',
            'is_active'            => 'Whether the campaign is currently active.',
            'amount'               => 'Gift amount in rials to be awarded.',
            'usage_limit_total'    => 'Total number of times this campaign can be used (null for unlimited).',
            'usage_limit_per_user' => 'Number of times each user can use this campaign (null for unlimited).',
            'starts_at'            => 'Campaign start date and time.',
            'ends_at'              => 'Campaign end date and time.',
            'metadata'             => 'Additional configuration data for the campaign.',
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
                'description' => 'Type of campaign (registration_bonus, birthday_gift, etc.).',
                'example'     => 'registration_bonus',
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
                'description' => 'Campaign start date and time.',
                'example'     => '1402-01-01',
            ],
            'ends_at' => [
                'description' => 'Campaign end date and time.',
                'example'     => '1402-01-30',
            ],
            'metadata' => [
                'description' => 'Additional configuration data for the campaign.',
                'example'     => ['source' => 'admin_panel'],
            ],
        ];
    }
}
