<?php

declare(strict_types=1);

use App\Actions\Admin\Discounts\RecordPromotionUsageAction;
use App\Models\DiscountCoupon;
use App\Models\DiscountPromotion;
use App\Models\DiscountPromotionUsage;
use App\Models\Order;

mutates(RecordPromotionUsageAction::class);

describe('RecordPromotionUsageAction', function (): void {
    uses()->group('unit', 'actions', 'discounts');

    test('records one slot for a promotion that marked a line down', function (): void {
        $promotion = DiscountPromotion::factory()->create();
        $coupon    = DiscountCoupon::factory()->create([
            'discount_promotion_id' => $promotion->id,
            'code'                  => 'SAVE10',
        ]);

        $order = Order::factory()->create([
            'applied_coupon_code'         => 'SAVE10',
            'applied_cart_discounts_json' => [
                [
                    'promotion_id'   => $promotion->id,
                    'promotion_name' => $promotion->name,
                    'applied_amount' => 5000,
                    'coupon_code'    => 'SAVE10',
                ],
            ],
        ]);

        app(RecordPromotionUsageAction::class)->handle($order);

        $usage = DiscountPromotionUsage::query()->where('order_id', $order->id)->sole();

        expect($usage->discount_promotion_id)->toBe($promotion->id)
            ->and($usage->discount_coupon_id)->toBe($coupon->id)
            ->and($usage->customer_id)->toBe($order->customer_id);
    });

    test('records a slot for a promotion that only granted a reward', function (): void {
        // Nothing was marked down, so the price-discount log is empty — but the
        // order owes a reward, and the caps for that promotion are counted from
        // these rows, so skipping it would leave the limits unconsumed.
        $promotion = DiscountPromotion::factory()->create();
        $coupon    = DiscountCoupon::factory()->create([
            'discount_promotion_id' => $promotion->id,
            'code'                  => 'WALLET1',
        ]);

        $order = Order::factory()->create([
            'applied_coupon_code'         => 'WALLET1',
            'applied_cart_discounts_json' => null,
            'reward_intents_json'         => [
                [
                    'promotion_id'   => $promotion->id,
                    'promotion_name' => $promotion->name,
                    'ledger_type'    => 'bonus',
                    'amount'         => 5000,
                ],
            ],
        ]);

        app(RecordPromotionUsageAction::class)->handle($order);

        $usage = DiscountPromotionUsage::query()->where('order_id', $order->id)->sole();

        expect($usage->discount_promotion_id)->toBe($promotion->id)
            ->and($usage->discount_coupon_id)->toBe($coupon->id);
    });

    test('records one slot when a promotion both discounted and granted a reward', function (): void {
        $promotion = DiscountPromotion::factory()->create();

        $order = Order::factory()->create([
            'applied_coupon_code'         => null,
            'applied_cart_discounts_json' => [
                ['promotion_id' => $promotion->id, 'applied_amount' => 5000, 'coupon_code' => null],
            ],
            'reward_intents_json' => [
                ['promotion_id' => $promotion->id, 'ledger_type' => 'bonus', 'amount' => 5000],
            ],
        ]);

        app(RecordPromotionUsageAction::class)->handle($order);

        expect(DiscountPromotionUsage::query()->where('order_id', $order->id)->count())->toBe(1);
    });

    test('records nothing for an order that used no promotion', function (): void {
        $order = Order::factory()->create([
            'applied_cart_discounts_json' => null,
            'reward_intents_json'         => null,
        ]);

        app(RecordPromotionUsageAction::class)->handle($order);

        expect(DiscountPromotionUsage::query()->where('order_id', $order->id)->count())->toBe(0);
    });
});
