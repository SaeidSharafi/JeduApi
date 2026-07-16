<?php

declare(strict_types=1);

use App\Models\ProductDeliveryOption;
use App\Services\Discounts\Configs\RegistrationClosingSoonData;
use App\Services\Discounts\Product\Conditions\RegistrationClosingSoonCondition;
use Carbon\Carbon;

describe('RegistrationClosingSoonCondition', function (): void {
    test('it passes when registration ends within the specified days', function (): void {
        $condition = new RegistrationClosingSoonCondition();
        $config = new RegistrationClosingSoonData(days: 3);

        $option = ProductDeliveryOption::factory()->make([
            'registration_end_date' => Carbon::now()->addDays(2)
        ]);

        expect($condition->passes($option, $config))->toBeTrue();
    });

    test('it fails when registration ends beyond the specified days', function (): void {
        $condition = new RegistrationClosingSoonCondition();
        $config = new RegistrationClosingSoonData(days: 3);

        $option = ProductDeliveryOption::factory()->make([
            'registration_end_date' => Carbon::now()->addDays(5)
        ]);

        expect($condition->passes($option, $config))->toBeFalse();
    });

    test('it fails when registration date has already passed', function (): void {
        $condition = new RegistrationClosingSoonCondition();
        $config = new RegistrationClosingSoonData(days: 3);

        $option = ProductDeliveryOption::factory()->make([
            'registration_end_date' => Carbon::now()->subDay()
        ]);

        expect($condition->passes($option, $config))->toBeFalse();
    });

    test('it fails when there is no registration end date', function (): void {
        $condition = new RegistrationClosingSoonCondition();
        $config = new RegistrationClosingSoonData(days: 3);

        $option = ProductDeliveryOption::factory()->make([
            'registration_end_date' => null
        ]);

        expect($condition->passes($option, $config))->toBeFalse();
    });
});
