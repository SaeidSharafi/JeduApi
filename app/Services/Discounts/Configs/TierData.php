<?php

namespace App\Services\Discounts\Configs;

use Spatie\LaravelData\Data;

class TierData extends Data
{
    public function __construct(
        public int $min_amount,
        public float $percentage
    ) {}

    /**
     * @return array<string, mixed>
     *
     * @codeCoverageIgnore
     */
    public static function rules(): array
    {
        return [
            'min_amount' => ['required', 'integer', 'min:0'],
            'percentage' => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
