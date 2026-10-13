<?php

declare(strict_types=1);

namespace App\Services\Discounts\Cart\Conditions;

use App\Attributes\DiscountHandlerKey;
use App\Contracts\Discounts\DiscountConditionContract;
use App\Data\Admin\Discounts\OrderContextData;
use App\Services\Discounts\Configs\CartValueTierConditionConfigData;
use Spatie\LaravelData\Data;

/**
 * Eligibility-only tier selection for a tiered cart campaign.
 *
 * The ladder is measured on {@see OrderContextData::discountableTotal()} — the
 * effective (product-discount-applied) worth of exactly the lines the consuming
 * action may mark down. This is deliberate:
 *  - the spec requires the ladder to be measured on the same price basis as
 *    other value conditions (effective prices, never base prices); and
 *  - the tier must be chosen from what is actually discountable, so the engine
 *    never promises a markdown it cannot apply.
 *
 * Deposit (prepayment) and bundle lines are therefore never counted, which is
 * why this condition has no `measure_whole_commitment` knob.
 *
 * It publishes the selected tier under its own handler key and never writes a
 * price, total or discount amount.
 */
#[DiscountHandlerKey(self::HANDLER_KEY)]
final class CartValueTierCondition implements DiscountConditionContract
{
    public const string HANDLER_KEY = 'cart_value_tier';

    public static function getConfigClass(): string
    {
        return CartValueTierConditionConfigData::class;
    }

    public function passes(OrderContextData $context, Data $configuration): bool
    {
        if (! $configuration instanceof CartValueTierConditionConfigData) {
            return false;
        }

        $selection = $this->selectTier($configuration, $context->discountableTotal());

        if ($selection === null) {
            return false;
        }

        $context->publishSelection(self::HANDLER_KEY, $selection);

        return true;
    }

    /**
     * The highest satisfied tier: the greatest percentage among the tiers whose
     * minimum amount the measured value reaches. Exactly one tier is returned,
     * so two satisfied rungs can never sum.
     *
     * @return array{min_amount: int, percentage: float}|null
     */
    private function selectTier(CartValueTierConditionConfigData $configuration, int $discountableValue): ?array
    {
        $selection = null;

        foreach ($configuration->tiers as $tier) {
            if ($discountableValue < $tier->min_amount) {
                continue;
            }

            if ($selection !== null && $tier->percentage <= $selection['percentage']) {
                continue;
            }

            $selection = [
                'min_amount' => (int) $tier->min_amount,
                'percentage' => (float) $tier->percentage,
            ];
        }

        return $selection;
    }
}
