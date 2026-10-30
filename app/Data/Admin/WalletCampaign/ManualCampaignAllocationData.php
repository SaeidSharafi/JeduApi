<?php

declare(strict_types=1);

namespace App\Data\Admin\WalletCampaign;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class ManualCampaignAllocationData extends Data
{
    /**
     * @param  array<int, int>  $user_ids
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public array $user_ids,
        public string $reason,
        public ?array $metadata = null
    ) {}

    public static function rules(?ValidationContext $context = null): array
    {
        return [
            'user_ids'   => ['required', 'array', 'min:1', 'max:100'],
            'user_ids.*' => ['required', 'integer', 'exists:users,id'],
            'reason'     => ['required', 'string', 'max:255'],
            'metadata'   => ['nullable', 'array'],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     *
     * @codeCoverageIgnore
     */
    public function bodyParameters(): array
    {
        return [
            'user_ids' => [
                'description' => 'Customers receiving the manual allocation (1..100).',
                'example'     => [1, 2, 3],
            ],
            'user_ids.*' => [
                'description' => 'Customer ID for the allocation.',
                'example'     => 1,
            ],
            'reason' => [
                'description' => 'Why the grant is made. Stored as the ledger description and allocation reason.',
                'example'     => 'Support ticket #1234 — missed registration bonus.',
            ],
            'metadata' => [
                'description' => 'Additional metadata stored on every allocation.',
                'example'     => ['source' => 'admin_panel'],
            ],
        ];
    }
}
