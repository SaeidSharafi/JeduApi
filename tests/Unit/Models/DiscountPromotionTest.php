<?php

declare(strict_types=1);

use App\Models\DiscountPromotion;

test('to array', function (): void {
    $discount = DiscountPromotion::factory()->create()->fresh();

    expect($discount->toArray())
        ->toEqual([
            'id'                               => $discount->id,
            'name'                             => $discount->name,
            'description'                      => $discount->description,
            'type'                             => $discount->type->value,
            'is_active'                        => $discount->is_active,
            'starts_at'                        => $discount->starts_at?->utc()->toJSON(),
            'ends_at'                          => $discount->ends_at?->utc()->toJSON(),
            'priority'                         => $discount->priority,
            'stop_processing_subsequent_rules' => $discount->stop_processing_subsequent_rules,
            'requires_coupon'                  => $discount->requires_coupon,
            'usage_limit_total'                => $discount->usage_limit_total,
            'usage_limit_per_customer'         => $discount->usage_limit_per_customer,
            'total_usage_count'                => $discount->total_usage_count,
            'created_at'                       => $discount->created_at?->utc()->toJSON(),
            'updated_at'                       => $discount->updated_at?->utc()->toJSON(),
        ]);
});

test('rule relation', function (): void {
    $discount = DiscountPromotion::factory()->create();
    $rule     = App\Models\DiscountPromotionRule::create(
        [
            'discount_promotion_id' => $discount->id,
            'type'                  => 'action',
            'handler'               => 'apply_percentage_off',
            'configuration'         => ['percentage' => 10],
        ]
    );
    expect($discount->rules)
        ->toBeInstanceOf(Illuminate\Database\Eloquent\Collection::class)
        ->and($discount->rules->first())
        ->toBeInstanceOf(App\Models\DiscountPromotionRule::class)
        ->and($discount->rules->first()->id)
        ->toEqual($rule->id);
});

test('coupon relation', function (): void {
    $discount = DiscountPromotion::factory()->create();
    $coupon   = App\Models\DiscountCoupon::factory()->create(
        ['discount_promotion_id' => $discount->id])->fresh();
    expect($discount->coupons)
        ->toBeInstanceOf(Illuminate\Database\Eloquent\Collection::class)
        ->and($discount->coupons->first())
        ->toBeInstanceOf(App\Models\DiscountCoupon::class)
        ->and($discount->coupons->first()->id)
        ->toEqual($coupon->id);
});
test('discounted prices relation', function (): void {
    $discount = DiscountPromotion::factory()->create();
    $price    = App\Models\ProductDeliveryOptionDiscountPrice::create([
        'discount_promotion_id'      => $discount->id,
        'product_delivery_option_id' => App\Models\ProductDeliveryOption::factory()->create()->id,
        'discounted_price'           => 2000,
    ])->fresh();

    expect($discount->discountedPrices)
        ->toBeInstanceOf(Illuminate\Database\Eloquent\Collection::class)
        ->and($discount->discountedPrices->first())
        ->toBeInstanceOf(App\Models\ProductDeliveryOptionDiscountPrice::class)
        ->and($discount->discountedPrices->first()->product_delivery_option_id)
        ->toEqual($price->product_delivery_option_id);
});
