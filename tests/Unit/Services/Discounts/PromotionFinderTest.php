<?php

declare(strict_types=1);

use App\Data\Admin\Order\OrderCreateData;
use App\Models\DiscountCoupon;
use App\Models\DiscountPromotion;
use App\Services\Discounts\PromotionFinder;

it('finds an active promotion by coupon code', function (): void {
    // Arrange
    $promotion = DiscountPromotion::factory()->create(['is_active' => true]);
    $coupon    = DiscountCoupon::factory()->create(['discount_promotion_id' => $promotion->id, 'code' => 'VALID']);
    $data      = new OrderCreateData(
        status: App\Enums\Order\OrderStatusEnum::PENDING->value,
        customer_id: App\Models\User::factory()->create()->id,
        items: [],
        applied_coupon_code: 'VALID'
    );

    // Act
    $found = app(PromotionFinder::class)->findApplicablePromotion($data);

    // Assert
    expect($found)->not->toBeNull()
        ->and($found->id)->toBe($promotion->id);
});

it('does not find an inactive promotion', function (): void {
    $promotion = DiscountPromotion::factory()->create(['is_active' => false]);
    $coupon    = DiscountCoupon::factory()->create(['discount_promotion_id' => $promotion->id, 'code' => 'INACTIVE']);
    $data      = new OrderCreateData(
        status: App\Enums\Order\OrderStatusEnum::PENDING->value,
        customer_id: App\Models\User::factory()->create()->id,
        items: [],
        applied_coupon_code: 'INACTIVE');

    $found = app(PromotionFinder::class)->findApplicablePromotion($data);

    expect($found)->toBeNull();
});

it('does not find an expired promotion', function (): void {
    $promotion = DiscountPromotion::factory()->create(['ends_at' => now()->subDay()]);
    $coupon    = DiscountCoupon::factory()->create(['discount_promotion_id' => $promotion->id, 'code' => 'EXPIRED']);
    $data      = new OrderCreateData(status: App\Enums\Order\OrderStatusEnum::PENDING->value,
        customer_id: App\Models\User::factory()->create()->id,
        items: [], applied_coupon_code: 'EXPIRED');

    $found = app(PromotionFinder::class)->findApplicablePromotion($data);

    expect($found)->toBeNull();
});

it('finds an active promotion by ID', function (): void {
    $promotion = DiscountPromotion::factory()->create(['is_active' => true]);
    $data      = new OrderCreateData(
        status: App\Enums\Order\OrderStatusEnum::PENDING->value,
        customer_id: App\Models\User::factory()->create()->id,
        items: [], applied_coupon_code: null, promotion_id: $promotion->id
    );

    $found = app(PromotionFinder::class)->findApplicablePromotion($data);

    expect($found)->not->toBeNull()
        ->and($found->id)->toBe($promotion->id);
});
