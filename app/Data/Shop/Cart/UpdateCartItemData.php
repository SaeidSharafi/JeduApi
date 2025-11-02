<?php

declare(strict_types=1);

namespace App\Data\Shop\Cart;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class UpdateCartItemData extends Data
{
    public function __construct(
        public int $quantity,
    ) {}

    public static function rules(?ValidationContext $context = null): array
    {
        return [
            'quantity' => ['required', 'integer', 'min:1', 'max:100'],
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
            'quantity' => [
                'description' => 'The new quantity for the cart item',
                'example'     => 2,
            ],
        ];
    }
}
