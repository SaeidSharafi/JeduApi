<?php

use App\Data\Admin\Discounts\OrderContextData;
use App\Enums\OperatorEnum;
use App\Models\User;
use App\Services\Discounts\Conditions\CartValueCondition;
use App\Services\Discounts\Conditions\CartValueConditionConfigData;
use Spatie\LaravelData\Data;

it('passes when value is greater or equal', function () {
    $handler = new CartValueCondition();
    $config = new CartValueConditionConfigData(operator: OperatorEnum::GREATER_THAN_OR_EQUAL, value: 10000, include_prepayments: false);
    $context = OrderContextData::from(['customer' => User::factory()->create(), 'items' => [], 'subtotal_full_payment_items' => 15000, 'subtotal_all_items' => 20000]);

    expect($handler->passes($context, $config))->toBeTrue();
});

it('fails when value is less than required', function () {
    $handler = new CartValueCondition();
    $config = new CartValueConditionConfigData(operator: OperatorEnum::GREATER_THAN_OR_EQUAL, value: 10000, include_prepayments: false);
    $context = OrderContextData::from(['customer' => User::factory()->create(), 'items' => [], 'subtotal_full_payment_items' => 9000, 'subtotal_all_items' => 20000]);

    expect($handler->passes($context, $config))->toBeFalse();
});

it('correctly uses all items subtotal when include_prepayments is true', function () {
    $handler = new CartValueCondition();
    $config = new CartValueConditionConfigData(operator: OperatorEnum::GREATER_THAN_OR_EQUAL, value: 18000, include_prepayments: true);
    $context = OrderContextData::from(['customer' => User::factory()->create(), 'items' => [], 'subtotal_full_payment_items' => 9000, 'subtotal_all_items' => 20000]);

    expect($handler->passes($context, $config))->toBeTrue();
});

dataset('operators', [
    // [operator, valueToTest, expectedResult]
    'greater than or equal - pass' => [OperatorEnum::GREATER_THAN_OR_EQUAL, 10000, true],
    'greater than or equal - fail' => [OperatorEnum::GREATER_THAN_OR_EQUAL, 10001, false],
    'greater than - pass'          => [OperatorEnum::GREATER_THAN, 9999, true],
    'greater than - fail'          => [OperatorEnum::GREATER_THAN, 10000, false],
    'less than or equal - pass'    => [OperatorEnum::LESS_THAN_OR_EQUAL, 10000, true],
    'less than or equal - fail'    => [OperatorEnum::LESS_THAN_OR_EQUAL, 9999, false],
    'less than - pass'             => [OperatorEnum::LESS_THAN, 10001, true],
    'less than - fail'             => [OperatorEnum::LESS_THAN, 10000, false],
    'equal - pass'                 => [OperatorEnum::EQUAL, 10000, true],
    'equal - fail'                 => [OperatorEnum::EQUAL, 10001, false],
]);

it('correctly evaluates all operators', function (OperatorEnum $operator, int $valueToTest, bool $expectedResult) {
    // This test covers all operator lines in the match statement
    $handler = new CartValueCondition();
    $config = new CartValueConditionConfigData(operator: $operator, value: $valueToTest, include_prepayments: false);

    // The context's value is 10000
    $context = OrderContextData::from(['customer' => User::factory()->make(), 'items' => [], 'subtotal_full_payment_items' => 10000, 'subtotal_all_items' => 20000]);

    expect($handler->passes($context, $config))->toBe($expectedResult);
})->with('operators');
it('returns false if configuration is not the correct type', function () {
    // This test covers: if (! $configuration instanceof CartValueConditionConfigData)
    $handler = new CartValueCondition();
    // Pass a generic Data object instead of the required config
    $wrongConfig = new class extends Data {
        public function __construct() {}
    };
    $context = OrderContextData::from([
        'customer' => User::factory()->make(),
        'items' => [],
        'subtotal_full_payment_items' => 15000,
        'subtotal_all_items' => 20000]
    );

    expect($handler->passes($context, $wrongConfig))->toBeFalse();
});
