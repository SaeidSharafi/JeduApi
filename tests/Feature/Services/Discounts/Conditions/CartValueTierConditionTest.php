<?php

declare(strict_types=1);

use App\Data\Admin\Discounts\CalculatedOrderItemData;
use App\Data\Admin\Discounts\OrderContextData;
use App\Enums\Order\OrderItemPaymentTypeEnum;
use App\Models\ProductDeliveryOption;
use App\Models\User;
use App\Services\Discounts\Cart\Conditions\CartValueTierCondition;
use App\Services\Discounts\Configs\CartValueTierConditionConfigData;
use App\Services\Discounts\Configs\TierData;
use Spatie\LaravelData\Data;

/**
 * @param  array<int, CalculatedOrderItemData>  $items
 */
function tierConditionContext(array $items, int $baseSubtotal = 0): OrderContextData
{
    return OrderContextData::from([
        'customer'                    => User::factory()->make(),
        'items'                       => $items,
        'subtotal_full_payment_items' => collect($items)
            ->where('payment_type', OrderItemPaymentTypeEnum::FULL_PAYMENT)
            ->sum('total'),
        'subtotal_all_items' => $baseSubtotal > 0 ? $baseSubtotal : collect($items)->sum('total'),
    ]);
}

function tierConditionFullPaymentLine(int $total, int $price = 0): CalculatedOrderItemData
{
    return new CalculatedOrderItemData(
        product_delivery_option: ProductDeliveryOption::factory()->make(),
        qty: 1,
        payment_type: OrderItemPaymentTypeEnum::FULL_PAYMENT,
        price: $price > 0 ? $price : $total,
        total: $total,
    );
}

function tierConditionConfig(): CartValueTierConditionConfigData
{
    return new CartValueTierConditionConfigData(tiers: [
        new TierData(min_amount: 3000000, percentage: 5),
        new TierData(min_amount: 5000000, percentage: 8),
    ]);
}

it('publishes the highest satisfied tier and reports eligible', function (): void {
    $handler = new CartValueTierCondition();
    $context = tierConditionContext([tierConditionFullPaymentLine(6000000)]);

    expect($handler->passes($context, tierConditionConfig()))->toBeTrue();

    $selection = $context->publishedSelection(CartValueTierCondition::HANDLER_KEY);

    expect($selection)->toBeInstanceOf(TierData::class)
        ->and($selection->min_amount)->toBe(5000000)
        ->and($selection->percentage)->toBe(8.0);
});

it('selects exactly one tier when two rungs are satisfied, never a sum of both', function (): void {
    $handler = new CartValueTierCondition();
    $context = tierConditionContext([tierConditionFullPaymentLine(6000000)]);

    $handler->passes($context, tierConditionConfig());

    $selection = $context->publishedSelection(CartValueTierCondition::HANDLER_KEY);

    expect($selection)->toBeInstanceOf(TierData::class)
        ->and($selection->min_amount)->toBe(5000000)
        ->and($selection->percentage)->toBe(8.0)
        ->and($selection->percentage)->not->toBe(13.0);
});

it('is ineligible below the lowest tier and publishes no selection', function (): void {
    $handler = new CartValueTierCondition();
    $context = tierConditionContext([tierConditionFullPaymentLine(2999999)]);

    expect($handler->passes($context, tierConditionConfig()))->toBeFalse()
        ->and($context->publishedSelection(CartValueTierCondition::HANDLER_KEY))->toBeNull();
});

it('measures the discountable lines only, so a large deposit cannot select a tier', function (): void {
    $handler = new CartValueTierCondition();

    $deposit = new CalculatedOrderItemData(
        product_delivery_option: ProductDeliveryOption::factory()->make(),
        qty: 1,
        payment_type: OrderItemPaymentTypeEnum::PRE_PAYMENT,
        price: 20000000,
        total: 20000000,
    );
    $fullPayment = tierConditionFullPaymentLine(1000000);

    // The context's all-lines total would clear the top rung; the discountable
    // total (1,000,000) does not even reach the lowest one.
    $context = tierConditionContext([$deposit, $fullPayment], baseSubtotal: 21000000);

    expect($context->discountableTotal())->toBe(1000000)
        ->and($handler->passes($context, tierConditionConfig()))->toBeFalse()
        ->and($context->publishedSelection(CartValueTierCondition::HANDLER_KEY))->toBeNull();
});

it('never measures base prices, only the effective discountable worth', function (): void {
    $handler = new CartValueTierCondition();

    // A line whose base price (5,000,000) would clear the top rung but whose
    // product-discounted total (1,000,000) does not reach the lowest one.
    $discounted = tierConditionFullPaymentLine(total: 1000000, price: 5000000);
    $context    = tierConditionContext([$discounted], baseSubtotal: 5000000);

    expect($handler->passes($context, tierConditionConfig()))->toBeFalse()
        ->and($context->publishedSelection(CartValueTierCondition::HANDLER_KEY))->toBeNull();
});

it('ignores bundle lines while tiering the standalone discountable lines', function (): void {
    $handler = new CartValueTierCondition();

    $standalone = tierConditionFullPaymentLine(3500000);
    $bundle     = new CalculatedOrderItemData(
        product_delivery_option: ProductDeliveryOption::factory()->make(),
        qty: 1,
        payment_type: OrderItemPaymentTypeEnum::FULL_PAYMENT,
        price: 5000000,
        total: 5000000,
        is_bundle: true,
    );

    $context = tierConditionContext([$standalone, $bundle], baseSubtotal: 8500000);

    expect($handler->passes($context, tierConditionConfig()))->toBeTrue();

    $selection = $context->publishedSelection(CartValueTierCondition::HANDLER_KEY);

    expect($selection)->toBeInstanceOf(TierData::class)
        ->and($selection->min_amount)->toBe(3000000)
        ->and($selection->percentage)->toBe(5.0);
});

it('returns false when the configuration is not the correct type', function (): void {
    $handler = new CartValueTierCondition();
    $context = tierConditionContext([tierConditionFullPaymentLine(6000000)]);

    $wrongConfig = new class extends Data
    {
        public function __construct() {}
    };

    expect($handler->passes($context, $wrongConfig))->toBeFalse()
        ->and($context->publishedSelection(CartValueTierCondition::HANDLER_KEY))->toBeNull();
});
