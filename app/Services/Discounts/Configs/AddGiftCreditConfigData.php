<?php

declare(strict_types=1);

namespace App\Services\Discounts\Configs;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class AddGiftCreditConfigData extends Data
{
    public function __construct(
        public int $amount,              // Gift credit amount in rials
        public bool $per_item = false,  // Award per item vs fixed amount
        public ?int $expires_days = null, // Optional expiration in days
        public ?string $description = null
    ) {}

    /**
     * @return array<string, mixed>
     *
     * @codeCoverageIgnore
     */
    public static function rules(ValidationContext $context): array
    {
        return [
            'amount'       => ['required', 'integer', 'min:1'],
            'per_item'     => ['sometimes', 'boolean'],
            'expires_days' => ['nullable', 'integer', 'min:1'],
            'description'  => ['nullable', 'string', 'max:255'],
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
            'amount'       => 'The gift credit amount in rials to be awarded.',
            'per_item'     => 'Whether to award the amount per item (true) or as fixed amount (false).',
            'expires_days' => 'Number of days after which the gift credit expires (optional).',
            'description'  => 'Optional description for the gift credit transaction.',
        ];
    }
}
