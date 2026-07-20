<?php

namespace App\Services\Discounts\Configs;

use Spatie\LaravelData\Data;

class SpecificProductsInCartData extends Data
{
    public function __construct(
        public array $product_ids
    ) {}

    /**
     * @return array<string, mixed>
     *
     * @codeCoverageIgnore
     */
    public static function rules(): array
    {
        return [
            'product_ids'   => ['required', 'array'],
            'product_ids.*' => ['integer', 'exists:products,id'],
        ];
    }
}
