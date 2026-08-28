<?php

declare(strict_types=1);

namespace App\Services\Discounts\Configs;

use Spatie\LaravelData\Data;

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
    public static function rules(): array
    {
        return [
            'amount'      => ['required', 'integer', 'min:0'],
            'per_item'    => ['boolean'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
