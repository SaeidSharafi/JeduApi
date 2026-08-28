<?php

declare(strict_types=1);

namespace App\Services\Discounts\Configs;

use Spatie\LaravelData\Data;

final class CartItemCountOverData extends Data
{
    public function __construct(
        public int $min_count,
        public bool $count_quantities = false // false = distinct items, true = sum of quantities
    ) {}

    /**
     * @return array<string, mixed>
     *
     * @codeCoverageIgnore
     */
    public static function rules(): array
    {
        return [
            'min_count'        => ['required', 'integer', 'min:0'],
            'count_quantities' => ['boolean'],
        ];
    }
}
