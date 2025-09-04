<?php

declare(strict_types=1);

namespace App\Data\Admin\Wallet;

use Carbon\CarbonInterface;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;
use Illuminate\Validation\Rule;
use App\Enums\Wallet\CampaignTypeEnum;

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
        public ?string $starts_at,
        public ?string $ends_at,
        public ?array $metadata
    ) {}

    public static function rules(ValidationContext $context): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'type' => ['required', 'string', Rule::enum(CampaignTypeEnum::class)],
            'is_active' => ['required', 'boolean'],
            'amount' => ['required', 'integer', 'min:1'],
            'usage_limit_total' => ['nullable', 'integer', 'min:1'],
            'usage_limit_per_user' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date', 'after_or_equal:now'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    /**
     * @return array<string, string>
     * @codeCoverageIgnore
     */
    public static function descriptions(): array
    {
        return [
            'name' => 'Campaign name for admin reference.',
            'description' => 'Detailed description of the campaign purpose and terms.',
            'type' => 'Type of campaign (registration_bonus, birthday_gift, etc.).',
            'is_active' => 'Whether the campaign is currently active.',
            'amount' => 'Gift amount in rials to be awarded.',
            'usage_limit_total' => 'Total number of times this campaign can be used (null for unlimited).',
            'usage_limit_per_user' => 'Number of times each user can use this campaign (null for unlimited).',
            'starts_at' => 'Campaign start date and time.',
            'ends_at' => 'Campaign end date and time.',
            'metadata' => 'Additional configuration data for the campaign.',
        ];
    }
}
