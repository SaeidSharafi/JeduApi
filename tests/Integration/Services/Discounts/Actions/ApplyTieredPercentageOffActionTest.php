<?php

declare(strict_types=1);

use App\Data\Admin\Discounts\CalculatedOrderItemData;
use App\Data\Admin\Discounts\OrderContextData;
use App\Enums\Order\OrderItemPaymentTypeEnum;
use App\Models\ProductDeliveryOption;
use App\Models\User;
use App\Services\Discounts\Cart\Actions\ApplyTieredPercentageOffAction;
use App\Services\Discounts\Configs\ApplyTieredPercentageOffData;
use App\Services\Discounts\Configs\TierData;

describe('ApplyTieredPercentageOffAction', function (): void {
    test('it applies the correct tier percentage across all items', function (): void {
        $action = new ApplyTieredPercentageOffAction();
        $config = new ApplyTieredPercentageOffData(tiers: [
            new TierData(min_amount: 10000, percentage: 10),
            new TierData(min_amount: 20000, percentage: 20),
        ]);

        $item1 = new CalculatedOrderItemData(
            product_delivery_option: ProductDeliveryOption::factory()->make(),
            qty: 1, payment_type: OrderItemPaymentTypeEnum::FULL_PAYMENT,
            price: 5000, total: 5000
        );
        $item2 = new CalculatedOrderItemData(
            product_delivery_option: ProductDeliveryOption::factory()->make(),
            qty: 1, payment_type: OrderItemPaymentTypeEnum::FULL_PAYMENT,
            price: 10000, total: 10000
        );

        // Subtotal is 15000. It hits the 10000 tier (10%), but misses the 20000 tier.
        $context = OrderContextData::from([
            'customer'                    => User::factory()->make(),
            'items'                       => [$item1, $item2],
            'subtotal_full_payment_items' => 15000,
            'subtotal_all_items'          => 15000,
        ]);

        $action->apply($context, $config);

        // 10% of 5000 = 500
        expect($context->items[0]->discount_amount)->toBe(500)
            ->and($context->items[0]->total)->toBe(4500);

        // 10% of 10000 = 1000
        expect($context->items[1]->discount_amount)->toBe(1000)
            ->and($context->items[1]->total)->toBe(9000);
    });

    test('it applies nothing if cart does not reach minimum tier', function (): void {
        $action = new ApplyTieredPercentageOffAction();
        $config = new ApplyTieredPercentageOffData(tiers: [
            new TierData(min_amount: 50000, percentage: 50),
        ]);

        $item1 = new CalculatedOrderItemData(
            product_delivery_option: ProductDeliveryOption::factory()->make(),
            qty: 1, payment_type: OrderItemPaymentTypeEnum::FULL_PAYMENT,
            price: 10000, total: 10000
        );

        $context = OrderContextData::from([
            'customer'                    => User::factory()->make(),
            'items'                       => [$item1],
            'subtotal_full_payment_items' => 10000,
            'subtotal_all_items'          => 10000,
        ]);

        $action->apply($context, $config);

        expect($context->items[0]->discount_amount)->toBe(0)
            ->and($context->items[0]->total)->toBe(10000);
    });

    test('it leaves a bundle line undiscounted while tiering a standalone line', function (): void {
        $action = new ApplyTieredPercentageOffAction();
        $config = new ApplyTieredPercentageOffData(tiers: [
            new TierData(min_amount: 20000, percentage: 20),
        ]);

        $standalone = new CalculatedOrderItemData(
            product_delivery_option: ProductDeliveryOption::factory()->make(),
            qty: 1, payment_type: OrderItemPaymentTypeEnum::FULL_PAYMENT,
            price: 10000, total: 10000
        );
        $bundle = new CalculatedOrderItemData(
            product_delivery_option: ProductDeliveryOption::factory()->make(),
            qty: 1, payment_type: OrderItemPaymentTypeEnum::FULL_PAYMENT,
            price: 50000, total: 50000,
            is_bundle: true
        );

        // Cart subtotal of 60000 reaches the 20000 tier.
        $context = OrderContextData::from([
            'customer'                    => User::factory()->make(),
            'items'                       => [$standalone, $bundle],
            'subtotal_full_payment_items' => 60000,
            'subtotal_all_items'          => 60000,
        ]);

        $action->apply($context, $config);

        // 20% of 10000 = 2000 on the standalone line.
        expect($context->items[0]->discount_amount)->toBe(2000)
            ->and($context->items[0]->total)->toBe(8000);

        // The bundle line keeps its full total.
        expect($context->items[1]->discount_amount)->toBe(0)
            ->and($context->items[1]->total)->toBe(50000);
    });

    test('it applies nothing when the cart holds only a bundle line', function (): void {
        $action = new ApplyTieredPercentageOffAction();
        $config = new ApplyTieredPercentageOffData(tiers: [
            new TierData(min_amount: 10000, percentage: 50),
        ]);

        $bundle = new CalculatedOrderItemData(
            product_delivery_option: ProductDeliveryOption::factory()->make(),
            qty: 1, payment_type: OrderItemPaymentTypeEnum::FULL_PAYMENT,
            price: 50000, total: 50000,
            is_bundle: true
        );

        $context = OrderContextData::from([
            'customer'                    => User::factory()->make(),
            'items'                       => [$bundle],
            'subtotal_full_payment_items' => 50000,
            'subtotal_all_items'          => 50000,
        ]);

        $action->apply($context, $config);

        expect($context->items[0]->discount_amount)->toBe(0)
            ->and($context->items[0]->total)->toBe(50000);
    });
});
