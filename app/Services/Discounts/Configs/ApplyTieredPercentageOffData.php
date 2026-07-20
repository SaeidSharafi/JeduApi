<?php

namespace App\Services\Discounts\Configs;

use Spatie\LaravelData\Data;

class ApplyTieredPercentageOffData extends Data
{
    /** @var TierData[] */
    public function __construct(
        public array $tiers
    ) {}

    /**
     * @return array<string, mixed>
     *
     * @codeCoverageIgnore
     */
    public static function rules(): array
    {
        return [
            'tiers'                => ['required', 'array'],
            'tiers.*.min_amount'   => ['required', 'integer', 'min:0'],
            'tiers.*.percentage'   => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
