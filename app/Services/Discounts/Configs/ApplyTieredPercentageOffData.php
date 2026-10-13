<?php

declare(strict_types=1);

namespace App\Services\Discounts\Configs;

use Spatie\LaravelData\Data;

/**
 * Config-free action: the tier ladder and its selection live on
 * {@see \App\Services\Discounts\Cart\Conditions\CartValueTierCondition}.
 */
final class ApplyTieredPercentageOffData extends Data
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
