<?php

declare(strict_types=1);

namespace App\Services\Discounts\Cart\Actions;

use App\Attributes\DiscountHandlerKey;
use App\Contracts\Discounts\Cart\LineMarkdownActionContract;
use App\Contracts\Discounts\DiscountActionContract;
use App\Data\Admin\Discounts\CalculatedOrderItemData;
use App\Data\Admin\Discounts\OrderContextData;
use App\Services\Discounts\Configs\ApplyFixedAmountOffData;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;

#[DiscountHandlerKey('apply_fixed_amount_off')]
final class ApplyFixedAmountOffAction implements DiscountActionContract, LineMarkdownActionContract
{
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

        $shares = $this->apportion($items, $target, $totalWeight);

        foreach ($items as $index => $item) {
            $item->applyDiscount($shares[$index]);
        }
    }

    /**
     * Largest-remainder apportionment with integer arithmetic only: every line
     * gets `intdiv(target * weight, totalWeight)`, then the leftover units are
     * handed out one each to the lines with the largest remainder, so the
     * shares sum to the target exactly. Ties go to the earliest line in
     * collection order, and a line can never receive more than its own total.
     *
     * @param  Collection<int, CalculatedOrderItemData>  $items
     * @return array<int, int>
     */
    private function apportion(Collection $items, int $target, int $totalWeight): array
    {
        $shares     = [];
        $remainders = [];
        $floorSum   = 0;

        foreach ($items as $index => $item) {
            $scaled = $target * $item->total;

            $shares[$index]     = intdiv($scaled, $totalWeight);
            $remainders[$index] = $scaled % $totalWeight;
            $floorSum += $shares[$index];
        }

        $leftover = $target - $floorSum;

        if ($leftover > 0) {
            $candidates = array_keys(array_filter(
                $remainders,
                fn (int $remainder, int $index): bool => $remainder > 0 && $items[$index]->total > 0,
                ARRAY_FILTER_USE_BOTH,
            ));

            usort($candidates, fn (int $first, int $second): int => ($remainders[$second] <=> $remainders[$first]) ?: ($first <=> $second));

            foreach (array_slice($candidates, 0, $leftover) as $index) {
                $shares[$index]++;
            }
        }

        return $shares;
    }
}
