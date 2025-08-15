<?php

use App\Models\DiscountPromotion;
use App\Models\DiscountPromotionRule;

it('to array', function () {
    $discount = DiscountPromotion::factory()->create();
    $rule = DiscountPromotionRule::create([
        'discount_promotion_id' => $discount->id,
        'type'                  => 'action',
        'handler'               => 'apply_percentage_off',
        'configuration'         => ['percentage' => 10],
    ])->fresh();

    expect($rule->toArray())
        ->toEqual([
            'id'                    => $rule->id,
            'discount_promotion_id' => $rule->discount_promotion_id,
            'type'                  => $rule->type,
            'handler'               => $rule->handler,
            'configuration'         => $rule->configuration,
            'created_at'            => $rule->created_at->format('Y-m-d H:i:s'),
            'updated_at'            => $rule->updated_at->format('Y-m-d H:i:s'),
        ]);

});

it('promotion relation', function () {
    $discount = DiscountPromotion::factory()->create();
    $rule = DiscountPromotionRule::create([
        'discount_promotion_id' => $discount->id,
        'type'                  => 'action',
        'handler'               => 'apply_percentage_off',
        'configuration'         => ['percentage' => 10],
    ])->fresh();

    expect($rule->promotion)
        ->toBeInstanceOf(DiscountPromotion::class)
        ->and($rule->promotion->id)
        ->toEqual($discount->id);
});
