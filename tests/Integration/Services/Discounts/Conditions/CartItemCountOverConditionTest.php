<?php

declare(strict_types=1);

use App\Data\Admin\Discounts\CalculatedOrderItemData;
use App\Data\Admin\Discounts\OrderContextData;
use App\Enums\Order\OrderItemPaymentTypeEnum;
use App\Models\ProductDeliveryOption;
use App\Models\User;
use App\Services\Discounts\Cart\Conditions\CartItemCountOverCondition;
use App\Services\Discounts\Configs\CartItemCountOverData;

describe('CartItemCountOverCondition', function (): void {
    test('it counts distinct items correctly', function (): void {
        $condition = new CartItemCountOverCondition();
        $config = new CartItemCountOverData(min_count: 1, count_quantities: false);

        $item1 = new CalculatedOrderItemData(
            product_delivery_option: ProductDeliveryOption::factory()->make(),
            qty: 5, // Quantity shouldn't matter here
            payment_type: OrderItemPaymentTypeEnum::FULL_PAYMENT,
            price: 1000,
            total: 5000
        );
        $item2 = clone $item1;

        $context = OrderContextData::from([
            'customer' => User::factory()->make(),
            'items' => [$item1, $item2], // 2 distinct items
            'subtotal_full_payment_items' => 10000,
            'subtotal_all_items' => 10000,
        ]);

        expect($condition->passes($context, $config))->toBeTrue();

        // Fails if min_count is 2 (strictly greater than required)
        $configFails = new CartItemCountOverData(min_count: 2, count_quantities: false);
        expect($condition->passes($context, $configFails))->toBeFalse();
    });

    test('it counts quantities correctly', function (): void {
        $condition = new CartItemCountOverCondition();
        $config = new CartItemCountOverData(min_count: 3, count_quantities: true);

        $item = new CalculatedOrderItemData(
            product_delivery_option: ProductDeliveryOption::factory()->make(),
            qty: 4, // Sum of qty is 4
            payment_type: OrderItemPaymentTypeEnum::FULL_PAYMENT,
            price: 1000,
            total: 4000
        );

        $context = OrderContextData::from([
            'customer' => User::factory()->make(),
            'items' => [$item], // Only 1 distinct item, but 4 qty
            'subtotal_full_payment_items' => 4000,
            'subtotal_all_items' => 4000,
        ]);

        expect($condition->passes($context, $config))->toBeTrue();
    });
});
