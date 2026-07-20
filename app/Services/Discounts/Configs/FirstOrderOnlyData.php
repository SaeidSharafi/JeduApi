<?php

namespace App\Services\Discounts\Configs;

use Spatie\LaravelData\Data;

final class FirstOrderOnlyData extends Data
{
    /**
     * @return array<string, mixed>
     *
     * @codeCoverageIgnore
     */
    public static function rules(): array
    {
        return [];
    }
}
