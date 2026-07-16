<?php

declare(strict_types=1);

use App\Data\Admin\Discounts\OrderContextData;
use App\Data\Admin\Order\OrderCreateData;
use App\Data\Admin\Order\OrderItemCreateData;
use App\Enums\Order\DiscountTypeEnum;
use App\Models\DiscountPromotion;
use App\Models\DiscountPromotionRule;
use App\Models\ProductDeliveryOption;
use App\Models\ProductDeliveryOptionDiscountPrice;
use App\Models\User;
use App\Services\Discounts\Cart\Actions\ApplyPercentageDiscountToItemsAction;
use App\Services\Discounts\Cart\Conditions\CartValueCondition;
use App\Services\Discounts\DiscountHandlerRegistry;
use App\Services\Discounts\OrderCalculationService;
use App\Services\Discounts\PromotionService;
use App\Services\ProductPriceService;
use Mockery\MockInterface;

/**
 * Helper: create a partial PromotionService mock with real context-building
 * and condition-checking, but a controlled findAllApplicableCartPromotions.
 */
function mockPromotionFinderReturning(mixed $promotions): void
{
    $mock = Mockery::mock(PromotionService::class, [
        app(DiscountHandlerRegistry::class),
        app(ProductPriceService::class),
    ])->makePartial();
    $mock->shouldReceive('findAllApplicableCartPromotions')->andReturn(
        $promotions instanceof DiscountPromotion ? collect([$promotions]) : collect()
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
        'configuration'         => json_encode(['value' => 10000, 'operator' => '>=', 'include_prepayments' => true]),
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
        ['type' => 'condition', 'handler' => 'cart_value_over', 'configuration' => ['value' => 20000, 'operator' => '>=', 'include_prepayments' => true]],
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
