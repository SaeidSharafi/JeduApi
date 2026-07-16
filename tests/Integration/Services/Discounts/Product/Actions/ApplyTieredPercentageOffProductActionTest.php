<?php

declare(strict_types=1);

use App\Models\ProductDeliveryOption;
use App\Services\Discounts\Configs\ApplyTieredPercentageOffProductData;
use App\Services\Discounts\Configs\TierData;
use App\Services\Discounts\Product\Actions\ApplyTieredPercentageOffProductAction;

describe('ApplyTieredPercentageOffProductAction', function (): void {
    test('it applies the highest applicable tier percentage', function (): void {
        $action = new ApplyTieredPercentageOffProductAction();
        $config = new ApplyTieredPercentageOffProductData(tiers: [
            new TierData(min_amount: 5000, percentage: 5),
            new TierData(min_amount: 10000, percentage: 10),
            new TierData(min_amount: 20000, percentage: 20),
        ]);

        $option = ProductDeliveryOption::factory()->make(['price' => 15000]);

        $discountAmount = $action->apply($option, $config);

        // Price is 15000, qualifies for the 10% tier (min 10000), but not the 20% tier (min 20000)
        // 10% of 15000 = 1500
        expect($discountAmount)->toBe(1500);
    });

    test('it returns 0 if the price does not meet the lowest tier', function (): void {
        $action = new ApplyTieredPercentageOffProductAction();
        $config = new ApplyTieredPercentageOffProductData(tiers: [
            new TierData(min_amount: 5000, percentage: 5),
        ]);

        $option = ProductDeliveryOption::factory()->make(['price' => 3000]);

        $discountAmount = $action->apply($option, $config);

        expect($discountAmount)->toBe(0);
    });
});
