<?php

declare(strict_types=1);

use App\Data\Admin\Discounts\CalculatedOrderItemData;
use App\Data\Admin\Discounts\OrderContextData;
use App\Enums\Order\OrderItemPaymentTypeEnum;
use App\Models\ProductDeliveryOption;
use App\Services\Discounts\Cart\Actions\ApplyPercentageDiscountToItemsAction;
use App\Services\Discounts\Configs\ApplyPercentageDiscountConfigData;

it('does not apply discount if discount is greater than the item price', function () {
    // This test covers: if ($discountPerUnit > $item->price)
    $handler = new ApplyPercentageDiscountToItemsAction();
    // This 200% discount will be greater than the price
    $config = new ApplyPercentageDiscountConfigData(percentage: 200);

    $item = new CalculatedOrderItemData(
        product_delivery_option: ProductDeliveryOption::factory()->make(['price' => 1000]),
        qty: 1, payment_type: OrderItemPaymentTypeEnum::FULL_PAYMENT, price: 1000, total: 1000
    );
    $context = OrderContextData::from([
        'customer'                    => App\Models\User::factory()->create(),
        'items'                       => [$item],
        'subtotal_full_payment_items' => 20000,
        'subtotal_all_items'          => 20000,
    ]);

    $handler->apply($context, $config);

    // Assert: The discount is capped at the item's price, and the total is 0.
    expect($context->items[0]->discount_amount)->toBe(1000)
        ->and($context->items[0]->total)->toBe(0);
});

it('returns early if configuration is not the correct type', function () {
    // This test covers: if (!$configuration instanceof ...)
    $handler     = new ApplyPercentageDiscountToItemsAction();
    $wrongConfig = new class extends Spatie\LaravelData\Data
    {
        public function __construct() {}
    };

    $item = new CalculatedOrderItemData(
        product_delivery_option: ProductDeliveryOption::factory()->make(['price' => 1000]),
        qty: 1, payment_type: OrderItemPaymentTypeEnum::FULL_PAYMENT, price: 1000, total: 1000
    );
    $context = OrderContextData::from(
        [
            'customer'                    => App\Models\User::factory()->create(),
            'items'                       => [$item],
            'subtotal_full_payment_items' => 20000,
            'subtotal_all_items'          => 20000,
        ]
    );

    // Act
    $handler->apply($context, $wrongConfig);

    // Assert: Nothing happened, the item's total is unchanged.
    expect($context->items[0]->total)->toBe(1000);
});
it('applies a percentage discount to a full payment item', function () {
    // Arrange
    $handler = new ApplyPercentageDiscountToItemsAction();
    $config  = new ApplyPercentageDiscountConfigData(percentage: 25); // 25% off

    $item = new CalculatedOrderItemData(
        product_delivery_option: ProductDeliveryOption::factory()->make([
            'price'             => 20000,
            'prepayment_amount' => 0,
            'is_prepayment'     => false,
        ]),
        qty: 1,
        payment_type: OrderItemPaymentTypeEnum::FULL_PAYMENT,
        price: 20000,
        total: 20000
    );
    $context = OrderContextData::from(
        [
            'customer'                    => App\Models\User::factory()->create(),
            'items'                       => [$item],
            'subtotal_full_payment_items' => 20000,
            'subtotal_all_items'          => 20000,
        ]);

    // Act
    $handler->apply($context, $config);

    // Assert
    expect($context->items[0]->discount_amount)->toBe(5000) // 25% of 20000
        ->and($context->items[0]->total)->toBe(15000);
});

it('does not apply discount to a prepayment item', function () {
    // Arrange
    $handler = new ApplyPercentageDiscountToItemsAction();
    $config  = new ApplyPercentageDiscountConfigData(percentage: 25);

    $item = new CalculatedOrderItemData(
        product_delivery_option: ProductDeliveryOption::factory()->make(
            [
                'price'             => 20000,
                'prepayment_amount' => 2000,
                'is_prepayment'     => true,
            ]),
        qty: 1, payment_type: OrderItemPaymentTypeEnum::PRE_PAYMENT,
        price: 20000,
        total: 2000

    );
    $context = OrderContextData::from(
        [
            'customer'                    => App\Models\User::factory()->create(),
            'items'                       => [$item],
            'subtotal_full_payment_items' => 20000,
            'subtotal_all_items'          => 2000,
        ]
    );

    // Act
    $handler->apply($context, $config);

    // Assert: Nothing changed
    expect($context->items[0]->discount_amount)->toBe(0)
        ->and($context->items[0]->total)->toBe(2000);
});
