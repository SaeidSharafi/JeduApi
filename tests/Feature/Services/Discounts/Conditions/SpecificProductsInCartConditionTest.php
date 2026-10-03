<?php

declare(strict_types=1);

use App\Data\Admin\Discounts\CalculatedOrderItemData;
use App\Data\Admin\Discounts\OrderContextData;
use App\Enums\Order\OrderItemPaymentTypeEnum;
use App\Models\Product;
use App\Models\ProductDeliveryOption;
use App\Models\User;
use App\Services\Discounts\Cart\Conditions\SpecificProductsInCartCondition;
use App\Services\Discounts\Configs\SpecificProductsInCartData;

describe('SpecificProductsInCartCondition', function (): void {
    test('it passes when the cart contains a specified product id', function (): void {
        $condition     = new SpecificProductsInCartCondition();
        $targetProduct = Product::factory()->create();
        $otherProduct  = Product::factory()->create();

        $config = new SpecificProductsInCartData(product_ids: [$targetProduct->id]);

        $item = new CalculatedOrderItemData(
            product_delivery_option: ProductDeliveryOption::factory()->make(['product_id' => $targetProduct->id]),
            qty: 1, payment_type: OrderItemPaymentTypeEnum::FULL_PAYMENT, price: 1000, total: 1000
        );

        $context = OrderContextData::from([
            'customer'                    => User::factory()->make(),
            'items'                       => collect([$item]),
            'subtotal_full_payment_items' => 1000,
            'subtotal_all_items'          => 1000,
        ]);

        expect($condition->passes($context, $config))->toBeTrue();
    });

    test('it fails when the cart does not contain the specified product id', function (): void {
        $condition     = new SpecificProductsInCartCondition();
        $targetProduct = Product::factory()->create();
        $otherProduct  = Product::factory()->create();

        $config = new SpecificProductsInCartData(product_ids: [$targetProduct->id]);

        $item = new CalculatedOrderItemData(
            product_delivery_option: ProductDeliveryOption::factory()->make(['product_id' => $otherProduct->id]),
            qty: 1, payment_type: OrderItemPaymentTypeEnum::FULL_PAYMENT, price: 1000, total: 1000
        );

        $context = OrderContextData::from([
            'customer'                    => User::factory()->make(),
            'items'                       => collect([$item]),
            'subtotal_full_payment_items' => 1000,
            'subtotal_all_items'          => 1000,
        ]);

        expect($condition->passes($context, $config))->toBeFalse();
    });
});
