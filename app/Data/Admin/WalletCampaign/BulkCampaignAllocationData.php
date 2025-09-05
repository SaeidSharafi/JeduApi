<?php

declare(strict_types=1);

namespace App\Data\Admin\WalletCampaign;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class BulkCampaignAllocationData extends Data
{
    public function __construct(
        public array $user_ids,
        public string $trigger_type, // 'manual' or 'event'
        public ?string $trigger_event, // specific event name for event-based triggers
        public ?string $reason = null, // reason for manual triggers
        public ?array $metadata = null
    ) {
    }

    public static function rules(ValidationContext $context): array
    {
        return [
            'user_ids'      => ['required', 'array', 'min:1', 'max:100'], // Limit bulk operations
            'user_ids.*'    => ['required', 'integer', 'exists:users,id'],
            'trigger_type'  => ['required', 'string', 'in:manual,event'],
            'trigger_event' => ['nullable', 'string', 'max:100', 'required_if:trigger_type,event'],
            'reason'        => ['nullable', 'string', 'max:255'],
            'metadata'      => ['nullable', 'array'],
        ];
    }
}
