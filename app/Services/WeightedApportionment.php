<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Deterministic largest-remainder apportionment.
 *
 * Splits an integer amount across weighted buckets so the parts always sum
 * exactly to the amount, with a stable lowest-index tie-break. Used both for
 * distributing a full-order deduction across commercial units and for
 * distributing a Bundle deduction across its component snapshots.
 */
final class WeightedApportionment
{
    /**
     * @param  list<int>  $weights
     * @return list<int>
     */
    public function distribute(int $amount, array $weights): array
    {
        $amount = max(0, $amount);
        $count  = count($weights);
        if ($count === 0) {
            return [];
        }

        $weights     = array_map(fn (int $weight): int => max(0, $weight), $weights);
        $totalWeight = array_sum($weights);
        if ($totalWeight <= 0) {
            return $this->equalShares($amount, $count);
        }

        $shares     = [];
        $remainders = [];
        $allocated  = 0;
        foreach ($weights as $index => $weight) {
            $product            = $amount * $weight;
            $shares[$index]     = intdiv($product, $totalWeight);
            $remainders[$index] = $product % $totalWeight;
            $allocated += $shares[$index];
        }

        $remaining = $amount - $allocated;
        if ($remaining > 0) {
            $order = range(0, $count - 1);
            usort($order, function (int $left, int $right) use ($remainders): int {
                return $remainders[$right] <=> $remainders[$left] ?: $left <=> $right;
            });

            for ($position = 0; $position < $remaining && $position < $count; $position++) {
                $shares[$order[$position]]++;
            }
        }

        ksort($shares);

        return array_values($shares);
    }

    /**
     * @return list<int>
     */
    private function equalShares(int $amount, int $count): array
    {
        $base      = intdiv($amount, $count);
        $remainder = $amount % $count;

        $shares = [];
        for ($index = 0; $index < $count; $index++) {
            $shares[$index] = $base + ($index < $remainder ? 1 : 0);
        }

        return $shares;
    }
}
