<?php

declare(strict_types=1);

namespace App\Data\Admin\Wallet;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class ProcessCampaignBonusData extends Data
{
    public function __construct(
        public int $user_id,
        public ?string $trigger_event = null,
        public ?array $metadata = null
    ) {}

    public static function rules(ValidationContext $context): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'trigger_event' => ['nullable', 'string', 'max:100'],
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
            'campaign_id' => 'ID of the wallet campaign to process bonus from.',
            'user_id' => 'ID of the user receiving the bonus.',
            'trigger_event' => 'Event that triggered this bonus (e.g., registration, birthday).',
            'metadata' => 'Additional context data for the bonus processing.',
        ];
    }
}
