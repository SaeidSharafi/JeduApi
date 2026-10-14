<?php

declare(strict_types=1);

use App\Data\Admin\Discounts\CalculatedOrderItemData;
use App\Data\Admin\Discounts\OrderContextData;
use App\Enums\Order\OrderItemPaymentTypeEnum;
use App\Models\ProductDeliveryOption;
use App\Models\User;
use App\Services\Discounts\Cart\Actions\ApplyFixedAmountOffAction;
use App\Services\Discounts\Configs\ApplyFixedAmountOffData;
use App\Services\WeightedApportionment;

/**
 * Build a line whose `total` may differ from the delivery option's list price,
 * which is how product-level promotions appear in the order context.
 */
function fixedAmountItem(
    int $total,
    ?int $listPrice = null,
    OrderItemPaymentTypeEnum $paymentType = OrderItemPaymentTypeEnum::FULL_PAYMENT,
    bool $isBundle = false,
): CalculatedOrderItemData {
    return new CalculatedOrderItemData(
        product_delivery_option: ProductDeliveryOption::factory()->make(['price' => $listPrice ?? $total]),
        qty: 1,
        payment_type: $paymentType,
        price: $total,
        total: $total,
        is_bundle: $isBundle,
    );
}

/**
 * @param  array<int, CalculatedOrderItemData>  $items
 */
function fixedAmountContext(array $items, int $subtotalAllItems): OrderContextData
{
    return OrderContextData::from([
        'customer'                    => User::factory()->make(),
        'items'                       => $items,
        'subtotal_full_payment_items' => collect($items)
            ->where('payment_type', OrderItemPaymentTypeEnum::FULL_PAYMENT)
            ->sum('total'),
        'subtotal_all_items' => $subtotalAllItems,
    ]);
}

describe('ApplyFixedAmountOffAction', function (): void {
    test('it distributes flat discount proportionally across items', function (): void {
        $action = new ApplyFixedAmountOffAction(new WeightedApportionment());
        $config = new ApplyFixedAmountOffData(amount: 3000); // 3000 total discount

        $item1 = new CalculatedOrderItemData(
            product_delivery_option: ProductDeliveryOption::factory()->make(),
            qty: 1, payment_type: OrderItemPaymentTypeEnum::FULL_PAYMENT,
            price: 2000, total: 2000
        );
        $item2 = new CalculatedOrderItemData(
            product_delivery_option: ProductDeliveryOption::factory()->make(),
            qty: 1, payment_type: OrderItemPaymentTypeEnum::FULL_PAYMENT,
            price: 8000, total: 8000
        );

        $context = OrderContextData::from([
            'customer'                    => User::factory()->make(),
            'items'                       => [$item1, $item2],
            'subtotal_full_payment_items' => 10000,
            'subtotal_all_items'          => 10000,
        ]);

        $action->apply($context, $config);

        // Item 1 represents 20% of the total, should receive 20% of 3000 = 600
        expect($context->items[0]->discount_amount)->toBe(600)
            ->and($context->items[0]->total)->toBe(1400);

        // Item 2 represents 80% of the total, should receive 80% of 3000 = 2400
        expect($context->items[1]->discount_amount)->toBe(2400)
            ->and($context->items[1]->total)->toBe(5600);
    });

    test('it caps the discount to the total cart value', function (): void {
        $action = new ApplyFixedAmountOffAction(new WeightedApportionment());
        $config = new ApplyFixedAmountOffData(amount: 10000); // More than cart value

        $item1 = new CalculatedOrderItemData(
            product_delivery_option: ProductDeliveryOption::factory()->make(),
            qty: 1, payment_type: OrderItemPaymentTypeEnum::FULL_PAYMENT,
            price: 4000, total: 4000
        );

        $context = OrderContextData::from([
            'customer'                    => User::factory()->make(),
            'items'                       => [$item1],
            'subtotal_full_payment_items' => 4000,
            'subtotal_all_items'          => 4000,
        ]);

        $action->apply($context, $config);

        expect($context->items[0]->discount_amount)->toBe(4000)
            ->and($context->items[0]->total)->toBe(0);
    });

    test('it distributes the flat discount over standalone lines only, leaving a bundle line untouched', function (): void {
        $action = new ApplyFixedAmountOffAction(new WeightedApportionment());
        $config = new ApplyFixedAmountOffData(amount: 3000); // 3000 total discount

        $standalone = new CalculatedOrderItemData(
            product_delivery_option: ProductDeliveryOption::factory()->make(),
            qty: 1, payment_type: OrderItemPaymentTypeEnum::FULL_PAYMENT,
            price: 8000, total: 8000
        );
        $bundle = new CalculatedOrderItemData(
            product_delivery_option: ProductDeliveryOption::factory()->make(),
            qty: 1, payment_type: OrderItemPaymentTypeEnum::FULL_PAYMENT,
            price: 2000, total: 2000,
            is_bundle: true
        );

        $context = OrderContextData::from([
            'customer'                    => User::factory()->make(),
            'items'                       => [$standalone, $bundle],
            'subtotal_full_payment_items' => 10000,
            'subtotal_all_items'          => 10000,
        ]);

        $action->apply($context, $config);

        // The bundle is excluded from the distribution weight, so the standalone
        // line (the only eligible line) absorbs the full 3000.
        expect($context->items[0]->discount_amount)->toBe(3000)
            ->and($context->items[0]->total)->toBe(5000)
            ->and($context->items[1]->discount_amount)->toBe(0)
            ->and($context->items[1]->total)->toBe(2000);
    });

    test('it applies no discount when the cart holds only a bundle line', function (): void {
        $action = new ApplyFixedAmountOffAction(new WeightedApportionment());
        $config = new ApplyFixedAmountOffData(amount: 3000);

        $bundle = new CalculatedOrderItemData(
            product_delivery_option: ProductDeliveryOption::factory()->make(),
            qty: 1, payment_type: OrderItemPaymentTypeEnum::FULL_PAYMENT,
            price: 2000, total: 2000,
            is_bundle: true
        );

        $context = OrderContextData::from([
            'customer'                    => User::factory()->make(),
            'items'                       => [$bundle],
            'subtotal_full_payment_items' => 2000,
            'subtotal_all_items'          => 2000,
        ]);

        $action->apply($context, $config);

        expect($context->items[0]->discount_amount)->toBe(0)
            ->and($context->items[0]->total)->toBe(2000);
    });

    test('it never marks down a prepayment line and gives the whole amount to the full-payment line', function (): void {
        $action = new ApplyFixedAmountOffAction(new WeightedApportionment());
        $config = new ApplyFixedAmountOffData(amount: 3000);

        $prepayment = new CalculatedOrderItemData(
            product_delivery_option: ProductDeliveryOption::factory()->make(),
            qty: 2, payment_type: OrderItemPaymentTypeEnum::PRE_PAYMENT,
            price: 50000, total: 20000
        );
        $fullPayment = new CalculatedOrderItemData(
            product_delivery_option: ProductDeliveryOption::factory()->make(),
            qty: 1, payment_type: OrderItemPaymentTypeEnum::FULL_PAYMENT,
            price: 10000, total: 10000
        );

        $context = OrderContextData::from([
            'customer'                    => User::factory()->make(),
            'items'                       => [$prepayment, $fullPayment],
            'subtotal_full_payment_items' => 10000,
            'subtotal_all_items'          => 110000,
        ]);

        $action->apply($context, $config);

        expect($context->items[0]->discount_amount)->toBe(0)
            ->and($context->items[0]->total)->toBe(20000)
            ->and($context->items[1]->discount_amount)->toBe(3000)
            ->and($context->items[1]->total)->toBe(7000);
    });

    test('it applies nothing on a deposit-only cart', function (): void {
        $action = new ApplyFixedAmountOffAction(new WeightedApportionment());
        $config = new ApplyFixedAmountOffData(amount: 3000);

        $prepayment = new CalculatedOrderItemData(
            product_delivery_option: ProductDeliveryOption::factory()->make(),
            qty: 2, payment_type: OrderItemPaymentTypeEnum::PRE_PAYMENT,
            price: 50000, total: 20000
        );

        $context = OrderContextData::from([
            'customer'                    => User::factory()->make(),
            'items'                       => [$prepayment],
            'subtotal_full_payment_items' => 0,
            'subtotal_all_items'          => 100000,
        ]);

        $action->apply($context, $config);

        expect($context->items[0]->discount_amount)->toBe(0)
            ->and($context->items[0]->total)->toBe(20000);
    });

    test('it removes the full advertised amount from lines that already carry a product-level discount', function (): void {
        $discounted = fixedAmountItem(total: 90000, listPrice: 100000);
        $context    = fixedAmountContext([$discounted], subtotalAllItems: 100000);

        (new ApplyFixedAmountOffAction(new WeightedApportionment()))->apply($context, new ApplyFixedAmountOffData(amount: 5000));

        expect($discounted->discount_amount)->toBe(5000)
            ->and($discounted->total)->toBe(85000);
    });

    test('it still removes the advertised amount after an earlier stacked markdown', function (): void {
        $first   = fixedAmountItem(total: 60000);
        $second  = fixedAmountItem(total: 40000);
        $context = fixedAmountContext([$first, $second], subtotalAllItems: 100000);

        // An earlier stacked promotion (lower priority) already marked the lines down.
        $first->applyDiscount(6000);
        $second->applyDiscount(4000);
        $worthBefore = $context->discountableTotal();

        (new ApplyFixedAmountOffAction(new WeightedApportionment()))->apply($context, new ApplyFixedAmountOffData(amount: 5000));

        expect($context->discountableTotal())->toBe($worthBefore - 5000)
            ->and($context->items->sum('discount_amount'))->toBe(15000);
    });

    test('it caps the markdown at the discountable lines worth and never takes a line below zero', function (): void {
        $first   = fixedAmountItem(total: 30000, listPrice: 100000);
        $second  = fixedAmountItem(total: 10000, listPrice: 100000);
        $context = fixedAmountContext([$first, $second], subtotalAllItems: 200000);

        (new ApplyFixedAmountOffAction(new WeightedApportionment()))->apply($context, new ApplyFixedAmountOffData(amount: 100000));

        expect($first->discount_amount)->toBe(30000)
            ->and($first->total)->toBe(0)
            ->and($second->discount_amount)->toBe(10000)
            ->and($second->total)->toBe(0);
    });

    test('it splits the target so the per-line shares sum exactly to it', function (array $weights, int $target, array $expectedShares): void {
        $items = array_map(
            fn (int $weight): CalculatedOrderItemData => fixedAmountItem(total: $weight),
            $weights,
        );

        $weightTotal     = array_sum($weights);
        $effectiveTarget = min($target, $weightTotal);
        $context         = fixedAmountContext($items, subtotalAllItems: $weightTotal);

        (new ApplyFixedAmountOffAction(new WeightedApportionment()))->apply($context, new ApplyFixedAmountOffData(amount: $target));

        $shares = array_map(
            fn (CalculatedOrderItemData $item): int => $item->discount_amount,
            $items,
        );

        expect($shares)->toBe($expectedShares)
            ->and(array_sum($shares))->toBe($effectiveTarget)
            ->and($context->items->sum('total'))->toBe($weightTotal - $effectiveTarget);

        foreach ($weights as $index => $weight) {
            expect($shares[$index])->toBeLessThanOrEqual($weight)
                ->and($items[$index]->total)->toBe($weight - $expectedShares[$index])
                ->and($items[$index]->total)->toBeGreaterThanOrEqual(0);
        }
    })->with([
        'target 100 over 1/3/3'                              => [[1, 3, 3], 100, [1, 3, 3]],
        'target 1000 over 333/333/334'                       => [[333, 333, 334], 1000, [333, 333, 334]],
        'equal weights with a target that does not divide'   => [[10, 10, 10], 10, [4, 3, 3]],
        'zero-worth line'                                    => [[0, 5000, 5000], 3000, [0, 1500, 1500]],
        'half units must not overshoot the target'           => [[5, 5], 5, [3, 2]],
        'equal remainders give the unit to the earlier line' => [[333, 333, 334], 500, [167, 166, 167]],
        'largest remainder carries the leftover unit'        => [[100, 300, 300], 400, [57, 172, 171]],
        'leftover unit lands on a line worth exactly one'    => [[1, 1], 1, [1, 0]],
        'zero-worth line does not consume the leftover unit' => [[0, 1, 1], 1, [0, 1, 0]],
    ]);

    test('it gives nothing to deposit and bundle lines while the standalone line takes the target', function (): void {
        $deposit    = fixedAmountItem(total: 25000, paymentType: OrderItemPaymentTypeEnum::PRE_PAYMENT);
        $bundle     = fixedAmountItem(total: 20000, isBundle: true);
        $standalone = fixedAmountItem(total: 10000);
        $context    = fixedAmountContext([$deposit, $bundle, $standalone], subtotalAllItems: 125000);

        (new ApplyFixedAmountOffAction(new WeightedApportionment()))->apply($context, new ApplyFixedAmountOffData(amount: 7000));

        expect($deposit->discount_amount)->toBe(0)
            ->and($deposit->total)->toBe(25000)
            ->and($bundle->discount_amount)->toBe(0)
            ->and($bundle->total)->toBe(20000)
            ->and($standalone->discount_amount)->toBe(7000)
            ->and($standalone->total)->toBe(3000);
    });
});
