<?php

declare(strict_types=1);

namespace App\Data\Admin\Wallet;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class AllocateGiftCreditData extends Data
{
    public function __construct(
        public int $user_id,
        public ?string $reason = null,
        public ?array $metadata = null
    ) {}

    public static function rules(ValidationContext $context): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'reason' => ['nullable', 'string', 'max:255'],
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
            'campaign_id' => 'ID of the wallet campaign to allocate from.',
            'user_id' => 'ID of the user receiving the gift credit.',
            'reason' => 'Optional reason for manual allocation.',
            'metadata' => 'Additional metadata for the allocation.',
        ];
    }
}
