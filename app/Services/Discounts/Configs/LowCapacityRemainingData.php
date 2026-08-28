<?php

declare(strict_types=1);

namespace App\Services\Discounts\Configs;

use Spatie\LaravelData\Data;

final class LowCapacityRemainingData extends Data
{
    public function __construct(
        public int $threshold
    ) {}

    /**
     * @return array<string, mixed>
     *
     * @codeCoverageIgnore
     */
    public static function rules(): array
    {
        return [
            'threshold' => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
