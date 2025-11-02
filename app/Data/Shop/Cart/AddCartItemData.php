<?php

declare(strict_types=1);

namespace App\Data\Shop\Cart;

use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class AddCartItemData extends Data
{
    public function __construct(
        public string $product_delivery_option_uuid,
        public int $quantity,
    ) {}

    public static function rules(?ValidationContext $context = null): array
    {
        return [
            'product_delivery_option_uuid' => [
                'required',
                'string',
                'uuid',
                Rule::exists('product_delivery_options', 'uuid'),
            ],
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
            'product_delivery_option_uuid' => [
                'description' => 'The UUID of the product delivery option to add to cart',
                'example'     => '01932e8f-4c3d-7b4e-9f3a-8c5e2d1b4a6f',
            ],
            'quantity' => [
                'description' => 'The quantity to add',
                'example'     => 1,
            ],
        ];
    }
}
