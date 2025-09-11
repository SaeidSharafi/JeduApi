<?php

declare(strict_types=1);

it('to array', function () {
    $coupon = App\Models\DiscountCoupon::factory()->create()->fresh();

    expect($coupon->toArray())
        ->toEqual([
            'id'                    => $coupon->id,
            'discount_promotion_id' => $coupon->discount_promotion_id,
            'code'                  => $coupon->code,
            'is_active'             => $coupon->is_active,
            'usage_limit'           => $coupon->usage_limit,
            'usage_count'           => $coupon->usage_count,
            'created_at'            => $coupon->created_at?->utc()->toJSON(),
            'updated_at'            => $coupon->updated_at?->utc()->toJSON(),
        ]);
});

it('promotion relation', function () {
    $coupon    = App\Models\DiscountCoupon::factory()->create();
    $promotion = $coupon->promotion;

    expect($promotion)
        ->toBeInstanceOf(App\Models\DiscountPromotion::class)
        ->and($promotion->id)
        ->toEqual($coupon->discount_promotion_id);
});
