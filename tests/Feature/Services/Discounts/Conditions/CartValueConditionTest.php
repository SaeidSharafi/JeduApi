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
    $config  = new CartValueConditionConfigData(operator: MathOperatorEnum::GREATER_THAN_OR_EQUAL, value: 10000, measure_whole_commitment: false);
    $context = OrderContextData::from(['customer' => User::factory()->create(), 'items' => [], 'subtotal_full_payment_items' => 15000, 'subtotal_all_items' => 20000, 'effective_subtotal_all_items' => 12000]);

    expect($handler->passes($context, $config))->toBeTrue();
});

it('fails when value is less than required', function (): void {
    $handler = new CartValueCondition();
    $config  = new CartValueConditionConfigData(operator: MathOperatorEnum::GREATER_THAN_OR_EQUAL, value: 10000, measure_whole_commitment: false);
    $context = OrderContextData::from(['customer' => User::factory()->create(), 'items' => [], 'subtotal_full_payment_items' => 9000, 'subtotal_all_items' => 20000, 'effective_subtotal_all_items' => 12000]);

    expect($handler->passes($context, $config))->toBeFalse();
});

it('measures the whole commitment on the effective basis, never the base-priced total', function (): void {
    $handler = new CartValueCondition();
    $context = OrderContextData::from([
        'customer'                     => User::factory()->create(),
        'items'                        => [],
        'subtotal_full_payment_items'  => 9000,
        'subtotal_all_items'           => 25000, // base-priced all lines
        'effective_subtotal_all_items' => 20000, // product discounts applied
    ]);

    $pass = new CartValueConditionConfigData(operator: MathOperatorEnum::GREATER_THAN_OR_EQUAL, value: 18000, measure_whole_commitment: true);
    $fail = new CartValueConditionConfigData(operator: MathOperatorEnum::GREATER_THAN_OR_EQUAL, value: 20001, measure_whole_commitment: true);

    expect($handler->passes($context, $pass))->toBeTrue()
        ->and($handler->passes($context, $fail))->toBeFalse();
});

it('switches only the measured line set and never the price basis', function (): void {
    // A cart with a product-discounted full-payment line and a deposit line:
    //  - the full-payment line is worth 80,000 after its product discount (base 100,000)
    //  - the deposit line is counted at its 10,000 deposit (base course price 100,000)
    $context = OrderContextData::from([
        'customer'                     => User::factory()->create(),
        'items'                        => [],
        'subtotal_full_payment_items'  => 80000,
        'subtotal_all_items'           => 180000, // base-priced all lines
        'effective_subtotal_all_items' => 90000,  // product discounts applied, deposit at its deposit
    ]);
    $handler = new CartValueCondition();

    // 85,000 sits between the two line sets, so only the line set decides:
    // both options are measured on the same effective basis.
    $onlyFullPayment = new CartValueConditionConfigData(operator: MathOperatorEnum::GREATER_THAN_OR_EQUAL, value: 85000, measure_whole_commitment: false);
    $wholeCommitment = new CartValueConditionConfigData(operator: MathOperatorEnum::GREATER_THAN_OR_EQUAL, value: 85000, measure_whole_commitment: true);

    expect($handler->passes($context, $onlyFullPayment))->toBeFalse()
        ->and($handler->passes($context, $wholeCommitment))->toBeTrue();

    // 100,000 is above the effective all-lines value (90,000) but below the
    // base-priced all-lines total (180,000). Failing here proves the deposit
    // contributes its 10,000 deposit (not its 100,000 course price) and that
    // the full-payment line contributes its discounted 80,000 (not its base 100,000).
    $aboveEffectiveBasis = new CartValueConditionConfigData(operator: MathOperatorEnum::GREATER_THAN_OR_EQUAL, value: 100000, measure_whole_commitment: true);

    expect($handler->passes($context, $aboveEffectiveBasis))->toBeFalse();

    // 80,000 is the effective full-payment value; the base-priced full line is 100,000.
    $atEffectiveFullPaymentValue = new CartValueConditionConfigData(operator: MathOperatorEnum::GREATER_THAN_OR_EQUAL, value: 80000, measure_whole_commitment: false);

    expect($handler->passes($context, $atEffectiveFullPaymentValue))->toBeTrue();
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
    $config  = new CartValueConditionConfigData(operator: $operator, value: $valueToTest, measure_whole_commitment: false);

    // The context's value is 10000
    $context = OrderContextData::from(['customer' => User::factory()->make(), 'items' => [], 'subtotal_full_payment_items' => 10000, 'subtotal_all_items' => 20000, 'effective_subtotal_all_items' => 10000]);

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
        'customer'                     => User::factory()->make(),
        'items'                        => [],
        'subtotal_full_payment_items'  => 15000,
        'subtotal_all_items'           => 20000,
        'effective_subtotal_all_items' => 15000]
    );

    expect($handler->passes($context, $wrongConfig))->toBeFalse();
});
