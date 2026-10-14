<?php

declare(strict_types=1);

use App\Data\Admin\Discounts\CalculatedOrderItemData;
use App\Data\Admin\Discounts\OrderContextData;
use App\Enums\Order\OrderItemPaymentTypeEnum;

describe('CalculatedOrderItemData::applyDiscount', function (): void {
    it('never marks down a prepayment line and reports zero applied', function (): void {
        $item = discountPrepaymentLine(price: 100000, prepaymentAmount: 25000, qty: 2);

        $applied = $item->applyDiscount(50000);

        expect($applied)->toBe(0)
            ->and($item->discount_amount)->toBe(0)
            ->and($item->total)->toBe(50000);
    });

    it('never marks down a bundle line', function (): void {
        $item = discountBundleLine(price: 30000, qty: 1);

        $applied = $item->applyDiscount(30000);

        expect($applied)->toBe(0)
            ->and($item->discount_amount)->toBe(0)
            ->and($item->total)->toBe(30000);
    });

    it('applies the requested amount and reports it, clamped to the line total', function (int $requested, int $applied, int $remaining): void {
        $item = discountFullPaymentLine(price: 20000, qty: 1);

        expect($item->applyDiscount($requested))->toBe($applied)
            ->and($item->discount_amount)->toBe($applied)
            ->and($item->total)->toBe($remaining);
    })->with([
        'partial'  => [5000, 5000, 15000],
        'exact'    => [20000, 20000, 0],
        'over cap' => [50000, 20000, 0],
        'negative' => [-1000, 0, 20000],
    ]);

    it('accumulates successive markdowns from stacked promotions', function (): void {
        $item = discountFullPaymentLine(price: 20000, qty: 1);

        expect($item->applyDiscount(5000))->toBe(5000)
            ->and($item->applyDiscount(3000))->toBe(3000)
            ->and($item->discount_amount)->toBe(8000)
            ->and($item->total)->toBe(12000)
            ->and($item->applyDiscount(99999))->toBe(12000)
            ->and($item->discount_amount)->toBe(20000)
            ->and($item->total)->toBe(0);
    });
});

describe('OrderContextData::discountableItems', function (): void {
    it('excludes prepayment and bundle lines and totals the rest', function (): void {
        $full    = discountFullPaymentLine(price: 30000);
        $prepaid = discountPrepaymentLine(price: 40000, prepaymentAmount: 10000);
        $bundle  = discountBundleLine(price: 50000);
        $context = discountOrderContext([$full, $prepaid, $bundle]);

        expect($context->discountableItems()->all())->toBe([$full])
            ->and($context->discountableTotal())->toBe(30000);
    });

    it('is empty for a deposit-only cart', function (): void {
        $context = discountOrderContext([discountPrepaymentLine(price: 40000, prepaymentAmount: 10000, qty: 2)]);

        expect($context->discountableItems())->toBeEmpty()
            ->and($context->discountableTotal())->toBe(0);
    });

    it('reports the current worth of lines after an earlier markdown', function (): void {
        $first   = discountFullPaymentLine(price: 30000);
        $second  = discountFullPaymentLine(price: 10000);
        $context = discountOrderContext([$first, $second]);

        $first->applyDiscount(5000);

        expect($context->discountableTotal())->toBe(35000);
    });
});
