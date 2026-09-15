<?php

declare(strict_types=1);

use App\Exceptions\RefundValidationException;
use App\Services\BundleRefundDeductionCalculator;
use App\Services\MixedOrderRefundCalculator;
use App\Services\WeightedApportionment;

covers(MixedOrderRefundCalculator::class);

/**
 * @param  list<array{order_item_id: int, product_delivery_option_id: int, base_price: int, paid_amount: int}>  $components
 * @return array{base_value: int, paid_value: int, components: list<array{order_item_id: int, product_delivery_option_id: int, base_price: int, paid_amount: int}>}
 */
function mixedUnit(int $baseValue, int $paidValue, array $components): array
{
    return ['base_value' => $baseValue, 'paid_value' => $paidValue, 'components' => $components];
}

/**
 * @return array{order_item_id: int, product_delivery_option_id: int, base_price: int, paid_amount: int}
 */
function mixedLine(int $id, int $basePrice, int $paidAmount): array
{
    return [
        'order_item_id'              => $id,
        'product_delivery_option_id' => $id,
        'base_price'                 => $basePrice,
        'paid_amount'                => $paidAmount,
    ];
}

beforeEach(function (): void {
    $this->calculator = new MixedOrderRefundCalculator(
        new BundleRefundDeductionCalculator(new WeightedApportionment()),
        new WeightedApportionment(),
    );
});

it('calculates a percentage per commercial unit from its snapshotted base value', function (): void {
    $result = $this->calculator->calculate(
        units: [
            mixedUnit(100000, 100000, [mixedLine(1, 100000, 100000)]),
            mixedUnit(200000, 150000, [mixedLine(2, 120000, 100000), mixedLine(3, 80000, 50000)]),
        ],
        deductionAmount: null,
        deductionPercent: 10,
    );

    expect($result['policy_deduction_amount'])->toBe(30000)
        ->and($result['effective_deduction_amount'])->toBe(30000)
        ->and($result['refund_amount'])->toBe(220000)
        ->and($result['units'][0]['refund_amount'])->toBe(90000)
        ->and($result['units'][0]['components'][0]['policy_deduction_amount'])->toBe(10000)
        ->and($result['units'][1]['refund_amount'])->toBe(130000)
        ->and($result['units'][1]['components'][0]['policy_deduction_amount'])->toBe(12000)
        ->and($result['units'][1]['components'][1]['policy_deduction_amount'])->toBe(8000);
});

it('distributes a fixed full-order deduction across units by base-value weight', function (): void {
    $result = $this->calculator->calculate(
        units: [
            mixedUnit(100000, 100000, [mixedLine(1, 100000, 100000)]),
            mixedUnit(300000, 300000, [mixedLine(2, 300000, 300000)]),
        ],
        deductionAmount: 40000,
        deductionPercent: null,
    );

    expect($result['units'][0]['policy_deduction_amount'])->toBe(10000)
        ->and($result['units'][0]['refund_amount'])->toBe(90000)
        ->and($result['units'][1]['policy_deduction_amount'])->toBe(30000)
        ->and($result['units'][1]['refund_amount'])->toBe(270000)
        ->and($result['refund_amount'])->toBe(360000);
});

it('keeps fixed distribution deterministic with remainder handling', function (): void {
    $units = array_fill(0, 3, null);
    foreach ($units as $index => $_) {
        $units[$index] = mixedUnit(100000, 100000, [mixedLine($index + 1, 100000, 100000)]);
    }

    $result = $this->calculator->calculate($units, deductionAmount: 100, deductionPercent: null);

    expect(array_map(
        fn (array $unit): int => $unit['components'][0]['effective_deduction_amount'],
        $result['units'],
    ))->toBe([34, 33, 33]);
});

it('caps every unit at its paid value and never produces a negative refund', function (): void {
    $result = $this->calculator->calculate(
        units: [
            mixedUnit(200000, 0, [mixedLine(1, 200000, 0)]),
            mixedUnit(100000, 40000, [mixedLine(2, 100000, 40000)]),
        ],
        deductionAmount: 500000,
        deductionPercent: null,
    );

    expect($result['units'][0]['effective_deduction_amount'])->toBe(0)
        ->and($result['units'][0]['refund_amount'])->toBe(0)
        ->and($result['units'][1]['effective_deduction_amount'])->toBe(40000)
        ->and($result['units'][1]['refund_amount'])->toBe(0)
        ->and($result['effective_deduction_amount'])->toBe(40000)
        ->and($result['refund_amount'])->toBe(0);
});

it('redistributes a Bundle share internally through the Bundle rules', function (): void {
    $result = $this->calculator->calculate(
        units: [
            mixedUnit(300000, 100000, [mixedLine(1, 200000, 100000), mixedLine(2, 100000, 0)]),
        ],
        deductionAmount: null,
        deductionPercent: 10,
    );

    expect($result['units'][0]['components'][0]['effective_deduction_amount'])->toBe(30000)
        ->and($result['units'][0]['components'][0]['redistributed_amount'])->toBe(10000)
        ->and($result['units'][0]['components'][0]['refund_amount'])->toBe(70000)
        ->and($result['units'][0]['components'][1]['effective_deduction_amount'])->toBe(0)
        ->and($result['units'][0]['refund_amount'])->toBe(70000);
});

it('splits equally when every unit base value is zero', function (): void {
    $result = $this->calculator->calculate(
        units: [
            mixedUnit(0, 30000, [mixedLine(1, 0, 10000)]),
            mixedUnit(0, 30000, [mixedLine(2, 0, 10000)]),
            mixedUnit(0, 30000, [mixedLine(3, 0, 10000)]),
        ],
        deductionAmount: 100,
        deductionPercent: null,
    );

    expect(array_map(
        fn (array $unit): int => $unit['components'][0]['effective_deduction_amount'],
        $result['units'],
    ))->toBe([34, 33, 33]);
});

it('accepts a consistent amount and percent pair and rejects a conflicting one', function (): void {
    $units = [mixedUnit(100000, 100000, [mixedLine(1, 100000, 100000)])];

    $result = $this->calculator->calculate($units, deductionAmount: 10000, deductionPercent: 10);
    expect($result['effective_deduction_amount'])->toBe(10000);

    expect(fn () => $this->calculator->calculate($units, deductionAmount: 15000, deductionPercent: 10))
        ->toThrow(RefundValidationException::class);
});

it('makes no deduction when neither a fixed amount nor a percent is given', function (): void {
    $result = $this->calculator->calculate(
        units: [
            mixedUnit(100000, 100000, [mixedLine(1, 100000, 100000)]),
            mixedUnit(200000, 150000, [mixedLine(2, 200000, 150000)]),
        ],
        deductionAmount: null,
        deductionPercent: null,
    );

    expect($result['policy_deduction_amount'])->toBe(0)
        ->and($result['effective_deduction_amount'])->toBe(0)
        ->and($result['refund_amount'])->toBe(250000)
        ->and($result['units'][0]['components'][0]['effective_deduction_amount'])->toBe(0)
        ->and($result['units'][0]['components'][0]['refund_amount'])->toBe(100000);
});

it('reports the apportioned policy total for a fixed deduction', function (): void {
    $result = $this->calculator->calculate(
        units: [
            mixedUnit(100000, 100000, [mixedLine(1, 100000, 100000)]),
            mixedUnit(300000, 300000, [mixedLine(2, 300000, 300000)]),
        ],
        deductionAmount: 40000,
        deductionPercent: null,
    );

    expect($result['policy_deduction_amount'])->toBe(40000)
        ->and($result['effective_deduction_amount'])->toBe(40000)
        ->and($result['refund_amount'])->toBe(360000);
});

it('reports the capped totals for a fixed deduction larger than the refundable value', function (): void {
    $result = $this->calculator->calculate(
        units: [
            mixedUnit(200000, 0, [mixedLine(1, 200000, 0)]),
            mixedUnit(100000, 40000, [mixedLine(2, 100000, 40000)]),
        ],
        deductionAmount: 500000,
        deductionPercent: null,
    );

    expect($result['policy_deduction_amount'])->toBe(500000)
        ->and($result['effective_deduction_amount'])->toBe(40000)
        ->and($result['refund_amount'])->toBe(0);
});

it('reports percentage totals across every unit', function (): void {
    $result = $this->calculator->calculate(
        units: [
            mixedUnit(100000, 100000, [mixedLine(1, 100000, 100000)]),
            mixedUnit(200000, 150000, [mixedLine(2, 120000, 100000), mixedLine(3, 80000, 50000)]),
        ],
        deductionAmount: null,
        deductionPercent: 10,
    );

    expect($result['policy_deduction_amount'])->toBe(30000)
        ->and($result['effective_deduction_amount'])->toBe(30000)
        ->and($result['refund_amount'])->toBe(220000)
        ->and($result['units'][1]['policy_deduction_amount'])->toBe(20000)
        ->and($result['units'][1]['effective_deduction_amount'])->toBe(20000);
});

it('floors a percentage that does not divide exactly', function (): void {
    $result = $this->calculator->calculate(
        units: [mixedUnit(33335, 33335, [mixedLine(1, 33335, 33335)])],
        deductionAmount: null,
        deductionPercent: 10,
    );

    expect($result['policy_deduction_amount'])->toBe(3333)
        ->and($result['effective_deduction_amount'])->toBe(3333)
        ->and($result['refund_amount'])->toBe(30002);
});
/*
 * Mutation notes (`pest --mutate --parallel`):
 * The remaining survivors are equivalent mutants: the `(int)` casts on
 * already-integer snapshots and the `max(0, ...)` clamps only differ for
 * negative or zero inputs that the refund action's validated snapshots never
 * produce, and `max(0, $amount ?? 0)` changing to `max(-1, ...)` is identical
 * for the zero and positive policy amounts the contract allows.
 */
