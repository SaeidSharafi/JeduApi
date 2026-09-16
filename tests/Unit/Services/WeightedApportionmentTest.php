<?php

declare(strict_types=1);

use App\Services\WeightedApportionment;

covers(WeightedApportionment::class);

beforeEach(function (): void {
    $this->apportionment = new WeightedApportionment();
});

it('apportions an amount by weight summing exactly to the amount', function (): void {
    expect($this->apportionment->distribute(30000, [200000, 100000]))->toBe([20000, 10000])
        ->and($this->apportionment->distribute(40000, [100000, 300000]))->toBe([10000, 30000]);
});

it('uses largest-remainder rounding with a stable lowest-index tie-break', function (): void {
    expect($this->apportionment->distribute(100, [33333, 33333, 33334]))->toBe([33, 33, 34])
        ->and($this->apportionment->distribute(100, [100000, 100000, 100000]))->toBe([34, 33, 33]);
});

it('ranks the rounding remainder rather than the raw product', function (): void {
    // Products 15 and 35 leave the same remainder (5), so the lowest index wins.
    expect($this->apportionment->distribute(5, [3, 7]))->toBe([2, 3]);
});

it('distributes multiple remainder units to the highest remainders in order', function (): void {
    expect($this->apportionment->distribute(2, [1, 1, 1]))->toBe([1, 1, 0]);
});

it('keeps the weighted path when the total weight is one', function (): void {
    expect($this->apportionment->distribute(100, [1, 0]))->toBe([100, 0]);
});

it('splits equally when every weight is zero', function (): void {
    expect($this->apportionment->distribute(100, [0, 0, 0]))->toBe([34, 33, 33]);
});

it('returns an empty split for no buckets and all zeros for a zero amount', function (): void {
    expect($this->apportionment->distribute(5000, []))->toBe([])
        ->and($this->apportionment->distribute(0, [100000, 200000]))->toBe([0, 0]);
});

it('clamps negative weights and amounts', function (): void {
    expect($this->apportionment->distribute(-100, [100000, 100000]))->toBe([0, 0])
        ->and($this->apportionment->distribute(100, [-5, 100000]))->toBe([0, 100]);
});
