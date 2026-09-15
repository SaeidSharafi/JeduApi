<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\RefundValidationException;

/**
 * Plans the deduction split for one full-order refund.
 *
 * A mixed Order is refunded as commercial units: each standalone Order Item is
 * one unit and each Bundle Purchase is one indivisible unit. A percentage
 * policy is calculated per unit from its snapshotted base value; a fixed policy
 * is first apportioned across units by base-value weight and then distributed
 * within each Bundle by the Bundle refund rules. Every unit caps its effective
 * deduction at the value actually paid, so the effective total can never exceed
 * the refundable paid value and no unit can produce a negative refund.
 */
final class MixedOrderRefundCalculator
{
    public function __construct(
        private readonly BundleRefundDeductionCalculator $componentCalculator,
        private readonly WeightedApportionment $apportionment,
    ) {}

    /**
     * @param  list<array{
     *     base_value: int,
     *     paid_value: int,
     *     components: list<array{order_item_id: int, product_delivery_option_id: int, base_price: int, paid_amount: int}>
     * }>  $units
     * @return array{
     *     policy_deduction_amount: int,
     *     effective_deduction_amount: int,
     *     refund_amount: int,
     *     units: list<array{
     *         policy_deduction_amount: int,
     *         effective_deduction_amount: int,
     *         refund_amount: int,
     *         components: list<array{
     *             order_item_id: int,
     *             product_delivery_option_id: int,
     *             base_price: int,
     *             paid_amount: int,
     *             policy_deduction_amount: int,
     *             effective_deduction_amount: int,
     *             redistributed_amount: int,
     *             refund_amount: int
     *         }>
     *     }>
     * }
     */
    public function calculate(array $units, ?int $deductionAmount, ?int $deductionPercent): array
    {
        $targets = $this->resolvePolicyTargets($units, $deductionAmount, $deductionPercent);

        $calculated = [];
        foreach ($units as $index => $unit) {
            $calculated[] = $this->componentCalculator->calculate(
                max(0, (int) $unit['paid_value']),
                $unit['components'],
                $targets[$index],
            );
        }

        return [
            'policy_deduction_amount'    => (int) array_sum(array_column($calculated, 'policy_deduction_amount')),
            'effective_deduction_amount' => (int) array_sum(array_column($calculated, 'effective_deduction_amount')),
            'refund_amount'              => (int) array_sum(array_column($calculated, 'refund_amount')),
            'units'                      => $calculated,
        ];
    }

    /**
     * @param  list<array{base_value: int, paid_value: int, components: list<array<string, int>>}>  $units
     * @return list<int>
     */
    private function resolvePolicyTargets(array $units, ?int $deductionAmount, ?int $deductionPercent): array
    {
        $baseValues = array_map(
            fn (array $unit): int => max(0, (int) $unit['base_value']),
            $units,
        );

        if ($deductionPercent !== null) {
            $targets = array_map(
                fn (int $baseValue): int => (int) floor(($baseValue * $deductionPercent) / 100),
                $baseValues,
            );

            if ($deductionAmount !== null && array_sum($targets) !== $deductionAmount) {
                throw new RefundValidationException(__('messages.order.refund.deduction_conflict'));
            }

            return $targets;
        }

        return $this->apportionment->distribute(max(0, $deductionAmount ?? 0), $baseValues);
    }
}
