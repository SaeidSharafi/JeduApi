<?php

declare(strict_types=1);

use App\Data\Admin\Discounts\CalculatedOrderItemData;
use App\Data\Admin\Discounts\OrderContextData;
use App\Enums\Order\OrderItemPaymentTypeEnum;
use App\Models\ProductDeliveryOption;
use App\Models\User;

function fullPaymentLine(int $price, int $qty = 1): CalculatedOrderItemData
{
    return new CalculatedOrderItemData(
        product_delivery_option: ProductDeliveryOption::factory()->make(['price' => $price]),
        qty: $qty,
        payment_type: OrderItemPaymentTypeEnum::FULL_PAYMENT,
        price: $price,
        total: $price * $qty,
    );
}

function prepaymentLine(int $price, int $prepaymentAmount, int $qty = 1): CalculatedOrderItemData
{
    return new CalculatedOrderItemData(
        product_delivery_option: ProductDeliveryOption::factory()->make([
            'price'             => $price,
            'prepayment_amount' => $prepaymentAmount,
        ]),
        qty: $qty,
        payment_type: OrderItemPaymentTypeEnum::PRE_PAYMENT,
        price: $price,
        total: $prepaymentAmount * $qty,
    );
}

function bundleLine(int $price, int $qty = 1): CalculatedOrderItemData
{
    return new CalculatedOrderItemData(
        product_delivery_option: ProductDeliveryOption::factory()->make(['price' => $price]),
        qty: $qty,
        payment_type: OrderItemPaymentTypeEnum::FULL_PAYMENT,
        price: $price,
        total: $price * $qty,
        is_bundle: true,
    );
}

/**
 * @param  array<int, CalculatedOrderItemData>  $items
 */
function contextOf(array $items): OrderContextData
{
    return OrderContextData::from([
        'customer'                    => User::factory()->make(),
        'items'                       => $items,
        'subtotal_full_payment_items' => collect($items)
            ->where('payment_type', OrderItemPaymentTypeEnum::FULL_PAYMENT)
            ->sum('total'),
        'subtotal_all_items' => collect($items)->sum('total'),
    ]);
}

describe('CalculatedOrderItemData::applyDiscount', function (): void {
    it('never marks down a prepayment line and reports zero applied', function (): void {
        $item = prepaymentLine(price: 100000, prepaymentAmount: 25000, qty: 2);

        $applied = $item->applyDiscount(50000);

        expect($applied)->toBe(0)
            ->and($item->discount_amount)->toBe(0)
            ->and($item->total)->toBe(50000);
    });

    it('never marks down a bundle line', function (): void {
        $item = bundleLine(price: 30000, qty: 1);

        $applied = $item->applyDiscount(30000);

        expect($applied)->toBe(0)
            ->and($item->discount_amount)->toBe(0)
            ->and($item->total)->toBe(30000);
    });

    it('applies the requested amount and reports it, clamped to the line total', function (int $requested, int $applied, int $remaining): void {
        $item = fullPaymentLine(price: 20000, qty: 1);

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
        $item = fullPaymentLine(price: 20000, qty: 1);

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
        $full    = fullPaymentLine(price: 30000);
        $prepaid = prepaymentLine(price: 40000, prepaymentAmount: 10000);
        $bundle  = bundleLine(price: 50000);
        $context = contextOf([$full, $prepaid, $bundle]);

        expect($context->discountableItems()->all())->toBe([$full])
            ->and($context->discountableTotal())->toBe(30000);
    });

    it('is empty for a deposit-only cart', function (): void {
        $context = contextOf([prepaymentLine(price: 40000, prepaymentAmount: 10000, qty: 2)]);

        expect($context->discountableItems())->toBeEmpty()
            ->and($context->discountableTotal())->toBe(0);
    });

    it('reports the current worth of lines after an earlier markdown', function (): void {
        $first   = fullPaymentLine(price: 30000);
        $second  = fullPaymentLine(price: 10000);
        $context = contextOf([$first, $second]);

        $first->applyDiscount(5000);

        expect($context->discountableTotal())->toBe(35000);
    });
});
