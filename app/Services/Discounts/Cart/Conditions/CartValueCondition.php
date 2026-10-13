<?php

declare(strict_types=1);

namespace App\Services\Discounts\Cart\Conditions;

use App\Attributes\DiscountHandlerKey;
use App\Contracts\Discounts\DiscountConditionContract;
use App\Data\Admin\Discounts\OrderContextData;
use App\Enums\Operators\MathOperatorEnum;
use App\Services\Discounts\Configs\CartValueConditionConfigData;
use Spatie\LaravelData\Data;

#[DiscountHandlerKey('cart_value_over')]
final class CartValueCondition implements DiscountConditionContract
{
    public static function getConfigClass(): string
    {
        return CartValueConditionConfigData::class;
    }

    public function passes(OrderContextData $context, Data $configuration): bool
    {
        if (! $configuration instanceof CartValueConditionConfigData) {
            return false;
        }

        // Both measurement options share one price basis; the flag only chooses
        // which lines count (whole commitment vs full payment only).
        $comparisonValue = $context->thresholdBasis($configuration->measure_whole_commitment);

        // Use a match statement for a clean and safe comparison
        return match ($configuration->operator) {
            MathOperatorEnum::GREATER_THAN_OR_EQUAL => $comparisonValue >= $configuration->value,
            MathOperatorEnum::GREATER_THAN          => $comparisonValue > $configuration->value,
            MathOperatorEnum::LESS_THAN_OR_EQUAL    => $comparisonValue <= $configuration->value,
            MathOperatorEnum::LESS_THAN             => $comparisonValue < $configuration->value,
            MathOperatorEnum::EQUAL                 => $comparisonValue === $configuration->value,
            MathOperatorEnum::NOT_EQUAL             => $comparisonValue !== $configuration->value,
        };
    }
}
