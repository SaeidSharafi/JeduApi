<?php

declare(strict_types=1);

namespace App\Services\Discounts\Configs;

use Spatie\LaravelData\Data;

/**
 * Exists only because {@see \App\Contracts\Discounts\DiscountActionContract::getConfigClass()}
 * requires a config class: the tier ladder lives on
 * {@see \App\Services\Discounts\Cart\Conditions\CartValueTierCondition}, so the
 * action is deliberately configuration-free.
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
