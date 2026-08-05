<?php

declare(strict_types=1);

use App\Enums\Order\DiscountTypeEnum;
use App\Models\DiscountCoupon;
use App\Models\DiscountPromotion;
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

it('orders non-coupon promotions before coupon promotions, then by priority in each group', function (): void {
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
        $nonCouponHighPriority->id,
        $nonCouponLowPriority->id,
        $couponHighPriority->id,
    ]);
});
