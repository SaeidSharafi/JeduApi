<?php

declare(strict_types=1);

use App\Actions\Admin\Discounts\ResolvePerCustomerPromotionEligibilityAction;
use App\Data\Admin\Discounts\OrderContextData;
use App\Data\Admin\Order\OrderCreateData;
use App\Data\Admin\Order\OrderItemCreateData;
use App\Enums\Order\DiscountTypeEnum;
use App\Enums\Product\ProductableEnum;
use App\Models\DiscountPromotion;
use App\Models\DiscountPromotionRule;
use App\Models\Product;
use App\Models\ProductDeliveryOption;
use App\Models\ProductDeliveryOptionDiscountPrice;
use App\Models\User;
use App\Services\Discounts\Cart\Actions\ApplyPercentageDiscountToItemsAction;
use App\Services\Discounts\Cart\Conditions\CartValueCondition;
use App\Services\Discounts\DiscountHandlerRegistry;
use App\Services\Discounts\OrderCalculationService;
use App\Services\Discounts\PromotionService;
use App\Services\ProductPriceService;

/**
 * Helper: create a partial PromotionService mock with real context-building
 * and condition-checking, but a controlled findAllApplicableCartPromotions.
 */
function mockPromotionFinderReturning(mixed $promotions): void
{
    $mock = Mockery::mock(PromotionService::class, [
        app(DiscountHandlerRegistry::class),
        app(ProductPriceService::class),
        app(ResolvePerCustomerPromotionEligibilityAction::class),
        app(App\Services\Discounts\PromotionCapacity::class),
    ])->makePartial();

    $resolvedPromotions = match (true) {
        $promotions instanceof DiscountPromotion             => collect([$promotions]),
        $promotions instanceof Illuminate\Support\Collection => $promotions,
        is_array($promotions)                                => collect($promotions),
        default                                              => collect(),
    };

    $mock->shouldReceive('findAllApplicableCartPromotions')->andReturn(
        $resolvedPromotions
    );
    app()->instance(PromotionService::class, $mock);
}

it('calculates a percentage discount correctly when a promotion is found', function (): void {
    // Arrange
    $user           = User::factory()->create();
    $deliveryOption = ProductDeliveryOption::factory()->create(['price' => 10000]);

    $promotion = DiscountPromotion::factory()->make([
        'id'   => 50,
        'type' => DiscountTypeEnum::CART_CHECKOUT,
    ]);
    $promotion->setRelation('rules', collect([
        ['type' => 'action', 'handler' => 'apply_percentage_off', 'configuration' => ['percentage' => 20]],
    ]));

    mockPromotionFinderReturning($promotion);

    $data = new OrderCreateData(
        status: App\Enums\Order\OrderStatusEnum::PENDING->value,
        customer_id: $user->id,
        items: [
            new OrderItemCreateData(
                product_delivery_option_id: $deliveryOption->id, payment_type: 'full_payment'),
        ],
        applied_coupon_code: 'SAVE20'
    );

    $service = app(OrderCalculationService::class);

    // Act
    $context = $service->calculate($data);

    // Assert
    expect($context->items[0]->discount_amount)->toBe(2000);
    expect($context->items[0]->total)->toBe(8000);
});

it('returns a context with zero discounts if no promotion is found', function (): void {
    // Arrange
    $user           = User::factory()->create();
    $deliveryOption = ProductDeliveryOption::factory()->create(['price' => 10000]);

    mockPromotionFinderReturning(null);

    $data = new OrderCreateData(
        status: App\Enums\Order\OrderStatusEnum::PENDING->value,
        customer_id: $user->id,
        items: [new OrderItemCreateData(product_delivery_option_id: $deliveryOption->id, payment_type: 'full_payment')],
        applied_coupon_code: null
    );

    $service = app(OrderCalculationService::class);

    // Act
    $context = $service->calculate($data);

    // Assert
    expect($context->items[0]->discount_amount)->toBe(0);
});

it('correctly uses featured price as the base for calculation', function (): void {
    $user           = User::factory()->create();
    $deliveryOption = ProductDeliveryOption::factory()->create([
        'price'                     => 10000,
        'is_featured'               => true,
        'featured_price'            => 8000,
        'featured_price_start_date' => now()->subDay(),
        'featured_price_end_date'   => now()->addDay(),
    ]);

    $promotion = DiscountPromotion::factory()->make([
        'type' => DiscountTypeEnum::CART_CHECKOUT,
    ]);
    $promotion->setRelation('rules', collect([
        ['type' => 'action', 'handler' => 'apply_percentage_off', 'configuration' => ['percentage' => 10]],
    ]));

    mockPromotionFinderReturning($promotion);

    $data = new OrderCreateData(
        status: App\Enums\Order\OrderStatusEnum::PENDING->value,
        customer_id: $user->id,
        items: [
            new OrderItemCreateData(
                product_delivery_option_id: $deliveryOption->id,
                payment_type: 'full_payment'),
        ],
        applied_coupon_code: null
    );

    $service = app(OrderCalculationService::class);

    // Act
    $context = $service->calculate($data);

    // Assert: The 10% discount is applied to the featured price of 8000, not the original 10000.
    expect($context->items[0]->price)->toBe(8000);
    expect($context->items[0]->discount_amount)->toBe(800);
    expect($context->items[0]->total)->toBe(7200);
});

test('it correctly uses a pre-calculated product-specific discount as the highest priority base price', function (): void {
    // Arrange
    $user           = User::factory()->create();
    $deliveryOption = ProductDeliveryOption::factory()->create([
        'price'          => 10000,
        'is_featured'    => true,
        'featured_price' => 8000,
    ]);

    ProductDeliveryOptionDiscountPrice::factory()
        ->forProductDeliveryOption($deliveryOption)
        ->create([
            'discounted_price' => 5000,
        ]);

    mockPromotionFinderReturning(null);

    $data = new OrderCreateData(
        status: App\Enums\Order\OrderStatusEnum::PENDING->value,
        customer_id: $user->id,
        items: [new OrderItemCreateData(product_delivery_option_id: $deliveryOption->id, payment_type: 'full_payment')],
        applied_coupon_code: null
    );

    $service = app(OrderCalculationService::class);

    // Act
    $context = $service->calculate($data);

    // Assert
    expect($context->items[0]->price)->toBe(5000);
    expect($context->items[0]->total)->toBe(5000);
});

test('it calculates subtotals correctly for mixed payment types', function (): void {
    // Arrange
    $user              = User::factory()->create();
    $fullPaymentOption = ProductDeliveryOption::factory()->create([
        'price'                   => 10000,
        'is_prepayment_available' => false,
        'prepayment_amount'       => 0,
    ]);
    $prePaymentOption = ProductDeliveryOption::factory()->create([
        'price'                   => 50000,
        'is_prepayment_available' => true,
        'prepayment_amount'       => 2000,
    ]);

    mockPromotionFinderReturning(null);

    $data = new OrderCreateData(
        status: App\Enums\Order\OrderStatusEnum::PENDING->value,
        customer_id: $user->id,
        items: [
            new OrderItemCreateData(product_delivery_option_id: $fullPaymentOption->id, payment_type: 'full_payment'),
            new OrderItemCreateData(product_delivery_option_id: $prePaymentOption->id, payment_type: 'pre_payment'),
        ],
        applied_coupon_code: null
    );

    $service = app(OrderCalculationService::class);

    // Act
    $context = $service->calculate($data);

    // Assert
    expect($context->subtotal_all_items)->toBe(60000);
    expect($context->subtotal_full_payment_items)->toBe(10000);
});

test('it sums every line\'s effective total into one all-lines measurement basis', function (): void {
    // Arrange: a mixed cart whose base prices differ from what each line is worth.
    $user = User::factory()->create();

    // Product-discounted full-payment line: 100,000 list, 80,000 effective.
    $discountedOption = ProductDeliveryOption::factory()->create([
        'price'                   => 100000,
        'is_prepayment_available' => false,
    ]);
    ProductDeliveryOptionDiscountPrice::factory()
        ->forProductDeliveryOption($discountedOption)
        ->create(['discounted_price' => 80000]);

    // Deposit line: 200,000 course price, 30,000 deposit.
    $prePaymentOption = ProductDeliveryOption::factory()->create([
        'price'                   => 200000,
        'is_prepayment_available' => true,
        'prepayment_amount'       => 30000,
    ]);

    // Bundle line: priced at its reviewed selling price, 50,000.
    $bundleProduct = Product::factory()->create([
        'productable_type' => ProductableEnum::BUNDLE->value,
    ]);
    $bundleOption = ProductDeliveryOption::factory()->create([
        'product_id'              => $bundleProduct->id,
        'price'                   => 50000,
        'is_prepayment_available' => false,
    ]);

    mockPromotionFinderReturning(null);

    $data = new OrderCreateData(
        status: App\Enums\Order\OrderStatusEnum::PENDING->value,
        customer_id: $user->id,
        items: [
            new OrderItemCreateData(product_delivery_option_id: $discountedOption->id, payment_type: 'full_payment'),
            new OrderItemCreateData(product_delivery_option_id: $prePaymentOption->id, payment_type: 'pre_payment'),
            new OrderItemCreateData(product_delivery_option_id: $bundleOption->id, payment_type: 'full_payment'),
        ],
        applied_coupon_code: null
    );

    // Act
    $context = app(OrderCalculationService::class)->calculate($data);

    // Assert: 80,000 discounted line + 30,000 deposit + 50,000 bundle.
    expect($context->effective_subtotal_all_items)->toBe(160000)
        ->and($context->items->sum('total'))->toBe(160000)
        // The full-payment threshold snapshot counts only what a markdown can
        // reach: the 80,000 discounted line, never the deposit or the bundle.
        ->and($context->effective_subtotal_discountable_items)->toBe(80000)
        ->and($context->thresholdBasis(false))->toBe(80000)
        ->and($context->thresholdBasis(true))->toBe(160000)
        // The legacy subtotals keep their own, unchanged meanings.
        ->and($context->subtotal_all_items)->toBe(350000)
        ->and($context->subtotal_full_payment_items)->toBe(130000);
});

test('it throws a runtime exception if a condition handler config dto is not mapped', function (): void {
    $customer     = User::factory()->create();
    $orderContext = new OrderContextData(
        customer: $customer,
        items: collect(),
        subtotal_full_payment_items: 0,
        subtotal_all_items: 0,
    );

    $promotion = DiscountPromotion::factory()->create([
        'type' => DiscountTypeEnum::CART_CHECKOUT,
    ]);
    DiscountPromotionRule::create([
        'discount_promotion_id' => $promotion->id,
        'type'                  => 'condition',
        'handler'               => 'cart_value_over',
        'configuration'         => json_encode(['value' => 10000, 'operator' => '>=', 'measure_whole_commitment' => true]),
    ]);

    $promotion->load('rules');

    $mockRegistry = $this->mock(DiscountHandlerRegistry::class);
    $mockRegistry->shouldReceive('getCartConditionHandler')
        ->with('cart_value_over')
        ->andReturn(CartValueCondition::class);
    $mockRegistry->shouldReceive('getConfigClass')
        ->with(CartValueCondition::class)
        ->andReturn(null);

    $this->app->instance(DiscountHandlerRegistry::class, $mockRegistry);

    $promotionService = app(PromotionService::class);

    $closure = function () use ($promotionService, $promotion, $orderContext): void {
        $promotionService->promotionConditionsPass($promotion, $orderContext);
    };

    expect($closure)
        ->toThrow(
            RuntimeException::class,
            "No config DTO mapped for handler '".CartValueCondition::class."'"
        );
});

test('it throws a runtime exception if an action handler config dto is not mapped', function (): void {
    $customer     = User::factory()->create();
    $orderContext = new OrderContextData(
        customer: $customer,
        items: collect(),
        subtotal_full_payment_items: 0,
        subtotal_all_items: 0,
    );

    $promotion = DiscountPromotion::factory()->create([
        'type' => DiscountTypeEnum::CART_CHECKOUT,
    ]);
    DiscountPromotionRule::create([
        'discount_promotion_id' => $promotion->id,
        'type'                  => 'action',
        'handler'               => 'apply_percentage_off',
        'configuration'         => json_encode(['percentage' => 15]),
    ]);

    $promotion->load('rules');

    $mockRegistry = $this->mock(DiscountHandlerRegistry::class);
    $mockRegistry->shouldReceive('getCartActionHandler')
        ->with('apply_percentage_off')
        ->andReturn(ApplyPercentageDiscountToItemsAction::class);
    $mockRegistry->shouldReceive('getConfigClass')
        ->with(ApplyPercentageDiscountToItemsAction::class)
        ->andReturn(null);

    $this->app->instance(DiscountHandlerRegistry::class, $mockRegistry);

    $service = app(OrderCalculationService::class);

    $closure = function () use ($service, $promotion, $orderContext): void {
        $method = new ReflectionMethod(OrderCalculationService::class, 'applyActions');
        $method->setAccessible(true);
        $method->invoke($service, $promotion, $orderContext);
    };

    expect($closure)
        ->toThrow(
            RuntimeException::class,
            "No config DTO mapped for handler '".ApplyPercentageDiscountToItemsAction::class."'"
        );
});

test('it throws a runtime exception for an unregistered condition config', function (): void {
    // Arrange
    $user           = User::factory()->create();
    $deliveryOption = ProductDeliveryOption::factory()->create();

    $promotion = DiscountPromotion::factory()->make([
        'type' => DiscountTypeEnum::CART_CHECKOUT,
    ]);
    $promotion->setRelation('rules', collect([
        ['type' => 'condition', 'handler' => 'this_handler_does_not_exist', 'configuration' => []],
    ]));

    mockPromotionFinderReturning($promotion);

    $data = new OrderCreateData(
        status: App\Enums\Order\OrderStatusEnum::PENDING->value,
        customer_id: $user->id,
        items: [new OrderItemCreateData(product_delivery_option_id: $deliveryOption->id, payment_type: 'full_payment')],
        applied_coupon_code: null
    );

    $service = app(OrderCalculationService::class);

    // Act & Assert
    expect(fn () => $service->calculate($data))
        ->toThrow(RuntimeException::class,
            "No discount condition handler registered for 'this_handler_does_not_exist'");
});

test('it throws a runtime exception for an unregistered action handler', function (): void {
    $user           = User::factory()->create();
    $deliveryOption = ProductDeliveryOption::factory()->create();

    $promotion = DiscountPromotion::factory()->make([
        'type' => DiscountTypeEnum::CART_CHECKOUT,
    ]);
    $promotion->setRelation('rules', collect([
        ['type' => 'action', 'handler' => 'this_action_is_fake', 'configuration' => []],
    ]));

    mockPromotionFinderReturning($promotion);

    $data = new OrderCreateData(
        status: App\Enums\Order\OrderStatusEnum::PENDING->value,
        customer_id: $user->id,
        items: [new OrderItemCreateData(product_delivery_option_id: $deliveryOption->id, payment_type: 'full_payment')],
        applied_coupon_code: null
    );

    $service = app(OrderCalculationService::class);

    // Act & Assert
    expect(fn () => $service->calculate($data))
        ->toThrow(RuntimeException::class, "No discount action handler registered for 'this_action_is_fake'");
});

test('it throws an exception if a delivery option ID does not exist', function (): void {
    $user = User::factory()->create();

    $validOption = ProductDeliveryOption::factory()->create();

    $data = new OrderCreateData(
        status: App\Enums\Order\OrderStatusEnum::PENDING->value,
        customer_id: $user->id,
        items: [
            new OrderItemCreateData(product_delivery_option_id: $validOption->id, payment_type: 'full_payment'),
            new OrderItemCreateData(product_delivery_option_id: 99999, payment_type: 'full_payment'),
        ],
        applied_coupon_code: null
    );

    $service = app(OrderCalculationService::class);

    expect(fn () => $service->calculate($data))
        ->toThrow(InvalidArgumentException::class, __('messages.order.delivery_options_not_found', ['ids' => '99999']));
});

test('it does not apply discount when promotion conditions fail', function (): void {
    // Arrange
    $user           = User::factory()->create();
    $deliveryOption = ProductDeliveryOption::factory()->create(['price' => 10000]);

    $promotion = DiscountPromotion::factory()->make([
        'type' => DiscountTypeEnum::CART_CHECKOUT,
    ]);
    $promotion->setRelation('rules', collect([
        ['type' => 'condition', 'handler' => 'cart_value_over', 'configuration' => ['value' => 20000, 'operator' => '>=', 'measure_whole_commitment' => true]],
        ['type' => 'action', 'handler' => 'apply_percentage_off', 'configuration' => ['percentage' => 20]],
    ]));

    mockPromotionFinderReturning($promotion);

    $data = new OrderCreateData(
        status: App\Enums\Order\OrderStatusEnum::PENDING->value,
        customer_id: $user->id,
        items: [new OrderItemCreateData(product_delivery_option_id: $deliveryOption->id, payment_type: 'full_payment')],
        applied_coupon_code: null
    );

    $service = app(OrderCalculationService::class);

    // Act
    $context = $service->calculate($data);

    // Assert
    expect($context->items[0]->discount_amount)->toBe(0);
    expect($context->items[0]->total)->toBe(10000);
    expect($context->evaluating_promotion)->toBeNull();
});

test('it attributes the coupon code only to the coupon-required promotion in the audit trail', function (): void {
    // Arrange
    $user           = User::factory()->create();
    $deliveryOption = ProductDeliveryOption::factory()->create(['price' => 10000]);

    // Automatic promotion (requires_coupon = false): applied whenever conditions pass.
    $automaticPromotion = DiscountPromotion::factory()->make([
        'id'              => 200,
        'type'            => DiscountTypeEnum::CART_CHECKOUT,
        'requires_coupon' => false,
        'priority'        => 1,
    ]);
    $automaticPromotion->setRelation('rules', collect([
        ['type' => 'action', 'handler' => 'apply_percentage_off', 'configuration' => ['percentage' => 10]],
    ]));

    // Coupon-required promotion that owns the applied coupon code.
    $couponPromotion = DiscountPromotion::factory()->make([
        'id'              => 201,
        'type'            => DiscountTypeEnum::CART_CHECKOUT,
        'requires_coupon' => true,
        'priority'        => 2,
    ]);
    $couponPromotion->setRelation('rules', collect([
        ['type' => 'action', 'handler' => 'apply_percentage_off', 'configuration' => ['percentage' => 20]],
    ]));

    mockPromotionFinderReturning([$automaticPromotion, $couponPromotion]);

    $data = new OrderCreateData(
        status: App\Enums\Order\OrderStatusEnum::PENDING->value,
        customer_id: $user->id,
        items: [new OrderItemCreateData(product_delivery_option_id: $deliveryOption->id, payment_type: 'full_payment')],
        applied_coupon_code: 'SAVE20',
    );

    $service = app(OrderCalculationService::class);

    // Act
    $context = $service->calculate($data);

    // Assert: both promotions applied a discount...
    expect($context->applied_cart_discounts)->toHaveCount(2);

    $byPromotion = collect($context->applied_cart_discounts)->keyBy('promotion_id');

    // ...but only the coupon-required promotion carries the coupon in the audit trail.
    expect($byPromotion[$automaticPromotion->id]['coupon_code'])->toBeNull();
    expect($byPromotion[$couponPromotion->id]['coupon_code'])->toBe('SAVE20');

    // Order-level coupon attribution is preserved (the coupon promotion is processed last).
    expect($context->triggered_by_coupon_code)->toBe('SAVE20');
});

test('a later automatic promotion cannot erase the code an earlier one consumed', function (): void {
    $user           = User::factory()->create();
    $deliveryOption = ProductDeliveryOption::factory()->create(['price' => 10000]);

    // The coupon-required promotion runs FIRST, so the automatic one is the
    // last to touch the context.
    $couponPromotion = DiscountPromotion::factory()->make([
        'id'              => 300,
        'type'            => DiscountTypeEnum::CART_CHECKOUT,
        'requires_coupon' => true,
        'priority'        => 1,
    ]);
    $couponPromotion->setRelation('rules', collect([
        ['type' => 'action', 'handler' => 'apply_percentage_off', 'configuration' => ['percentage' => 20]],
    ]));

    $automaticPromotion = DiscountPromotion::factory()->make([
        'id'              => 301,
        'type'            => DiscountTypeEnum::CART_CHECKOUT,
        'requires_coupon' => false,
        'priority'        => 2,
    ]);
    $automaticPromotion->setRelation('rules', collect([
        ['type' => 'action', 'handler' => 'apply_percentage_off', 'configuration' => ['percentage' => 10]],
    ]));

    mockPromotionFinderReturning([$couponPromotion, $automaticPromotion]);

    $data = new OrderCreateData(
        status: App\Enums\Order\OrderStatusEnum::PENDING->value,
        customer_id: $user->id,
        items: [new OrderItemCreateData(product_delivery_option_id: $deliveryOption->id, payment_type: 'full_payment')],
        applied_coupon_code: 'SAVE20',
    );

    $context     = app(OrderCalculationService::class)->calculate($data);
    $byPromotion = collect($context->applied_cart_discounts)->keyBy('promotion_id');

    expect($context->applied_cart_discounts)->toHaveCount(2)
        ->and($byPromotion[$couponPromotion->id]['coupon_code'])->toBe('SAVE20')
        // The automatic promotion never inherits the code...
        ->and($byPromotion[$automaticPromotion->id]['coupon_code'])->toBeNull()
        // ...and it must not erase it either: CreateOrderAction persists this
        // field into orders.applied_coupon_code, and the coupon usage counter
        // resolves the coupon from that same column.
        ->and($context->triggered_by_coupon_code)->toBe('SAVE20');
});

test('it does not crash or grant credit when a wallet-credit promotion is applied to a guest cart', function (): void {
    // Arrange
    $deliveryOption = ProductDeliveryOption::factory()->create(['price' => 10000]);

    $promotion = DiscountPromotion::factory()->make([
        'type' => DiscountTypeEnum::CART_CHECKOUT,
    ]);
    $promotion->setRelation('rules', collect([
        ['type' => 'action', 'handler' => 'add_wallet_credit', 'configuration' => ['amount' => 5000, 'per_item' => false]],
    ]));

    mockPromotionFinderReturning($promotion);

    // Guest cart: customer_id is null.
    $data = new OrderCreateData(
        status: App\Enums\Order\OrderStatusEnum::PENDING->value,
        customer_id: null,
        items: [new OrderItemCreateData(product_delivery_option_id: $deliveryOption->id, payment_type: 'full_payment')],
        applied_coupon_code: null
    );

    $service = app(OrderCalculationService::class);

    // Act & Assert: no fatal error on null customer, no wallet credit granted, normal totals.
    expect(fn () => $service->calculate($data))->not->toThrow(Exception::class);

    $context = $service->calculate($data);
    expect($context->customer)->toBeNull();
    expect(App\Models\WalletTransaction::count())->toBe(0);
    expect($context->items[0]->discount_amount)->toBe(0);
    expect($context->items[0]->total)->toBe(10000);
});

test('it does not crash or grant credit when a gift-credit promotion is applied to a guest cart', function (): void {
    // Arrange
    $deliveryOption = ProductDeliveryOption::factory()->create(['price' => 10000]);

    $promotion = DiscountPromotion::factory()->make([
        'type' => DiscountTypeEnum::CART_CHECKOUT,
    ]);
    $promotion->setRelation('rules', collect([
        ['type' => 'action', 'handler' => 'add_gift_credit', 'configuration' => ['amount' => 3000, 'per_item' => false]],
    ]));

    mockPromotionFinderReturning($promotion);

    // Guest cart: customer_id is null.
    $data = new OrderCreateData(
        status: App\Enums\Order\OrderStatusEnum::PENDING->value,
        customer_id: null,
        items: [new OrderItemCreateData(product_delivery_option_id: $deliveryOption->id, payment_type: 'full_payment')],
        applied_coupon_code: null
    );

    $service = app(OrderCalculationService::class);

    // Act & Assert: no fatal error on null customer, no gift credit granted, normal totals.
    expect(fn () => $service->calculate($data))->not->toThrow(Exception::class);

    $context = $service->calculate($data);
    expect($context->customer)->toBeNull();
    expect(App\Models\WalletTransaction::count())->toBe(0);
    expect($context->items[0]->discount_amount)->toBe(0);
    expect($context->items[0]->total)->toBe(10000);
});

test('it stops applying subsequent cart promotions when stop_processing_subsequent_rules is enabled', function (): void {
    $user           = User::factory()->create();
    $deliveryOption = ProductDeliveryOption::factory()->create(['price' => 10000]);

    $firstPromotion = DiscountPromotion::factory()->make([
        'id'                               => 100,
        'type'                             => DiscountTypeEnum::CART_CHECKOUT,
        'stop_processing_subsequent_rules' => true,
        'priority'                         => 1,
    ]);
    $firstPromotion->setRelation('rules', collect([
        ['type' => 'action', 'handler' => 'apply_percentage_off', 'configuration' => ['percentage' => 10]],
    ]));

    $secondPromotion = DiscountPromotion::factory()->make([
        'id'                               => 101,
        'type'                             => DiscountTypeEnum::CART_CHECKOUT,
        'stop_processing_subsequent_rules' => false,
        'priority'                         => 2,
    ]);
    $secondPromotion->setRelation('rules', collect([
        ['type' => 'action', 'handler' => 'apply_percentage_off', 'configuration' => ['percentage' => 50]],
    ]));

    mockPromotionFinderReturning([$firstPromotion, $secondPromotion]);

    $data = new OrderCreateData(
        status: App\Enums\Order\OrderStatusEnum::PENDING->value,
        customer_id: $user->id,
        items: [new OrderItemCreateData(product_delivery_option_id: $deliveryOption->id, payment_type: 'full_payment')],
        applied_coupon_code: null,
    );

    $service = app(OrderCalculationService::class);
    $context = $service->calculate($data);

    expect($context->items[0]->discount_amount)->toBe(1000)
        ->and($context->items[0]->total)->toBe(9000)
        ->and($context->applied_cart_discounts)->toHaveCount(1)
        ->and($context->applied_cart_discounts[0]['promotion_id'])->toBe(100);
});

test('a gift line measures the same threshold whichever promotion runs first', function (string $first): void {
    $user         = User::factory()->create();
    $courseOption = ProductDeliveryOption::factory()->create(['price' => 100000, 'is_prepayment_available' => false]);
    $giftOption   = ProductDeliveryOption::factory()->create(['price' => 50000, 'is_prepayment_available' => false]);

    $giftPromotion = DiscountPromotion::factory()->make([
        'id'       => 200,
        'type'     => DiscountTypeEnum::CART_CHECKOUT,
        'priority' => 1,
    ]);
    $giftPromotion->setRelation('rules', collect([
        ['type' => 'action', 'handler' => 'gift_product', 'configuration' => ['product_delivery_option_id' => $giftOption->id]],
    ]));

    // 120,000 sits between the cart's discountable worth (100,000) and the
    // value the gift's *list* price would add (150,000), so the measured
    // amount alone decides this condition.
    $valuePromotion = DiscountPromotion::factory()->make([
        'id'       => 201,
        'type'     => DiscountTypeEnum::CART_CHECKOUT,
        'priority' => 2,
    ]);
    $valuePromotion->setRelation('rules', collect([
        [
            'type'          => 'condition',
            'handler'       => 'cart_value_over',
            'configuration' => ['operator' => '>=', 'value' => 120000, 'measure_whole_commitment' => false],
        ],
        ['type' => 'action', 'handler' => 'apply_percentage_off', 'configuration' => ['percentage' => 10]],
    ]));

    mockPromotionFinderReturning($first === 'gift first'
        ? [$giftPromotion, $valuePromotion]
        : [$valuePromotion, $giftPromotion]);

    $data = new OrderCreateData(
        status: App\Enums\Order\OrderStatusEnum::PENDING->value,
        customer_id: $user->id,
        items: [new OrderItemCreateData(product_delivery_option_id: $courseOption->id, payment_type: 'full_payment')],
        applied_coupon_code: null,
    );

    $context = app(OrderCalculationService::class)->calculate($data);
    $course  = $context->items->first(fn ($item): bool => ! $item->is_gift);

    // The gift is free, so it is worth 0 to the customer and must not carry the
    // cart over the threshold. Before the measurement became a build-time
    // snapshot, running the gift first pushed the measured full-payment total
    // to 150,000 and the 10% markdown fired only in that order.
    expect($context->effective_subtotal_discountable_items)->toBe(100000)
        ->and($context->subtotal_full_payment_items)->toBe(150000)
        ->and($course->discount_amount)->toBe(0)
        ->and($course->total)->toBe(100000);
})->with(['value condition first', 'gift first']);
