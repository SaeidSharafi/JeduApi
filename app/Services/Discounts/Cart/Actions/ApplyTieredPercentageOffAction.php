<?php

declare(strict_types=1);

namespace App\Services\Discounts\Cart\Actions;

use App\Attributes\DiscountHandlerKey;
use App\Contracts\Discounts\Cart\LineMarkdownActionContract;
use App\Contracts\Discounts\DiscountActionContract;
use App\Data\Admin\Discounts\OrderContextData;
use App\Services\Discounts\Cart\Conditions\CartValueTierCondition;
use App\Services\Discounts\Configs\ApplyTieredPercentageOffData;
use App\Services\Discounts\Configs\TierData;
use Spatie\LaravelData\Data;

/**
 * Config-free consumer of the tier {@see CartValueTierCondition} selected.
 *
 * The ladder and the eligibility decision live on the condition; this action
 * only applies the published {@see TierData} percentage to the discountable
 * lines. When nothing was published, the selection is not a `TierData`, or it
 * carries no positive percentage, it does nothing at all rather than guessing a
 * percentage.
 */
#[DiscountHandlerKey(self::HANDLER_KEY)]
final class ApplyTieredPercentageOffAction implements DiscountActionContract, LineMarkdownActionContract
{
    public const string HANDLER_KEY = 'apply_tiered_percentage_off';

    public static function getConfigClass(): string
    {
        return ApplyTieredPercentageOffData::class;
    }

    public function apply(OrderContextData $context, Data $configuration): void
    {
        $selection = $context->publishedSelection(CartValueTierCondition::HANDLER_KEY);

        if (! $selection instanceof TierData || $selection->percentage <= 0) {
            return;
        }

        foreach ($context->discountableItems() as $item) {
            $item->applyDiscount((int) ($item->total * ($selection->percentage / 100)));
        }
    }
}
