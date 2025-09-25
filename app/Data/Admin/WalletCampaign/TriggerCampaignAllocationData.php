<?php

declare(strict_types=1);

namespace App\Data\Admin\WalletCampaign;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class TriggerCampaignAllocationData extends Data
{
    public function __construct(
        public string $trigger_type, // 'manual' or 'event'
        public ?string $trigger_event,
        public ?string $reason = null, // reason for manual triggers
        public ?array $metadata = null
    ) {}

    public static function rules(?ValidationContext $context = null): array
    {
        return [
            'trigger_type'  => ['required', 'string', 'in:manual,event'],
            'trigger_event' => ['nullable', 'string', 'max:100', 'required_if:trigger_type,event'],
            'reason'        => ['nullable', 'string', 'max:255'],
            'metadata'      => ['nullable', 'array'],
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
            'user_id'       => 'ID of the user receiving the campaign allocation.',
            'trigger_type'  => 'Type of trigger: manual (admin-initiated) or event (system-initiated).',
            'trigger_event' => 'Specific event name that triggered this allocation (required for event triggers).',
            'reason'        => 'Optional reason for manual allocations.',
            'metadata'      => 'Additional metadata for the allocation.',
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
            'trigger_type' => [
                'description' => 'Type of trigger: manual (admin-initiated) or event (system-initiated).',
                'example'     => 'manual',
            ],
            'trigger_event' => [
                'description' => 'Specific event name for event-based triggers.',
                'example'     => 'user_registration',
            ],
            'reason' => [
                'description' => 'Reason for manual triggers.',
                'example'     => 'Admin initiated bonus.',
            ],
            'metadata' => [
                'description' => 'Additional metadata for the allocation.',
                'example'     => ['source' => 'admin_panel'],
            ],
        ];
    }
}
