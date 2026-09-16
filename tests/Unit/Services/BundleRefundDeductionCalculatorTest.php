<?php

declare(strict_types=1);

use App\Services\BundleRefundDeductionCalculator;
use App\Services\WeightedApportionment;

covers(BundleRefundDeductionCalculator::class);

/**
 * @return array{order_item_id: int, product_delivery_option_id: int, base_price: int, paid_amount: int}
 */
function deductionComponent(int $id, int $basePrice, int $paidAmount): array
{
    return [
        'order_item_id'              => $id,
        'product_delivery_option_id' => $id,
        'base_price'                 => $basePrice,
        'paid_amount'                => $paidAmount,
    ];
}

beforeEach(function (): void {
    $this->calculator = new BundleRefundDeductionCalculator(new WeightedApportionment());
});

it('distributes a percentage of Bundle Base Value by snapshotted base-price weight', function (): void {
    $result = $this->calculator->calculate(
        paidAmount: 300000,
        components: [
            deductionComponent(1, 200000, 200000),
            deductionComponent(2, 100000, 100000),
        ],
        policyDeductionAmount: 30000,
    );

    expect($result['policy_deduction_amount'])->toBe(30000)
        ->and($result['effective_deduction_amount'])->toBe(30000)
        ->and($result['refund_amount'])->toBe(270000)
        ->and($result['components'][0]['policy_deduction_amount'])->toBe(20000)
        ->and($result['components'][0]['refund_amount'])->toBe(180000)
        ->and($result['components'][1]['policy_deduction_amount'])->toBe(10000)
        ->and($result['components'][1]['refund_amount'])->toBe(90000);
});

it('distributes a fixed Bundle deduction with the same base-price weighting', function (): void {
    $result = $this->calculator->calculate(
        paidAmount: 300000,
        components: [
            deductionComponent(1, 100000, 100000),
            deductionComponent(2, 200000, 200000),
        ],
        policyDeductionAmount: 30000,
    );

    expect($result['components'][0]['policy_deduction_amount'])->toBe(10000)
        ->and($result['components'][1]['policy_deduction_amount'])->toBe(20000)
        ->and($result['effective_deduction_amount'])->toBe(30000);
});

it('redistributes the share of a zero-paid component onto paid components', function (): void {
    $result = $this->calculator->calculate(
        paidAmount: 100000,
        components: [
            deductionComponent(1, 200000, 100000),
            deductionComponent(2, 100000, 0),
        ],
        policyDeductionAmount: 30000,
    );

    expect($result['components'][0]['policy_deduction_amount'])->toBe(20000)
        ->and($result['components'][0]['effective_deduction_amount'])->toBe(30000)
        ->and($result['components'][0]['redistributed_amount'])->toBe(10000)
        ->and($result['components'][0]['refund_amount'])->toBe(70000)
        ->and($result['components'][1]['effective_deduction_amount'])->toBe(0)
        ->and($result['components'][1]['redistributed_amount'])->toBe(-10000)
        ->and($result['components'][1]['refund_amount'])->toBe(0)
        ->and($result['effective_deduction_amount'])->toBe(30000)
        ->and($result['refund_amount'])->toBe(70000);
});

it('redistributes an over-cap share onto a component with remaining paid value', function (): void {
    $result = $this->calculator->calculate(
        paidAmount: 105000,
        components: [
            deductionComponent(1, 300000, 100000),
            deductionComponent(2, 100000, 5000),
        ],
        policyDeductionAmount: 60000,
    );

    expect($result['components'][0]['policy_deduction_amount'])->toBe(45000)
        ->and($result['components'][0]['effective_deduction_amount'])->toBe(55000)
        ->and($result['components'][0]['redistributed_amount'])->toBe(10000)
        ->and($result['components'][0]['refund_amount'])->toBe(45000)
        ->and($result['components'][1]['policy_deduction_amount'])->toBe(15000)
        ->and($result['components'][1]['effective_deduction_amount'])->toBe(5000)
        ->and($result['components'][1]['redistributed_amount'])->toBe(-10000)
        ->and($result['components'][1]['refund_amount'])->toBe(0)
        ->and($result['effective_deduction_amount'])->toBe(60000)
        ->and($result['refund_amount'])->toBe(45000);
});

it('caps the effective deduction at the actual Bundle amount paid so a refund cannot go negative', function (): void {
    $result = $this->calculator->calculate(
        paidAmount: 50000,
        components: [
            deductionComponent(1, 200000, 50000),
        ],
        policyDeductionAmount: 100000,
    );

    expect($result['policy_deduction_amount'])->toBe(100000)
        ->and($result['effective_deduction_amount'])->toBe(50000)
        ->and($result['refund_amount'])->toBe(0)
        ->and($result['components'][0]['refund_amount'])->toBe(0);
});

it('assigns rounding remainders deterministically by largest remainder then component order', function (): void {
    $result = $this->calculator->calculate(
        paidAmount: 100000,
        components: [
            deductionComponent(1, 33333, 33333),
            deductionComponent(2, 33333, 33333),
            deductionComponent(3, 33334, 33334),
        ],
        policyDeductionAmount: 100,
    );

    expect(array_column($result['components'], 'policy_deduction_amount'))->toBe([33, 33, 34])
        ->and($result['effective_deduction_amount'])->toBe(100);
});

it('returns an all-zero calculation when nothing was actually paid', function (): void {
    $result = $this->calculator->calculate(
        paidAmount: 0,
        components: [
            deductionComponent(1, 100000, 0),
            deductionComponent(2, 100000, 0),
        ],
        policyDeductionAmount: 20000,
    );

    expect($result['effective_deduction_amount'])->toBe(0)
        ->and($result['refund_amount'])->toBe(0)
        ->and(array_column($result['components'], 'effective_deduction_amount'))->toBe([0, 0])
        ->and(array_column($result['components'], 'refund_amount'))->toBe([0, 0]);
});

it('produces the identical split for repeated calls', function (): void {
    $arguments = [
        'paidAmount' => 200000,
        'components' => [
            deductionComponent(1, 111111, 100000),
            deductionComponent(2, 111111, 100000),
            deductionComponent(3, 111111, 0),
        ],
        'policyDeductionAmount' => 33333,
    ];

    expect($this->calculator->calculate(...$arguments))
        ->toBe($this->calculator->calculate(...$arguments));
});

it('returns an empty breakdown when the Bundle has no component lines', function (): void {
    $result = $this->calculator->calculate(
        paidAmount: 0,
        components: [],
        policyDeductionAmount: 5000,
    );

    expect($result['policy_deduction_amount'])->toBe(5000)
        ->and($result['effective_deduction_amount'])->toBe(0)
        ->and($result['refund_amount'])->toBe(0)
        ->and($result['components'])->toBe([]);
});

it('splits equally when every component base price is zero', function (): void {
    $result = $this->calculator->calculate(
        paidAmount: 30000,
        components: [
            deductionComponent(1, 0, 10000),
            deductionComponent(2, 0, 10000),
            deductionComponent(3, 0, 10000),
        ],
        policyDeductionAmount: 100,
    );

    expect(array_column($result['components'], 'policy_deduction_amount'))->toBe([34, 33, 33])
        ->and(array_column($result['components'], 'effective_deduction_amount'))->toBe([34, 33, 33])
        ->and($result['effective_deduction_amount'])->toBe(100);
});

it('assigns no deductible weight to a zero base-price component', function (): void {
    $result = $this->calculator->calculate(
        paidAmount: 100000,
        components: [
            deductionComponent(1, 100000, 50000),
            deductionComponent(2, 0, 50000),
        ],
        policyDeductionAmount: 10000,
    );

    expect($result['components'][0]['policy_deduction_amount'])->toBe(10000)
        ->and($result['components'][0]['effective_deduction_amount'])->toBe(10000)
        ->and($result['components'][0]['refund_amount'])->toBe(40000)
        ->and($result['components'][1]['policy_deduction_amount'])->toBe(0)
        ->and($result['components'][1]['refund_amount'])->toBe(50000);
});

it('makes no deduction when the policy amount is zero', function (): void {
    $result = $this->calculator->calculate(
        paidAmount: 150000,
        components: [
            deductionComponent(1, 120000, 100000),
            deductionComponent(2, 80000, 50000),
        ],
        policyDeductionAmount: 0,
    );

    expect($result['effective_deduction_amount'])->toBe(0)
        ->and($result['refund_amount'])->toBe(150000)
        ->and(array_column($result['components'], 'effective_deduction_amount'))->toBe([0, 0]);
});

it('redistributes across every component that still has paid value', function (): void {
    $result = $this->calculator->calculate(
        paidAmount: 640100,
        components: [
            deductionComponent(1, 600000, 600000),
            deductionComponent(2, 300000, 100),
            deductionComponent(3, 100000, 40000),
        ],
        policyDeductionAmount: 100000,
    );

    // B can only absorb its 100 paid, so its 29,900 shortfall is split across A
    // and C in proportion to their remaining paid value.
    expect($result['components'][1]['effective_deduction_amount'])->toBe(100)
        ->and($result['components'][0]['effective_deduction_amount'])->toBeGreaterThan(60000)
        ->and($result['components'][2]['effective_deduction_amount'])->toBeGreaterThan(10000)
        ->and($result['effective_deduction_amount'])->toBe(100000)
        ->and($result['refund_amount'])->toBe(540100);
});
