<?php

declare(strict_types=1);

use App\Data\Admin\Order\OrderCreateData;
use App\Data\Admin\Order\OrderItemCreateData;
use App\Enums\Order\DiscountTypeEnum;
use App\Enums\Order\OrderStatusEnum;
use App\Models\DiscountCoupon;
use App\Models\DiscountPromotion;
use App\Models\DiscountPromotionRule;
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
