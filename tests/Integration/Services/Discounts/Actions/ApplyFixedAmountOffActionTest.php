<?php

declare(strict_types=1);

use App\Data\Admin\Discounts\CalculatedOrderItemData;
use App\Data\Admin\Discounts\OrderContextData;
use App\Enums\Order\OrderItemPaymentTypeEnum;
use App\Models\ProductDeliveryOption;
use App\Models\User;
use App\Services\Discounts\Cart\Actions\ApplyFixedAmountOffAction;
use App\Services\Discounts\Configs\ApplyFixedAmountOffData;

describe('ApplyFixedAmountOffAction', function (): void {
    test('it distributes flat discount proportionally across items', function (): void {
        $action = new ApplyFixedAmountOffAction();
        $config = new ApplyFixedAmountOffData(amount: 3000); // 3000 total discount

        $item1 = new CalculatedOrderItemData(
            product_delivery_option: ProductDeliveryOption::factory()->make(),
            qty: 1, payment_type: OrderItemPaymentTypeEnum::FULL_PAYMENT,
            price: 2000, total: 2000
        );
        $item2 = new CalculatedOrderItemData(
            product_delivery_option: ProductDeliveryOption::factory()->make(),
            qty: 1, payment_type: OrderItemPaymentTypeEnum::FULL_PAYMENT,
            price: 8000, total: 8000
        );

        $context = OrderContextData::from([
            'customer'                    => User::factory()->make(),
            'items'                       => [$item1, $item2],
            'subtotal_full_payment_items' => 10000,
            'subtotal_all_items'          => 10000,
        ]);

        $action->apply($context, $config);

        // Item 1 represents 20% of the total, should receive 20% of 3000 = 600
        expect($context->items[0]->discount_amount)->toBe(600)
            ->and($context->items[0]->total)->toBe(1400);

        // Item 2 represents 80% of the total, should receive 80% of 3000 = 2400
        expect($context->items[1]->discount_amount)->toBe(2400)
            ->and($context->items[1]->total)->toBe(5600);
    });

    test('it caps the discount to the total cart value', function (): void {
        $action = new ApplyFixedAmountOffAction();
        $config = new ApplyFixedAmountOffData(amount: 10000); // More than cart value

        $item1 = new CalculatedOrderItemData(
            product_delivery_option: ProductDeliveryOption::factory()->make(),
            qty: 1, payment_type: OrderItemPaymentTypeEnum::FULL_PAYMENT,
            price: 4000, total: 4000
        );

        $context = OrderContextData::from([
            'customer'                    => User::factory()->make(),
            'items'                       => [$item1],
            'subtotal_full_payment_items' => 4000,
            'subtotal_all_items'          => 4000,
        ]);

        $action->apply($context, $config);

        expect($context->items[0]->discount_amount)->toBe(4000)
            ->and($context->items[0]->total)->toBe(0);
    });

    test('it distributes the flat discount over standalone lines only, leaving a bundle line untouched', function (): void {
        $action = new ApplyFixedAmountOffAction();
        $config = new ApplyFixedAmountOffData(amount: 3000); // 3000 total discount

        $standalone = new CalculatedOrderItemData(
            product_delivery_option: ProductDeliveryOption::factory()->make(),
            qty: 1, payment_type: OrderItemPaymentTypeEnum::FULL_PAYMENT,
            price: 8000, total: 8000
        );
        $bundle = new CalculatedOrderItemData(
            product_delivery_option: ProductDeliveryOption::factory()->make(),
            qty: 1, payment_type: OrderItemPaymentTypeEnum::FULL_PAYMENT,
            price: 2000, total: 2000,
            is_bundle: true
        );

        $context = OrderContextData::from([
            'customer'                    => User::factory()->make(),
            'items'                       => [$standalone, $bundle],
            'subtotal_full_payment_items' => 10000,
            'subtotal_all_items'          => 10000,
        ]);

        $action->apply($context, $config);

        // The bundle is excluded from the distribution weight, so the standalone
        // line (the only eligible line) absorbs the full 3000.
        expect($context->items[0]->discount_amount)->toBe(3000)
            ->and($context->items[0]->total)->toBe(5000)
            ->and($context->items[1]->discount_amount)->toBe(0)
            ->and($context->items[1]->total)->toBe(2000);
    });

    test('it applies no discount when the cart holds only a bundle line', function (): void {
        $action = new ApplyFixedAmountOffAction();
        $config = new ApplyFixedAmountOffData(amount: 3000);

        $bundle = new CalculatedOrderItemData(
            product_delivery_option: ProductDeliveryOption::factory()->make(),
            qty: 1, payment_type: OrderItemPaymentTypeEnum::FULL_PAYMENT,
            price: 2000, total: 2000,
            is_bundle: true
        );

        $context = OrderContextData::from([
            'customer'                    => User::factory()->make(),
            'items'                       => [$bundle],
            'subtotal_full_payment_items' => 2000,
            'subtotal_all_items'          => 2000,
        ]);

        $action->apply($context, $config);

        expect($context->items[0]->discount_amount)->toBe(0)
            ->and($context->items[0]->total)->toBe(2000);
    });
});
