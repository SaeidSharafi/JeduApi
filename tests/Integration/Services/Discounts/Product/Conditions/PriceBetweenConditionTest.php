<?php

declare(strict_types=1);

use App\Models\ProductDeliveryOption;
use App\Services\Discounts\Configs\PriceBetweenData;
use App\Services\Discounts\Product\Conditions\PriceBetweenCondition;

describe('PriceBetweenCondition', function (): void {
    test('it passes when price is strictly within min and max boundaries', function (): void {
        $condition = new PriceBetweenCondition();
        $config    = new PriceBetweenData(min_price: 5000, max_price: 15000);
        $option    = ProductDeliveryOption::factory()->make(['price' => 10000]);

        expect($condition->passes($option, $config))->toBeTrue();
    });

    test('it passes when price is equal to the minimum boundary', function (): void {
        $condition = new PriceBetweenCondition();
        $config    = new PriceBetweenData(min_price: 5000, max_price: 15000);
        $option    = ProductDeliveryOption::factory()->make(['price' => 5000]);

        expect($condition->passes($option, $config))->toBeTrue();
    });

    test('it fails when price is below the minimum boundary', function (): void {
        $condition = new PriceBetweenCondition();
        $config    = new PriceBetweenData(min_price: 5000, max_price: 15000);
        $option    = ProductDeliveryOption::factory()->make(['price' => 4999]);

        expect($condition->passes($option, $config))->toBeFalse();
    });

    test('it passes when no max_price is defined and price is above minimum', function (): void {
        $condition = new PriceBetweenCondition();
        $config    = new PriceBetweenData(min_price: 5000, max_price: null);
        $option    = ProductDeliveryOption::factory()->make(['price' => 99999]);

        expect($condition->passes($option, $config))->toBeTrue();
    });
});
