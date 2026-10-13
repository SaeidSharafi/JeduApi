<?php

declare(strict_types=1);

use App\Models\DiscountPromotionRule;
use App\Models\ProductDeliveryOptionDiscountPrice;
use Database\Seeders\DemoSeeder;

it('seeds and reindexes the demo discount data with the tiered campaign', function (): void {
    $this->artisan('db:seed', ['--class' => DemoSeeder::class])->assertExitCode(0);

    $rules = DiscountPromotionRule::query()
        ->where('discount_promotion_id', 4)
        ->orderBy('id')
        ->get();

    expect($rules)->toHaveCount(2)
        ->and($rules[0]->handler)->toBe('cart_value_tier')
        ->and($rules[0]->configuration['tiers'])->toBe([
            ['min_amount' => 3000000, 'percentage' => 5],
            ['min_amount' => 5000000, 'percentage' => 8],
        ])
        ->and($rules[1]->handler)->toBe('apply_tiered_percentage_off')
        ->and($rules[1]->configuration)->toBe([]);

    // The tiered campaign is a cart_checkout ladder, so the real indexer leaves
    // it out of product_delivery_option_discount_prices; only the
    // product_specific demo promotion produces indexed rows.
    expect(ProductDeliveryOptionDiscountPrice::query()
        ->distinct()
        ->orderBy('discount_promotion_id')
        ->pluck('discount_promotion_id')
        ->all())->toBe([2]);
});
