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

        // HERE IS THE KEY LOGIC: Select the correct total based on the config flag
        $comparisonValue = $configuration->include_prepayments
            ? $context->subtotal_all_items
            : $context->subtotal_full_payment_items;

        // Use a match statement for a clean and safe comparison
        return match ($configuration->operator) {
            MathOperatorEnum::GREATER_THAN_OR_EQUAL => $comparisonValue >= $configuration->value,
            MathOperatorEnum::GREATER_THAN          => $comparisonValue > $configuration->value,
            MathOperatorEnum::LESS_THAN_OR_EQUAL    => $comparisonValue <= $configuration->value,
            MathOperatorEnum::LESS_THAN             => $comparisonValue < $configuration->value,
            MathOperatorEnum::EQUAL                 => $comparisonValue === $configuration->value,
        };
    }
}
