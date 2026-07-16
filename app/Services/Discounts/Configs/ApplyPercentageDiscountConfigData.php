<?php

declare(strict_types=1);

namespace App\Services\Discounts\Configs;

use Spatie\LaravelData\Data;

final class ApplyPercentageDiscountConfigData extends Data
{
    public function __construct(
        public int $percentage, // e.g., 15 for 15%
    ) {}

    /**
     * @return array<string, mixed>
     *
     * @codeCoverageIgnore
     */
    public static function rules(): array
    {
        return [
            'percentage' => ['required', 'integer', 'min:1', 'max:100'],
        ];
    }
}
