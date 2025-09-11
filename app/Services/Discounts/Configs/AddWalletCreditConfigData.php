<?php

declare(strict_types=1);

namespace App\Services\Discounts\Configs;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class AddWalletCreditConfigData extends Data
{
    public function __construct(
        public int $amount,              // Credit amount in rials
        public bool $per_item = false,  // Award per item vs fixed amount
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
            'amount'      => ['required', 'integer', 'min:1'],
            'per_item'    => ['sometimes', 'boolean'],
            'description' => ['nullable', 'string', 'max:255'],
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
            'amount'      => 'The wallet credit amount in rials to be awarded.',
            'per_item'    => 'Whether to award the amount per item (true) or as fixed amount (false).',
            'description' => 'Optional description for the wallet credit transaction.',
        ];
    }
}
