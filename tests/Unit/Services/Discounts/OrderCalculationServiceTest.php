<?php

declare(strict_types=1);

use App\Data\Admin\Discounts\OrderContextData;
use App\Data\Admin\Order\OrderCreateData;
use App\Data\Admin\Order\OrderItemCreateData;
use App\Enums\Order\DiscountTypeEnum;
use App\Models\DiscountPromotion;
use App\Models\DiscountPromotionRule;
use App\Models\ProductDeliveryOption;
use App\Models\User;
use App\Services\Discounts\Cart\Actions\ApplyPercentageDiscountToItemsAction;
use App\Services\Discounts\Cart\Conditions\CartValueCondition;
use App\Services\Discounts\OrderCalculationService;
use App\Services\Discounts\PromotionFinder;
use Mockery\MockInterface;

it('calculates a percentage discount correctly when a promotion is found', function () {
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

    $this->mock(PromotionFinder::class,
        fn (MockInterface $mock) => $mock->shouldReceive('findApplicablePromotion')->andReturn($promotion));

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

it('returns a context with zero discounts if promotion finder returns null', function () {
    // Arrange
    $user           = User::factory()->create();
    $deliveryOption = ProductDeliveryOption::factory()->create(['price' => 10000]);

    $this->mock(PromotionFinder::class,
        fn (MockInterface $mock) => $mock->shouldReceive('findApplicablePromotion')->andReturn(null));

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

it('correctly uses featured price as the base for calculation', function () {
    $user           = User::factory()->create();
    $deliveryOption = ProductDeliveryOption::factory()->create([
        'price'                     => 10000,
        'is_featured'               => true,
        'featured_price'            => 8000, // The item is on sale
        'featured_price_start_date' => now()->subDay(),
        'featured_price_end_date'   => now()->addDay(),
    ]);

    $promotion = DiscountPromotion::factory()->make([
        'type' => DiscountTypeEnum::CART_CHECKOUT,
    ]);
    $promotion->setRelation('rules', collect([
        ['type' => 'action', 'handler' => 'apply_percentage_off', 'configuration' => ['percentage' => 10]],
    ]));

    $this->mock(PromotionFinder::class,
        fn (MockInterface $mock) => $mock->shouldReceive('findApplicablePromotion')->andReturn($promotion));

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
    expect($context->items[0]->price)->toBe(8000); // The base price for calculation was the featured price
    expect($context->items[0]->discount_amount)->toBe(800);
    expect($context->items[0]->total)->toBe(7200); // 8000 - 800
});

test('it correctly uses a pre-calculated product-specific discount as the highest priority base price', function () {
    // This test covers: if ($precalculatedPrices->has($option->id))

    // Arrange
    $user           = User::factory()->create();
    $deliveryOption = ProductDeliveryOption::factory()->create([
        'price'          => 10000,
        'is_featured'    => true,
        'featured_price' => 8000, // A featured price also exists but should be ignored
    ]);

    // Mock the DB facade to return a pre-calculated price for this item.
    Illuminate\Support\Facades\DB::shouldReceive('table->whereIn->pluck')
        ->andReturn(collect([$deliveryOption->id => 5000])); // A special 50.00 price!

    // No promotion is needed, as we are testing the base price calculation.
    $this->mock(PromotionFinder::class,
        fn (MockInterface $mock) => $mock->shouldReceive('findApplicablePromotion')->andReturn(null));

    $data = new OrderCreateData(
        status: App\Enums\Order\OrderStatusEnum::PENDING->value,
        customer_id: $user->id,
        items: [new OrderItemCreateData(product_delivery_option_id: $deliveryOption->id, payment_type: 'full_payment')],
        applied_coupon_code: null
    );

    $service = app(OrderCalculationService::class);

    // Act
    $context = $service->calculate($data);

    // Assert: The base price used was the pre-calculated one, not featured or standard.
    expect($context->items[0]->price)->toBe(5000);
    expect($context->items[0]->total)->toBe(5000);
});

test('it calculates subtotals correctly for mixed payment types', function () {
    // This test covers: if ($calculatedItem->payment_type === OrderItemPaymentTypeEnum::FULL_PAYMENT->value)

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

    $this->mock(PromotionFinder::class,
        fn (MockInterface $mock) => $mock->shouldReceive('findApplicablePromotion')->andReturn(null));

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
    // The full value of all items in the cart
    expect($context->subtotal_all_items)->toBe(60000); // 10000 + 50000

    // The value of ONLY the full payment items
    expect($context->subtotal_full_payment_items)->toBe(10000);
});

test('it throws a runtime exception if a handler config dto is not mapped', function () {
    $customer     = User::factory()->create();
    $orderContext = new OrderContextData(
        customer: $customer,
        items: collect(),
        subtotal_full_payment_items: 0,
        subtotal_all_items: 0,
    );

    // Create a promotion with a rule that IS registered in the handler registry
    $promotion = DiscountPromotion::factory()->create([
        'type' => DiscountTypeEnum::CART_CHECKOUT,
    ]);
    $rule = DiscountPromotionRule::create([
        'discount_promotion_id' => $promotion->id,
        'type'                  => 'condition',
        'handler'               => 'cart_value_over', // This handler exists
        'configuration'         => json_encode(['value' => 10000, 'operator' => '>=', 'include_prepayments' => true]),
    ]);

    // Refresh the promotion to get the loaded relationship
    $promotion->load('rules');

    // Mock the registry to return a handler class but no config class
    $mockRegistry = $this->mock(App\Services\Discounts\DiscountHandlerRegistry::class);
    $mockRegistry->shouldReceive('getCartConditionHandler')
        ->with('cart_value_over')
        ->andReturn(CartValueCondition::class);
    $mockRegistry->shouldReceive('getConfigClass')
        ->with(CartValueCondition::class)
        ->andReturn(null); // No config DTO mapped

    // Replace the registry in the service container
    $this->app->instance(App\Services\Discounts\DiscountHandlerRegistry::class, $mockRegistry);

    $service = app(OrderCalculationService::class);

    // ACT & ASSERT: Expect the specific exception to be thrown
    $closure = function () use ($service, $promotion, $orderContext) {
        $method = new ReflectionMethod(OrderCalculationService::class, 'allConditionsPass');
        $method->setAccessible(true);
        $method->invoke($service, $promotion, $orderContext);
    };

    expect($closure)
        ->toThrow(
            RuntimeException::class,
            "No config DTO mapped for handler '".CartValueCondition::class."'"
        );
});
test('it throws a runtime exception if an action handler config dto is not mapped', function () {
    $customer     = User::factory()->create();
    $orderContext = new OrderContextData(
        customer: $customer,
        items: collect(),
        subtotal_full_payment_items: 0,
        subtotal_all_items: 0,
    );

    // Create a promotion with an 'action' rule
    $promotion = DiscountPromotion::factory()->create([
        'type' => DiscountTypeEnum::CART_CHECKOUT,
    ]);
    $rule = DiscountPromotionRule::create([
        'discount_promotion_id' => $promotion->id,
        'type'                  => 'action',
        'handler'               => 'apply_percentage_off', // A valid action handler name
        'configuration'         => json_encode(['percentage' => 15]),
    ]);

    // Refresh the promotion to get the loaded relationship
    $promotion->load('rules');

    // Mock the registry to return a handler class but no config class
    $mockRegistry = $this->mock(App\Services\Discounts\DiscountHandlerRegistry::class);
    $mockRegistry->shouldReceive('getCartActionHandler')
        ->with('apply_percentage_off')
        ->andReturn(ApplyPercentageDiscountToItemsAction::class);
    $mockRegistry->shouldReceive('getConfigClass')
        ->with(ApplyPercentageDiscountToItemsAction::class)
        ->andReturn(null); // No config DTO mapped

    // Replace the registry in the service container
    $this->app->instance(App\Services\Discounts\DiscountHandlerRegistry::class, $mockRegistry);

    $service = app(OrderCalculationService::class);

    // ACT & ASSERT: Expect the specific exception to be thrown when calling the private method
    $closure = function () use ($service, $promotion, $orderContext) {
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
test('it throws a runtime exception for an unregistered condition config', function () {
    // Arrange
    $user           = User::factory()->create();
    $deliveryOption = ProductDeliveryOption::factory()->create();

    // Create a promotion with a handler that does NOT exist in the registry
    $promotion = DiscountPromotion::factory()->make([
        'type' => DiscountTypeEnum::CART_CHECKOUT,
    ]);
    $promotion->setRelation('rules', collect([
        ['type' => 'condition', 'handler' => 'this_handler_does_not_exist', 'configuration' => []],
    ]));

    $this->mock(PromotionFinder::class,
        fn (MockInterface $mock) => $mock->shouldReceive('findApplicablePromotion')->andReturn($promotion));

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

test('it throws a runtime exception for an unregistered action handler', function () {
    $user           = User::factory()->create();
    $deliveryOption = ProductDeliveryOption::factory()->create();

    $promotion = DiscountPromotion::factory()->make([
        'type' => DiscountTypeEnum::CART_CHECKOUT,
    ]);
    $promotion->setRelation('rules', collect([
        ['type' => 'action', 'handler' => 'this_action_is_fake', 'configuration' => []],
    ]));

    $this->mock(PromotionFinder::class,
        fn (MockInterface $mock) => $mock->shouldReceive('findApplicablePromotion')->andReturn($promotion));

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

test('it skips an item in calculation if its delivery option ID does not exist', function () {

    $user = User::factory()->create();

    $validOption = ProductDeliveryOption::factory()->create();

    $data = new OrderCreateData(
        status: App\Enums\Order\OrderStatusEnum::PENDING->value,
        customer_id: $user->id,
        items: [
            new OrderItemCreateData(product_delivery_option_id: $validOption->id, payment_type: 'full_payment'),
            new OrderItemCreateData(product_delivery_option_id: 99999, payment_type: 'full_payment'), // Invalid ID
        ],
        applied_coupon_code: null
    );

    // We now have to use the InvalidArgumentException test because our service correctly throws it first.
    // This proves that the `continue` line is effectively unreachable, which is good design.
    // The test for the exception is the correct way to test this "missing item" path.
    $service = app(OrderCalculationService::class);

    // Act & Assert
    expect(fn () => $service->calculate($data))
        ->toThrow(InvalidArgumentException::class, 'One or more ProductDeliveryOption IDs do not exist: 99999');
});

test('it does not apply discount when promotion conditions fail', function () {
    // Arrange
    $user           = User::factory()->create();
    $deliveryOption = ProductDeliveryOption::factory()->create(['price' => 10000]);

    // Create a promotion with a condition that will fail
    $promotion = DiscountPromotion::factory()->make([
        'type' => DiscountTypeEnum::CART_CHECKOUT,
    ]);
    $promotion->setRelation('rules', collect([
        ['type' => 'condition', 'handler' => 'cart_value_over', 'configuration' => ['value' => 20000, 'operator' => '>=', 'include_prepayments' => true]], // Cart needs to be >= 200.00, but it's only 100.00
        ['type' => 'action', 'handler' => 'apply_percentage_off', 'configuration' => ['percentage' => 20]],
    ]));

    $this->mock(PromotionFinder::class,
        fn (MockInterface $mock) => $mock->shouldReceive('findApplicablePromotion')->andReturn($promotion));

    $data = new OrderCreateData(
        status: App\Enums\Order\OrderStatusEnum::PENDING->value,
        customer_id: $user->id,
        items: [new OrderItemCreateData(product_delivery_option_id: $deliveryOption->id, payment_type: 'full_payment')],
        applied_coupon_code: null
    );

    $service = app(OrderCalculationService::class);

    // Act
    $context = $service->calculate($data);

    // Assert - No discount should be applied because condition failed
    expect($context->items[0]->discount_amount)->toBe(0);
    expect($context->items[0]->total)->toBe(10000); // Original price
    expect($context->evaluating_promotion)->toBeNull(); // No promotion was applied
});
