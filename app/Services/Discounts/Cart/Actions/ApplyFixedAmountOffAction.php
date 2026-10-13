<?php

declare(strict_types=1);

namespace App\Services\Discounts\Cart\Actions;

use App\Attributes\DiscountHandlerKey;
use App\Contracts\Discounts\Cart\LineMarkdownActionContract;
use App\Contracts\Discounts\DiscountActionContract;
use App\Data\Admin\Discounts\OrderContextData;
use App\Services\Discounts\Configs\ApplyFixedAmountOffData;
use App\Services\WeightedApportionment;
use Spatie\LaravelData\Data;

#[DiscountHandlerKey('apply_fixed_amount_off')]
final class ApplyFixedAmountOffAction implements DiscountActionContract, LineMarkdownActionContract
{
    public function __construct(
        private readonly WeightedApportionment $apportionment = new WeightedApportionment(),
    ) {}

    public static function getConfigClass(): string
    {
        return ApplyFixedAmountOffData::class;
    }

    public function apply(OrderContextData $context, Data $configuration): void
    {
        /** @var ApplyFixedAmountOffData $configuration */
        // Bundle and deposit lines never carry a markdown, so the flat amount is
        // distributed over the lines a cart promotion may discount at all.
        $items       = $context->discountableItems();
        $totalWeight = $context->discountableTotal();

        if ($totalWeight <= 0) {
            return;
        }

        $target = min($configuration->amount, $totalWeight);

        if ($target <= 0) {
            return;
        }

        $shares = $this->apportionment->distribute($target, $items->pluck('total')->all());

        foreach ($items as $index => $item) {
            $item->applyDiscount($shares[$index]);
        }
    }
}
