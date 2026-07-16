<?php

declare(strict_types=1);

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
