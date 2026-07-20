<?php

namespace App\Services\Discounts\Configs;

use Spatie\LaravelData\Data;

class ApplyFixedPriceProductData extends Data
{
    public function __construct(
        public int $fixed_price
    ) {}

    /**
     * @return array<string, mixed>
     *
     * @codeCoverageIgnore
     */
    public static function rules(): array
    {
        return [
            'fixed_price' => ['required', 'integer', 'min:0'],
        ];
    }
}
