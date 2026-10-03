<?php

declare(strict_types=1);

use App\Data\Admin\Discounts\OrderContextData;
use App\Enums\Operators\MathOperatorEnum;
use App\Models\User;
use App\Services\Discounts\Cart\Conditions\CartValueCondition;
use App\Services\Discounts\Configs\CartValueConditionConfigData;
use Spatie\LaravelData\Data;

it('passes when value is greater or equal', function (): void {
    $handler = new CartValueCondition();
    $config  = new CartValueConditionConfigData(operator: MathOperatorEnum::GREATER_THAN_OR_EQUAL, value: 10000, include_prepayments: false);
    $context = OrderContextData::from(['customer' => User::factory()->create(), 'items' => [], 'subtotal_full_payment_items' => 15000, 'subtotal_all_items' => 20000]);

    expect($handler->passes($context, $config))->toBeTrue();
});

it('fails when value is less than required', function (): void {
    $handler = new CartValueCondition();
    $config  = new CartValueConditionConfigData(operator: MathOperatorEnum::GREATER_THAN_OR_EQUAL, value: 10000, include_prepayments: false);
    $context = OrderContextData::from(['customer' => User::factory()->create(), 'items' => [], 'subtotal_full_payment_items' => 9000, 'subtotal_all_items' => 20000]);

    expect($handler->passes($context, $config))->toBeFalse();
});

it('correctly uses all items subtotal when include_prepayments is true', function (): void {
    $handler = new CartValueCondition();
    $config  = new CartValueConditionConfigData(operator: MathOperatorEnum::GREATER_THAN_OR_EQUAL, value: 18000, include_prepayments: true);
    $context = OrderContextData::from(['customer' => User::factory()->create(), 'items' => [], 'subtotal_full_payment_items' => 9000, 'subtotal_all_items' => 20000]);

    expect($handler->passes($context, $config))->toBeTrue();
});

dataset('operators', [
    // [operator, valueToTest, expectedResult]
    'greater than or equal - pass' => [MathOperatorEnum::GREATER_THAN_OR_EQUAL, 10000, true],
    'greater than or equal - fail' => [MathOperatorEnum::GREATER_THAN_OR_EQUAL, 10001, false],
    'greater than - pass'          => [MathOperatorEnum::GREATER_THAN, 9999, true],
    'greater than - fail'          => [MathOperatorEnum::GREATER_THAN, 10000, false],
    'less than or equal - pass'    => [MathOperatorEnum::LESS_THAN_OR_EQUAL, 10000, true],
    'less than or equal - fail'    => [MathOperatorEnum::LESS_THAN_OR_EQUAL, 9999, false],
    'less than - pass'             => [MathOperatorEnum::LESS_THAN, 10001, true],
    'less than - fail'             => [MathOperatorEnum::LESS_THAN, 10000, false],
    'equal - pass'                 => [MathOperatorEnum::EQUAL, 10000, true],
    'equal - fail'                 => [MathOperatorEnum::EQUAL, 10001, false],
]);

it('correctly evaluates all operators', function (MathOperatorEnum $operator, int $valueToTest, bool $expectedResult): void {
    // This test covers all operator lines in the match statement
    $handler = new CartValueCondition();
    $config  = new CartValueConditionConfigData(operator: $operator, value: $valueToTest, include_prepayments: false);

    // The context's value is 10000
    $context = OrderContextData::from(['customer' => User::factory()->make(), 'items' => [], 'subtotal_full_payment_items' => 10000, 'subtotal_all_items' => 20000]);

    expect($handler->passes($context, $config))->toBe($expectedResult);
})->with('operators');
it('returns false if configuration is not the correct type', function (): void {
    // This test covers: if (! $configuration instanceof CartValueConditionConfigData)
    $handler = new CartValueCondition();
    // Pass a generic Data object instead of the required config
    $wrongConfig = new class extends Data
    {
        public function __construct() {}
    };
    $context = OrderContextData::from([
        'customer'                    => User::factory()->make(),
        'items'                       => [],
        'subtotal_full_payment_items' => 15000,
        'subtotal_all_items'          => 20000]
    );

    expect($handler->passes($context, $wrongConfig))->toBeFalse();
});
