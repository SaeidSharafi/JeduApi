<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Distributes one Bundle-level deduction policy across the immutable component
 * snapshots of a single Bundle Purchase.
 *
 * The policy amount (a percentage of the snapshotted Bundle Base Value or a
 * fixed amount) is first weighted by each component's snapshotted base price.
 * Each component can only absorb a deduction up to the allocation it was
 * actually paid, so any share that lands on a zero-paid or insufficiently
 * allocated component is redistributed to components with remaining paid value.
 * The result is a deterministic, reproducible, and auditable split.
 */ final class BundleRefundDeductionCalculator
{
    public function __construct(private readonly WeightedApportionment $apportionment) {}

    /**
     * @param  list<array{order_item_id: int, product_delivery_option_id: int, base_price: int, paid_amount: int}>  $components
     * @return array{
     *     policy_deduction_amount: int,
     *     effective_deduction_amount: int,
     *     refund_amount: int,
     *     components: list<array{
     *         order_item_id: int,
     *         product_delivery_option_id: int,
     *         base_price: int,
     *         paid_amount: int,
     *         policy_deduction_amount: int,
     *         effective_deduction_amount: int,
     *         redistributed_amount: int,
     *         refund_amount: int
     *     }>
     * }
     */
    public function calculate(int $paidAmount, array $components, int $policyDeductionAmount): array
    {
        $paidAmount  = max(0, $paidAmount);
        $policyTotal = max(0, $policyDeductionAmount);

        $basePrices = array_map(
            fn (array $component): int => max(0, (int) $component['base_price']),
            $components,
        );
        $paidShares = array_map(
            fn (array $component): int => max(0, (int) $component['paid_amount']),
            $components,
        );

        $policyShares = $this->apportionment->distribute($policyTotal, $basePrices);
        $effective    = [];
        foreach ($components as $index => $component) {
            $effective[$index] = min($policyShares[$index], $paidShares[$index]);
        }

        $this->redistributeShortfall($policyTotal, $paidShares, $effective);

        $calculatedComponents = [];
        foreach ($components as $index => $component) {
            $effectiveDeduction = max(0, $effective[$index]);
            $paid               = $paidShares[$index];

            $calculatedComponents[] = [
                'order_item_id'              => (int) $component['order_item_id'],
                'product_delivery_option_id' => (int) $component['product_delivery_option_id'],
                'base_price'                 => $basePrices[$index],
                'paid_amount'                => $paid,
                'policy_deduction_amount'    => $policyShares[$index],
                'effective_deduction_amount' => $effectiveDeduction,
                'redistributed_amount'       => $effectiveDeduction - $policyShares[$index],
                'refund_amount'              => max(0, $paid - $effectiveDeduction),
            ];
        }

        $effectiveTotal = array_sum($effective);

        return [
            'policy_deduction_amount'    => $policyTotal,
            'effective_deduction_amount' => min($effectiveTotal, $paidAmount),
            'refund_amount'              => max(0, $paidAmount - $effectiveTotal),
            'components'                 => $calculatedComponents,
        ];
    }

    /**
     * @param  list<int>  $paidShares
     * @param  array<int, int>  $effective
     */
    private function redistributeShortfall(int $policyTotal, array $paidShares, array &$effective): void
    {
        $shortfall = $policyTotal - array_sum($effective);

        while ($shortfall > 0) {
            $capacities = [];
            $eligible   = [];
            foreach ($paidShares as $index => $paid) {
                $capacity = $paid - $effective[$index];
                if ($capacity > 0) {
                    $eligible[]         = $index;
                    $capacities[$index] = $capacity;
                }
            }

            if ($eligible === []) {
                return;
            }

            $distribution = $this->apportionment->distribute($shortfall, array_values($capacities));
            $applied      = 0;
            foreach ($eligible as $position => $index) {
                $increment = min($distribution[$position], $capacities[$index]);
                $effective[$index] += $increment;
                $applied           += $increment;
            }

            if ($applied === 0) {
                return;
            }

            $shortfall -= $applied;
        }
    }
}
