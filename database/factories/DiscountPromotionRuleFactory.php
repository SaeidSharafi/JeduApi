<?php

namespace Database\Factories;

use App\Models\DiscountPromotion;
use DiscountPromotionRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @mixin Factory<DiscountPromotionRule> */
class DiscountPromotionRuleFactory extends Factory
{
    protected $model = DiscountPromotionRule::class;

    public function definition(): array
    {
        return [
            'discount_promotion_id' => DiscountPromotion::factory(),
            'type' => 'action',
            'handler' => 'apply_percentage_off',
            'configuration' => ['percentage' => 10], // Default 10% discount
        ];
    }

    /**
     * STATE: Configures the rule as a "Cart Value Over" condition.
     *
     * @param int $value The value the cart must be over (in cents).
     * @param string $operator The comparison operator (e.g., '>=', '>').
     * @param bool $includePrepayments Whether to include the full value of prepayment items.
     * @return Factory
     */
    public function asCartValueCondition(int $value = 10000, string $operator = '>=', bool $includePrepayments = false): Factory
    {
        return $this->state(function (array $attributes) use ($value, $operator, $includePrepayments) {
            return [
                'type' => 'condition',
                'handler' => 'cart_value_over',
                'configuration' => [
                    'value' => $value,
                    'operator' => $operator,
                    'include_prepayments' => $includePrepayments,
                ],
            ];
        });
    }

    /**
     * STATE: Configures the rule as a "Product In Category" condition.
     *
     * @param array $categoryIds The array of category IDs to check for.
     * @param string $matchPolicy 'any' or 'all'.
     * @return Factory
     */
    public function asProductCategoryCondition(array $categoryIds, string $matchPolicy = 'any'): Factory
    {
        return $this->state(function (array $attributes) use ($categoryIds, $matchPolicy) {
            return [
                'type' => 'condition',
                'handler' => 'product_in_category',
                'configuration' => [
                    'category_ids' => $categoryIds,
                    'match_policy' => $matchPolicy,
                ],
            ];
        });
    }

    /**
     * STATE: Configures the rule as a "Percentage Off" action.
     *
     * @param int $percentage The percentage to discount (e.g., 15 for 15%).
     * @return Factory
     */
    public function asPercentageDiscountAction(int $percentage = 15): Factory
    {
        return $this->state(function (array $attributes) use ($percentage) {
            return [
                'type' => 'action',
                'handler' => 'apply_percentage_off',
                'configuration' => ['percentage' => $percentage],
            ];
        });
    }
}
