<?php

declare(strict_types=1);

use App\Data\Admin\Order\OrderCreateData;
use App\Data\Admin\Order\OrderItemCreateData;
use App\Enums\Order\DiscountTypeEnum;
use App\Enums\Order\OrderStatusEnum;
use App\Models\Bundle;
use App\Models\DiscountCoupon;
use App\Models\DiscountPromotion;
use App\Models\DiscountPromotionRule;
use App\Models\DiscountPromotionUsage;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductDeliveryOption;
use App\Models\User;
use App\Services\Discounts\OrderCalculationService;
use App\Services\Discounts\PromotionService;

it('finds an active promotion by coupon code', function (): void {
    // Arrange
    $promotion = DiscountPromotion::factory()->create(['is_active' => true]);
    DiscountCoupon::factory()->create(['discount_promotion_id' => $promotion->id, 'code' => 'VALID']);

    // Act
    $found = app(PromotionService::class)->findPromotionByCoupon('VALID');

    // Assert
    expect($found)->not->toBeNull()
        ->and($found->id)->toBe($promotion->id);
});

it('does not find an inactive promotion', function (): void {
    $promotion = DiscountPromotion::factory()->create(['is_active' => false]);
    DiscountCoupon::factory()->create(['discount_promotion_id' => $promotion->id, 'code' => 'INACTIVE']);

    $found = app(PromotionService::class)->findPromotionByCoupon('INACTIVE');

    expect($found)->toBeNull();
});

it('does not find an expired promotion', function (): void {
    $promotion = DiscountPromotion::factory()->create(['ends_at' => now()->subDay()]);
    DiscountCoupon::factory()->create(['discount_promotion_id' => $promotion->id, 'code' => 'EXPIRED']);

    $found = app(PromotionService::class)->findPromotionByCoupon('EXPIRED');

    expect($found)->toBeNull();
});

it('does not return coupon-required promotions when no coupon is provided', function (): void {
    $couponRequiredPromotion = DiscountPromotion::factory()->create([
        'type'            => DiscountTypeEnum::CART_CHECKOUT,
        'is_active'       => true,
        'requires_coupon' => true,
        'priority'        => 1,
        'starts_at'       => now()->subDay(),
        'ends_at'         => now()->addDay(),
    ]);

    DiscountCoupon::factory()->create([
        'discount_promotion_id' => $couponRequiredPromotion->id,
        'code'                  => 'SAVEONLY',
        'is_active'             => true,
    ]);

    $publicPromotion = DiscountPromotion::factory()->create([
        'type'            => DiscountTypeEnum::CART_CHECKOUT,
        'is_active'       => true,
        'requires_coupon' => false,
        'priority'        => 10,
        'starts_at'       => now()->subDay(),
        'ends_at'         => now()->addDay(),
    ]);

    $promotions = app(PromotionService::class)->findAllApplicableCartPromotions(null);

    expect($promotions->pluck('id'))->toContain($publicPromotion->id)
        ->and($promotions->pluck('id'))->not->toContain($couponRequiredPromotion->id);
});

it('orders all applicable promotions purely by priority ascending regardless of coupon requirement', function (): void {
    $nonCouponHighPriority = DiscountPromotion::factory()->create([
        'type'            => DiscountTypeEnum::CART_CHECKOUT,
        'is_active'       => true,
        'requires_coupon' => false,
        'priority'        => 1,
        'starts_at'       => now()->subDay(),
        'ends_at'         => now()->addDay(),
    ]);

    $nonCouponLowPriority = DiscountPromotion::factory()->create([
        'type'            => DiscountTypeEnum::CART_CHECKOUT,
        'is_active'       => true,
        'requires_coupon' => false,
        'priority'        => 3,
        'starts_at'       => now()->subDay(),
        'ends_at'         => now()->addDay(),
    ]);

    $couponHighPriority = DiscountPromotion::factory()->create([
        'type'            => DiscountTypeEnum::CART_CHECKOUT,
        'is_active'       => true,
        'requires_coupon' => true,
        'priority'        => 0,
        'starts_at'       => now()->subDay(),
        'ends_at'         => now()->addDay(),
    ]);

    DiscountCoupon::factory()->create([
        'discount_promotion_id' => $couponHighPriority->id,
        'code'                  => 'ORDER10',
        'is_active'             => true,
    ]);

    $promotions = app(PromotionService::class)->findAllApplicableCartPromotions('ORDER10');

    expect($promotions->pluck('id')->all())->toBe([
        $couponHighPriority->id,
        $nonCouponHighPriority->id,
        $nonCouponLowPriority->id,
    ]);
});

it('lets a high-priority coupon promotion with stop_processing_subsequent_rules override a lower-priority automatic promotion', function (): void {
    $user           = User::factory()->create();
    $deliveryOption = ProductDeliveryOption::factory()->create(['price' => 10000]);

    $automaticPromotion = DiscountPromotion::factory()->create([
        'name'                             => 'Blanket 10% Campaign',
        'type'                             => DiscountTypeEnum::CART_CHECKOUT,
        'is_active'                        => true,
        'requires_coupon'                  => false,
        'priority'                         => 3,
        'stop_processing_subsequent_rules' => false,
        'starts_at'                        => now()->subDay(),
        'ends_at'                          => now()->addDay(),
    ]);
    DiscountPromotionRule::create([
        'discount_promotion_id' => $automaticPromotion->id,
        'type'                  => 'action',
        'handler'               => 'apply_percentage_off',
        'configuration'         => ['percentage' => 10],
    ]);

    $couponPromotion = DiscountPromotion::factory()->create([
        'name'                             => 'VIP 30% Coupon',
        'type'                             => DiscountTypeEnum::CART_CHECKOUT,
        'is_active'                        => true,
        'requires_coupon'                  => true,
        'priority'                         => 0,
        'stop_processing_subsequent_rules' => true,
        'starts_at'                        => now()->subDay(),
        'ends_at'                          => now()->addDay(),
    ]);
    DiscountPromotionRule::create([
        'discount_promotion_id' => $couponPromotion->id,
        'type'                  => 'action',
        'handler'               => 'apply_percentage_off',
        'configuration'         => ['percentage' => 30],
    ]);
    DiscountCoupon::factory()->create([
        'discount_promotion_id' => $couponPromotion->id,
        'code'                  => 'OVERRIDE',
        'is_active'             => true,
    ]);

    $data = new OrderCreateData(
        status: OrderStatusEnum::PENDING->value,
        customer_id: $user->id,
        items: [
            new OrderItemCreateData(
                product_delivery_option_id: $deliveryOption->id,
                payment_type: 'full_payment',
            ),
        ],
        applied_coupon_code: 'OVERRIDE',
    );

    $context = app(OrderCalculationService::class)->calculate($data);

    // Coupon runs first (priority 0) and stops stacking, so the automatic
    // 10% campaign never applies.
    expect($context->items[0]->discount_amount)->toBe(3000)
        ->and($context->items[0]->total)->toBe(7000)
        ->and($context->applied_cart_discounts)->toHaveCount(1)
        ->and($context->applied_cart_discounts[0]['promotion_id'])->toBe($couponPromotion->id)
        ->and($context->applied_cart_discounts[0]['coupon_code'])->toBe('OVERRIDE');
});

it('keeps a Bundle line at its reviewed selling price while an automatic cart promotion discounts the standalone line', function (): void {
    $user          = User::factory()->create();
    $standalone    = ProductDeliveryOption::factory()->create(['price' => 100000]);
    $bundle        = Bundle::factory()->create();
    $bundleProduct = Product::factory()->create([
        'productable_type' => App\Enums\Product\ProductableEnum::BUNDLE->value,
        'productable_id'   => $bundle->id,
        'status'           => App\Enums\Content\PublicationStatusEnum::PUBLISHED,
    ]);
    $bundleOption = ProductDeliveryOption::factory()->create([
        'product_id'       => $bundleProduct->id,
        'fulfillment_type' => App\Enums\Product\FulfillmentTypeEnum::COMPOSITE,
        'delivery_method'  => App\Enums\Product\DeliveryMethodEnum::BUNDLE,
        'details_json'     => [],
        'price'            => 500000,
        'status'           => App\Enums\Content\PublicationStatusEnum::PUBLISHED,
    ]);

    $promotion = DiscountPromotion::factory()->create([
        'type'            => DiscountTypeEnum::CART_CHECKOUT,
        'is_active'       => true,
        'requires_coupon' => false,
        'priority'        => 1,
        'starts_at'       => now()->subDay(),
        'ends_at'         => now()->addDay(),
    ]);
    DiscountPromotionRule::create([
        'discount_promotion_id' => $promotion->id,
        'type'                  => 'action',
        'handler'               => 'apply_percentage_off',
        'configuration'         => ['percentage' => 10],
    ]);

    $data = new OrderCreateData(
        status: OrderStatusEnum::PENDING->value,
        customer_id: $user->id,
        items: [
            new OrderItemCreateData(product_delivery_option_id: $standalone->id, payment_type: 'full_payment'),
            new OrderItemCreateData(product_delivery_option_id: $bundleOption->id, payment_type: 'full_payment'),
        ],
        applied_coupon_code: null,
    );

    $context = app(OrderCalculationService::class)->calculate($data);

    expect($context->items[0]->is_bundle)->toBeFalse()
        ->and($context->items[0]->discount_amount)->toBe(10000) // 10% of 100000
        ->and($context->items[0]->total)->toBe(90000)
        ->and($context->items[1]->is_bundle)->toBeTrue()
        ->and($context->items[1]->discount_amount)->toBe(0)
        ->and($context->items[1]->total)->toBe(500000);
});

it('does not let a tier selection reach the next promotion when that promotion has no tier condition', function (): void {
    $user           = User::factory()->create();
    $deliveryOption = ProductDeliveryOption::factory()->create(['price' => 10000]);

    // Priority 1: a proper tiered campaign publishes and consumes its selection.
    $tieredPromotion = DiscountPromotion::factory()->create([
        'type'            => DiscountTypeEnum::CART_CHECKOUT,
        'is_active'       => true,
        'requires_coupon' => false,
        'priority'        => 1,
        'starts_at'       => now()->subDay(),
        'ends_at'         => now()->addDay(),
    ]);
    DiscountPromotionRule::create([
        'discount_promotion_id' => $tieredPromotion->id,
        'type'                  => 'condition',
        'handler'               => 'cart_value_tier',
        'configuration'         => ['tiers' => [['min_amount' => 0, 'percentage' => 10]]],
    ]);
    DiscountPromotionRule::create([
        'discount_promotion_id' => $tieredPromotion->id,
        'type'                  => 'action',
        'handler'               => 'apply_tiered_percentage_off',
        'configuration'         => [],
    ]);

    // Priority 2: rules persisted before the pairing validation existed, so the
    // consuming action is present with no tier condition to publish for it.
    $legacyPromotion = DiscountPromotion::factory()->create([
        'type'            => DiscountTypeEnum::CART_CHECKOUT,
        'is_active'       => true,
        'requires_coupon' => false,
        'priority'        => 2,
        'starts_at'       => now()->subDay(),
        'ends_at'         => now()->addDay(),
    ]);
    DiscountPromotionRule::create([
        'discount_promotion_id' => $legacyPromotion->id,
        'type'                  => 'action',
        'handler'               => 'apply_tiered_percentage_off',
        'configuration'         => [],
    ]);

    $data = new OrderCreateData(
        status: OrderStatusEnum::PENDING->value,
        customer_id: $user->id,
        items: [
            new OrderItemCreateData(product_delivery_option_id: $deliveryOption->id, payment_type: 'full_payment'),
        ],
        applied_coupon_code: null,
    );

    $context = app(OrderCalculationService::class)->calculate($data);

    // Only the tiered campaign marks the line down; the action-only promotion
    // must not inherit its percentage.
    expect($context->items[0]->discount_amount)->toBe(1000)
        ->and($context->items[0]->total)->toBe(9000)
        ->and($context->applied_cart_discounts)->toHaveCount(1)
        ->and($context->applied_cart_discounts[0]['promotion_id'])->toBe($tieredPromotion->id);
});

/**
 * Hold one reservation slot for a promotion (and coupon, when given) through a
 * fresh pending order.
 */
function reservePromotionUsageSlot(DiscountPromotion $promotion, ?DiscountCoupon $coupon = null): DiscountPromotionUsage
{
    $holdingOrder = Order::factory()->create(['status' => OrderStatusEnum::PENDING->value]);

    return DiscountPromotionUsage::create([
        'discount_promotion_id' => $promotion->id,
        'discount_coupon_id'    => $coupon?->id,
        'customer_id'           => $holdingOrder->customer_id,
        'order_id'              => $holdingOrder->id,
    ]);
}

it('drops an automatic promotion from the applicable cart promotions once its total cap is fully reserved', function (): void {
    $promotion = DiscountPromotion::factory()->create([
        'type'              => DiscountTypeEnum::CART_CHECKOUT,
        'is_active'         => true,
        'requires_coupon'   => false,
        'usage_limit_total' => 1,
        'starts_at'         => now()->subDay(),
        'ends_at'           => now()->addDay(),
    ]);

    reservePromotionUsageSlot($promotion);

    $promotions = app(PromotionService::class)->findAllApplicableCartPromotions(null, User::factory()->create()->id);

    expect($promotions->pluck('id'))->not->toContain($promotion->id);
});

it('keeps an automatic promotion applicable while its reserved capacity is below the total cap', function (): void {
    $promotion = DiscountPromotion::factory()->create([
        'type'              => DiscountTypeEnum::CART_CHECKOUT,
        'is_active'         => true,
        'requires_coupon'   => false,
        'usage_limit_total' => 2,
        'starts_at'         => now()->subDay(),
        'ends_at'           => now()->addDay(),
    ]);

    reservePromotionUsageSlot($promotion);

    $promotions = app(PromotionService::class)->findAllApplicableCartPromotions(null, User::factory()->create()->id);

    expect($promotions->pluck('id'))->toContain($promotion->id);
});

it('does not find a promotion by coupon while its total cap is fully reserved', function (): void {
    $promotion = DiscountPromotion::factory()->create([
        'is_active'         => true,
        'usage_limit_total' => 1,
    ]);
    $coupon = DiscountCoupon::factory()->create([
        'discount_promotion_id' => $promotion->id,
        'code'                  => 'TOTALCAP',
    ]);

    reservePromotionUsageSlot($promotion);

    expect(app(PromotionService::class)->findPromotionByCoupon($coupon->code))->toBeNull();
});

it('does not find a coupon code whose own cap is fully reserved', function (): void {
    $promotion = DiscountPromotion::factory()->create(['is_active' => true]);
    $coupon    = DiscountCoupon::factory()->create([
        'discount_promotion_id' => $promotion->id,
        'code'                  => 'COUPONCAP',
        'usage_limit'           => 1,
    ]);

    reservePromotionUsageSlot($promotion, $coupon);

    expect(app(PromotionService::class)->findPromotionByCoupon($coupon->code))->toBeNull();
});

it('finds a coupon code while its reserved capacity is below the cap', function (): void {
    $promotion = DiscountPromotion::factory()->create(['is_active' => true]);
    $coupon    = DiscountCoupon::factory()->create([
        'discount_promotion_id' => $promotion->id,
        'code'                  => 'COUPONROOM',
        'usage_limit'           => 2,
    ]);

    reservePromotionUsageSlot($promotion, $coupon);

    $found = app(PromotionService::class)->findPromotionByCoupon($coupon->code);

    expect($found?->id)->toBe($promotion->id);
});

it('drops a coupon-gated promotion from the applicable cart promotions once the supplied code cap is fully reserved', function (): void {
    $promotion = DiscountPromotion::factory()->create([
        'type'            => DiscountTypeEnum::CART_CHECKOUT,
        'is_active'       => true,
        'requires_coupon' => true,
        'starts_at'       => now()->subDay(),
        'ends_at'         => now()->addDay(),
    ]);
    $coupon = DiscountCoupon::factory()->create([
        'discount_promotion_id' => $promotion->id,
        'code'                  => 'GATEDCAP',
        'usage_limit'           => 1,
    ]);

    reservePromotionUsageSlot($promotion, $coupon);

    $promotions = app(PromotionService::class)->findAllApplicableCartPromotions('GATEDCAP', User::factory()->create()->id);

    expect($promotions->pluck('id'))->not->toContain($promotion->id);
});

it('frees reserved cap capacity once the holding reservation expires', function (): void {
    $promotion = DiscountPromotion::factory()->create([
        'type'              => DiscountTypeEnum::CART_CHECKOUT,
        'is_active'         => true,
        'requires_coupon'   => false,
        'usage_limit_total' => 1,
        'starts_at'         => now()->subDay(),
        'ends_at'           => now()->addDay(),
    ]);

    reservePromotionUsageSlot($promotion);

    $this->travel(31)->minutes();

    $promotions = app(PromotionService::class)->findAllApplicableCartPromotions(null, User::factory()->create()->id);

    expect($promotions->pluck('id'))->toContain($promotion->id);
});

it('does not gate a cap on the completion counters', function (): void {
    $automaticPromotion = DiscountPromotion::factory()->create([
        'type'              => DiscountTypeEnum::CART_CHECKOUT,
        'is_active'         => true,
        'requires_coupon'   => false,
        'usage_limit_total' => 1,
        'total_usage_count' => 5,
        'starts_at'         => now()->subDay(),
        'ends_at'           => now()->addDay(),
    ]);

    $couponPromotion = DiscountPromotion::factory()->create([
        'type'            => DiscountTypeEnum::CART_CHECKOUT,
        'is_active'       => true,
        'requires_coupon' => true,
        'starts_at'       => now()->subDay(),
        'ends_at'         => now()->addDay(),
    ]);
    $coupon = DiscountCoupon::factory()->create([
        'discount_promotion_id' => $couponPromotion->id,
        'code'                  => 'COUNTERONLY',
        'usage_limit'           => 1,
        'usage_count'           => 5,
    ]);

    $automaticPromotions = app(PromotionService::class)->findAllApplicableCartPromotions(null, User::factory()->create()->id);

    expect($automaticPromotions->pluck('id'))->toContain($automaticPromotion->id);

    // The counters are reporting values: with no reservations holding the
    // capacity, the code is still found.
    expect(app(PromotionService::class)->findPromotionByCoupon($coupon->code)?->id)->toBe($couponPromotion->id);
});
