<?php

namespace App\Services\Discounts\Configs;

use Spatie\LaravelData\Data;

class ApplyFixedAmountOffData extends Data
{
    public function __construct(
        public int $amount
    ) {}

    /**
     * @return array<string, mixed>
     *
     * @codeCoverageIgnore
     */
    public static function rules(): array
    {
        return [
            'amount' => ['required', 'integer', 'min:0'],
        ];
    }
}
