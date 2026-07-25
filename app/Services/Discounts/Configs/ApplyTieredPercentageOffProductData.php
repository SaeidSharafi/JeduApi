<?php

declare(strict_types=1);

namespace App\Services\Discounts\Configs;

use Spatie\LaravelData\Data;

final class ApplyTieredPercentageOffProductData extends Data
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
            'tiers'              => ['required', 'array'],
            'tiers.*.min_amount' => ['required', 'integer', 'min:0'],
            'tiers.*.percentage' => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }

    /**
     * Extra frontend metadata for specific fields.
     *
     * @return array<string, array<string, mixed>>
     *
     * @codeCoverageIgnore
     */
    public static function fieldMeta(): array
    {
        return [
            'tiers' => [
                'item' => [
                    'item_type'  => 'data',
                    'item_class' => TierData::class,
                ],
            ],
        ];
    }
}
