<?php

namespace App\Services\Discounts\Configs;

use Spatie\LaravelData\Data;

class GiftProductData extends Data
{
    public function __construct(
        public int $product_delivery_option_id
    ) {}

    /**
     * @return array<string, mixed>
     *
     * @codeCoverageIgnore
     */
    public static function rules(): array
    {
        return [
            'product_delivery_option_id' => ['required', 'integer', 'exists:product_delivery_options,id'],
        ];
    }
}
