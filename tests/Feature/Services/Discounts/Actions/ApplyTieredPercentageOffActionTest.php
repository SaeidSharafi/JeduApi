<?php

declare(strict_types=1);

use App\Data\Admin\Discounts\CalculatedOrderItemData;
use App\Data\Admin\Discounts\OrderContextData;
use App\Enums\Order\OrderItemPaymentTypeEnum;
use App\Models\ProductDeliveryOption;
use App\Models\User;
use App\Services\Discounts\Cart\Actions\ApplyTieredPercentageOffAction;
use App\Services\Discounts\Cart\Conditions\CartValueTierCondition;
use App\Services\Discounts\Configs\ApplyTieredPercentageOffData;
use App\Services\Discounts\Configs\TierData;

/**
 * @param  array<int, CalculatedOrderItemData>  $items
 */
function tieredActionContext(array $items, ?float $publishedPercentage = null): OrderContextData
{
    $context = OrderContextData::from([
        'customer'                    => User::factory()->make(),
        'items'                       => $items,
        'subtotal_full_payment_items' => collect($items)->sum('total'),
        'subtotal_all_items'          => collect($items)->sum('total'),
    ]);

    if ($publishedPercentage !== null) {
        $context->publishSelection(
            CartValueTierCondition::HANDLER_KEY,
            new TierData(min_amount: 3000000, percentage: $publishedPercentage),
        );
    }

    return $context;
}

function tieredActionLine(int $total, OrderItemPaymentTypeEnum $paymentType = OrderItemPaymentTypeEnum::FULL_PAYMENT, bool $isBundle = false): CalculatedOrderItemData
{
    return new CalculatedOrderItemData(
        product_delivery_option: ProductDeliveryOption::factory()->make(),
        qty: 1,
        payment_type: $paymentType,
        price: $total,
        total: $total,
        is_bundle: $isBundle,
    );
}

describe('ApplyTieredPercentageOffAction', function (): void {
    test('it applies the percentage published by the tier condition, truncating the markdown', function (): void {
        $action  = new ApplyTieredPercentageOffAction();
        $context = tieredActionContext([
            tieredActionLine(5005),
            tieredActionLine(10000),
        ], publishedPercentage: 10.0);

        $action->apply($context, new ApplyTieredPercentageOffData());

        // 10% of 5005 = 500.5, truncated to 500 so existing amounts keep their unit.
        expect($context->items[0]->discount_amount)->toBe(500)
            ->and($context->items[0]->total)->toBe(4505)
            ->and($context->items[1]->discount_amount)->toBe(1000)
            ->and($context->items[1]->total)->toBe(9000);
    });

    test('it applies no markdown when no tier selection was published', function (): void {
        $action  = new ApplyTieredPercentageOffAction();
        $context = tieredActionContext([tieredActionLine(10000)]);

        $action->apply($context, new ApplyTieredPercentageOffData());

        expect($context->items[0]->discount_amount)->toBe(0)
            ->and($context->items[0]->total)->toBe(10000);
    });

    test('it applies no markdown for a non-positive published percentage', function (): void {
        $action  = new ApplyTieredPercentageOffAction();
        $context = tieredActionContext([tieredActionLine(10000)], publishedPercentage: 0.0);

        $action->apply($context, new ApplyTieredPercentageOffData());

        expect($context->items[0]->discount_amount)->toBe(0)
            ->and($context->items[0]->total)->toBe(10000);
    });

    test('it marks down only discountable lines, leaving deposits and bundles untouched', function (): void {
        $action = new ApplyTieredPercentageOffAction();

        $standalone = tieredActionLine(10000);
        $deposit    = tieredActionLine(20000, OrderItemPaymentTypeEnum::PRE_PAYMENT);
        $bundle     = tieredActionLine(50000, isBundle: true);

        $context = tieredActionContext([$standalone, $deposit, $bundle], publishedPercentage: 20.0);

        $action->apply($context, new ApplyTieredPercentageOffData());

        expect($context->items[0]->discount_amount)->toBe(2000)
            ->and($context->items[0]->total)->toBe(8000)
            ->and($context->items[1]->discount_amount)->toBe(0)
            ->and($context->items[1]->total)->toBe(20000)
            ->and($context->items[2]->discount_amount)->toBe(0)
            ->and($context->items[2]->total)->toBe(50000);
    });
});
