<?php

declare(strict_types=1);

use App\Data\Admin\Discounts\CalculatedOrderItemData;
use App\Data\Admin\Discounts\OrderContextData;
use App\Enums\Operators\MatchPolicyEnum;
use App\Enums\Order\OrderItemPaymentTypeEnum;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductDeliveryOption;
use App\Models\User;
use App\Services\Discounts\Cart\Conditions\ProductCategoryCondition;
use App\Services\Discounts\Configs\ProductCategoryConditionConfigData;

it('passes if match policy is "any" and at least one item is in a category', function () {
    // Covers: 'any' => $matchingProductCount > 0
    $categoryA = Category::factory()->create();
    $categoryB = Category::factory()->create();

    $productA = Product::factory()->create();
    $productA->categories()->attach($categoryA);
    $productB = Product::factory()->create(); // Not in any specified category

    $pdoA = ProductDeliveryOption::factory()->create(['product_id' => $productA->id]);
    $pdoB = ProductDeliveryOption::factory()->create(['product_id' => $productB->id]);

    $handler = new ProductCategoryCondition();
    $config  = new ProductCategoryConditionConfigData(category_ids: [$categoryA->id, $categoryB->id],
        match_policy: MatchPolicyEnum::ANY);
    $context = OrderContextData::from([
        'customer' => User::factory()->create(),
        'items'    => [
            new CalculatedOrderItemData(
                product_delivery_option: $pdoA,
                qty: 1,
                payment_type: OrderItemPaymentTypeEnum::FULL_PAYMENT,
                price: 1000,
                total: 1000
            ),
            new CalculatedOrderItemData(
                product_delivery_option: $pdoB,
                qty: 1,
                payment_type: OrderItemPaymentTypeEnum::FULL_PAYMENT,
                price: 1000,
                total: 1000
            ),
        ],
        'subtotal_full_payment_items' => 0,
        'subtotal_all_items'          => 0,
    ]);

    expect($handler->passes($context, $config))->toBeTrue();
});

it('passes if match policy is "all" and all items are in categories', function () {
    // Covers: 'all' => $matchingProductCount === count($itemProductIds)
    $categoryA = Category::factory()->create();
    $categoryB = Category::factory()->create();

    $productA = Product::factory()->create();
    $productA->categories()->attach($categoryA);
    $productB = Product::factory()->create();
    $productB->categories()->attach($categoryB);

    $pdoA = ProductDeliveryOption::factory()->create(['product_id' => $productA->id]);
    $pdoB = ProductDeliveryOption::factory()->create(['product_id' => $productB->id]);

    $handler = new ProductCategoryCondition();
    $config  = new ProductCategoryConditionConfigData(category_ids: [$categoryA->id, $categoryB->id],
        match_policy: MatchPolicyEnum::ALL);
    $context = OrderContextData::from([
        'customer' => User::factory()->create(),
        'items'    => [
            new CalculatedOrderItemData(
                product_delivery_option: $pdoA,
                qty: 1,
                payment_type: OrderItemPaymentTypeEnum::FULL_PAYMENT,
                price: 1000,
                total: 1000
            ),
            new CalculatedOrderItemData(
                product_delivery_option: $pdoB,
                qty: 1,
                payment_type: OrderItemPaymentTypeEnum::FULL_PAYMENT,
                price: 1000,
                total: 1000
            ),
        ],
        'subtotal_full_payment_items' => 0,
        'subtotal_all_items'          => 0,
    ]);

    expect($handler->passes($context, $config))->toBeTrue();
});

it('fails if match policy is "all" and one item is not in a category', function () {
    $categoryA = Category::factory()->create();
    $productA  = Product::factory()->create();
    $productA->categories()->attach($categoryA);
    $productB = Product::factory()->create(); // Not in a category

    $pdoA = ProductDeliveryOption::factory()->create(['product_id' => $productA->id]);
    $pdoB = ProductDeliveryOption::factory()->create(['product_id' => $productB->id]);

    $handler = new ProductCategoryCondition();
    $config  = new ProductCategoryConditionConfigData(category_ids: [$categoryA->id], match_policy: MatchPolicyEnum::ALL);
    $item1   = new CalculatedOrderItemData(
        product_delivery_option: $pdoA,
        qty: 1,
        payment_type: OrderItemPaymentTypeEnum::FULL_PAYMENT,
        price: 1000,
        total: 1000
    );
    $item2 = new CalculatedOrderItemData(
        product_delivery_option: $pdoB,
        qty: 1, payment_type: OrderItemPaymentTypeEnum::FULL_PAYMENT,
        price: 1000,
        total: 1000
    );
    $context = OrderContextData::from([
        'customer' => User::factory()->create(),
        'items'    => [
            $item1,
            $item2,
        ],
        'subtotal_full_payment_items' => 0,
        'subtotal_all_items'          => 0,
    ]);

    expect($handler->passes($context, $config))->toBeFalse();
});

it('returns true if category_ids config is empty', function () {
    // Covers: if (empty($configuration->category_ids))
    $handler = new ProductCategoryCondition();
    $config  = new ProductCategoryConditionConfigData(category_ids: []);
    $item1   = new CalculatedOrderItemData(
        product_delivery_option: ProductDeliveryOption::factory()->create(),
        qty: 1,
        payment_type: OrderItemPaymentTypeEnum::FULL_PAYMENT,
        price: 1000,
        total: 1000
    );
    $item2 = new CalculatedOrderItemData(
        product_delivery_option: ProductDeliveryOption::factory()->create(),
        qty: 1, payment_type: OrderItemPaymentTypeEnum::FULL_PAYMENT,
        price: 1000,
        total: 1000
    );
    $context = OrderContextData::from([
        'customer' => User::factory()->create(),
        'items'    => [
            $item1,
            $item2,
        ],
        'subtotal_full_payment_items' => 0,
        'subtotal_all_items'          => 0,
    ]);

    expect($handler->passes($context, $config))->toBeTrue();
});

it('returns false if context has no items', function () {
    // Covers: if (empty($itemProductIds))
    $handler = new ProductCategoryCondition();
    $config  = new ProductCategoryConditionConfigData(category_ids: [1]);
    $context = OrderContextData::from([
        'customer'           => User::factory()->create(), 'items' => [], 'subtotal_full_payment_items' => 0,
        'subtotal_all_items' => 0,
    ]);

    expect($handler->passes($context, $config))->toBeFalse();
});

it('returns false if configuration is not an instance of ProductCategoryConditionConfigData', function () {
    // Covers: if (! $configuration instanceof ProductCategoryConditionConfigData)
    $handler = new ProductCategoryCondition();
    $context = OrderContextData::from([
        'customer'                    => User::factory()->create(),
        'items'                       => [],
        'subtotal_full_payment_items' => 0,
        'subtotal_all_items'          => 0,
    ]);

    expect($handler->passes($context, new class extends Spatie\LaravelData\Data {}))->toBeFalse();
});
