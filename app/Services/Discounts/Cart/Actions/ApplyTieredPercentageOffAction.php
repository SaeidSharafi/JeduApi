<?php

declare(strict_types=1);

namespace App\Services\Discounts\Cart\Actions;

use App\Attributes\DiscountHandlerKey;
use App\Contracts\Discounts\DiscountActionContract;
use App\Data\Admin\Discounts\OrderContextData;
use App\Services\Discounts\Configs\ApplyTieredPercentageOffData;
use Spatie\LaravelData\Data;

#[DiscountHandlerKey('apply_tiered_percentage_off')]
final class ApplyTieredPercentageOffAction implements DiscountActionContract
{
    public static function getConfigClass(): string
    {
        return ApplyTieredPercentageOffData::class;
    }

    public function apply(OrderContextData $context, Data $configuration): void
    {
        /** @var ApplyTieredPercentageOffData $configuration */
        $applicablePercentage = 0;

        foreach ($configuration->tiers as $tier) {
            if ($context->subtotal_all_items >= $tier->min_amount && $tier->percentage > $applicablePercentage) {
                $applicablePercentage = $tier->percentage;
            }
        }

        if ($applicablePercentage <= 0) {
            return;
        }

        foreach ($context->items as $item) {
            $itemDiscount = (int) ($item->total * ($applicablePercentage / 100));
            $itemDiscount = min($itemDiscount, $item->total);

            $item->discount_amount += $itemDiscount;
            $item->total -= $itemDiscount;
        }
    }
}
