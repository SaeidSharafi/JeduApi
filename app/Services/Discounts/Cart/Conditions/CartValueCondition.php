<?php

declare(strict_types=1);

namespace App\Services\Discounts\Cart\Conditions;

use App\Attributes\DiscountHandler;
use App\Contracts\Discounts\DiscountConditionContract;
use App\Data\Admin\Discounts\OrderContextData;
use App\Enums\OperatorEnum;
use App\Services\Discounts\Configs\CartValueConditionConfigData;
use Spatie\LaravelData\Data;

#[DiscountHandler('cart_value_over', 'condition')]
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
            OperatorEnum::GREATER_THAN_OR_EQUAL => $comparisonValue >= $configuration->value,
            OperatorEnum::GREATER_THAN          => $comparisonValue > $configuration->value,
            OperatorEnum::LESS_THAN_OR_EQUAL    => $comparisonValue <= $configuration->value,
            OperatorEnum::LESS_THAN             => $comparisonValue < $configuration->value,
            OperatorEnum::EQUAL                 => $comparisonValue === $configuration->value,
        };
    }
}
