<?php

declare(strict_types=1);

it('to array', function (): void {
    $dsc = App\Models\ProductDeliveryOptionDiscountPrice::create(
        [
            'product_delivery_option_id' => App\Models\ProductDeliveryOption::factory()->create()->id,
            'discount_promotion_id'      => App\Models\DiscountPromotion::factory()->create()->id,
            'discounted_price'           => 1000,
            'created_at'                 => now(),
            'updated_at'                 => now(),
        ]
    );

    expect($dsc->toArray())
        ->toEqual([
            'product_delivery_option_id' => $dsc->product_delivery_option_id,
            'discount_promotion_id'      => $dsc->discount_promotion_id,
            'discounted_price'           => $dsc->discounted_price,
            'created_at'                 => $dsc->created_at?->utc()->toJSON(),
            'updated_at'                 => $dsc->updated_at?->utc()->toJSON(),
        ]);
});

it('promotion relation', function (): void {
    $product   = App\Models\ProductDeliveryOption::factory()->create();
    $promotion = App\Models\DiscountPromotion::factory()->create();
    $dsc       = App\Models\ProductDeliveryOptionDiscountPrice::create([
        'product_delivery_option_id' => $product->id,
        'discount_promotion_id'      => $promotion->id,
        'discounted_price'           => 1000,
        'created_at'                 => now(),
        'updated_at'                 => now(),
    ]);
    $promotion = $dsc->promotion;

    expect($promotion)
        ->toBeInstanceOf(App\Models\DiscountPromotion::class)
        ->and($promotion->id)
        ->toEqual($dsc->discount_promotion_id);

});

it('product delivery option relation', function (): void {
    $product   = App\Models\ProductDeliveryOption::factory()->create();
    $promotion = App\Models\DiscountPromotion::factory()->create();
    $dsc       = App\Models\ProductDeliveryOptionDiscountPrice::create([
        'product_delivery_option_id' => $product->id,
        'discount_promotion_id'      => $promotion->id,
        'discounted_price'           => 1000,
        'created_at'                 => now(),
        'updated_at'                 => now(),
    ]);

    expect($dsc->productDeliveryOption)
        ->toBeInstanceOf(App\Models\ProductDeliveryOption::class)
        ->and($product->id)
        ->toEqual($dsc->product_delivery_option_id);
});
