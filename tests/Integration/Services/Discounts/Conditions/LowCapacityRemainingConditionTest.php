<?php

declare(strict_types=1);

use App\Models\ProductDeliveryOption;
use App\Services\Discounts\Configs\LowCapacityRemainingData;
use App\Services\Discounts\Product\Conditions\LowCapacityRemainingCondition;

describe('LowCapacityRemainingCondition', function (): void {
    test('it passes when capacity threshold is reached', function (): void {
        $condition = new LowCapacityRemainingCondition();
        $config = new LowCapacityRemainingData(threshold: 0.8); // 80% full

        $option = ProductDeliveryOption::factory()->make([
            'capacity' => 10,
            'enrolled_count' => 8 // 80%
        ]);

        expect($condition->passes($option, $config))->toBeTrue();
    });

    test('it fails when capacity threshold is not reached', function (): void {
        $condition = new LowCapacityRemainingCondition();
        $config = new LowCapacityRemainingData(threshold: 0.8);

        $option = ProductDeliveryOption::factory()->make([
            'capacity' => 10,
            'enrolled_count' => 5 // 50%
        ]);

        expect($condition->passes($option, $config))->toBeFalse();
    });

    test('it fails when capacity is null (unlimited)', function (): void {
        $condition = new LowCapacityRemainingCondition();
        $config = new LowCapacityRemainingData(threshold: 0.8);

        $option = ProductDeliveryOption::factory()->make([
            'capacity' => null,
            'enrolled_count' => 100
        ]);

        expect($condition->passes($option, $config))->toBeFalse();
    });
});
