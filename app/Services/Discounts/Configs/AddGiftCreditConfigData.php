<?php

declare(strict_types=1);

namespace App\Services\Discounts\Configs;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class AddGiftCreditConfigData extends Data
{
    public function __construct(
        public int $amount,
        public bool $per_item = false,
        public ?int $expires_days = null,
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
            'amount'       => ['required', 'integer', 'min:0'],
            'per_item'     => ['boolean'],
            'expires_days' => ['nullable', 'integer', 'min:1'],
            'description'  => ['nullable', 'string', 'max:255'],
        ];
    }
}
