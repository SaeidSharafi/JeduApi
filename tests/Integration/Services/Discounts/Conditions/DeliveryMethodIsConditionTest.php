<?php

declare(strict_types=1);

use App\Enums\Product\DeliveryMethodEnum;
use App\Models\ProductDeliveryOption;
use App\Services\Discounts\Configs\DeliveryMethodIsData;
use App\Services\Discounts\Product\Conditions\DeliveryMethodIsCondition;

describe('DeliveryMethodIsCondition', function (): void {
    test('it passes when delivery method matches', function (): void {
        $condition = new DeliveryMethodIsCondition();
        $config = new DeliveryMethodIsData(delivery_methods: [DeliveryMethodEnum::LMS_MOODLE->value]);

        $option = ProductDeliveryOption::factory()->make([
            'delivery_method' => DeliveryMethodEnum::LMS_MOODLE->value
        ]);

        expect($condition->passes($option, $config))->toBeTrue();
    });

    test('it fails when delivery method does not match', function (): void {
        $condition = new DeliveryMethodIsCondition();
        $config = new DeliveryMethodIsData(delivery_methods: [DeliveryMethodEnum::LMS_MOODLE->value]);

        $option = ProductDeliveryOption::factory()->make([
            'delivery_method' => DeliveryMethodEnum::IN_PERSON->value
        ]);

        expect($condition->passes($option, $config))->toBeFalse();
    });
});
