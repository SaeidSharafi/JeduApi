<?php

declare(strict_types=1);

use App\Models\ProductDeliveryOption;
use App\Services\Discounts\Configs\ApplyFixedPriceProductData;
use App\Services\Discounts\Product\Actions\ApplyFixedPriceProductAction;

describe('ApplyFixedPriceProductAction', function (): void {
    test('it overrides the price by returning the correct discount amount', function (): void {
        $action = new ApplyFixedPriceProductAction();
        $config = new ApplyFixedPriceProductData(fixed_price: 6000);
        $option = ProductDeliveryOption::factory()->make(['price' => 10000]);

        $discountAmount = $action->apply($option, $config);

        // Base price is 10000, fixed price is 6000, so discount amount should be 4000
        expect($discountAmount)->toBe(4000);
    });

    test('it returns 0 discount if base price is already lower than fixed price', function (): void {
        $action = new ApplyFixedPriceProductAction();
        $config = new ApplyFixedPriceProductData(fixed_price: 15000);
        $option = ProductDeliveryOption::factory()->make(['price' => 10000]);

        $discountAmount = $action->apply($option, $config);

        expect($discountAmount)->toBe(0);
    });
});
