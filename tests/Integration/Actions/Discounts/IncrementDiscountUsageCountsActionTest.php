<?php

declare(strict_types=1);

use App\Actions\Admin\Discounts\IncrementDiscountUsageCountsAction;
use App\Enums\Order\OrderStatusEnum;
use App\Models\DiscountCoupon;
use App\Models\DiscountPromotion;
use App\Models\Order;

mutates(IncrementDiscountUsageCountsAction::class);

describe('IncrementDiscountUsageCountsAction', function (): void {
    uses()->group('unit', 'actions', 'discounts');

    test('increments counters once and persists an idempotency flag', function (): void {
        $promotion = DiscountPromotion::factory()->create(['is_active' => true]);
        $coupon    = DiscountCoupon::factory()->create([
            'discount_promotion_id' => $promotion->id,
            'code'                  => 'SAVE10',
        ]);

        $order = Order::factory()->create([
            'status'                      => OrderStatusEnum::COMPLETED,
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

        $action = app(IncrementDiscountUsageCountsAction::class);

        $action->handle($order);
        $action->handle($order->fresh());

        expect($promotion->fresh()->total_usage_count)->toBe(1)
            ->and($coupon->fresh()->usage_count)->toBe(1)
            ->and($order->fresh()->discount_usage_incremented_at)->not->toBeNull();
    });

    test('does not increment counters when the idempotency flag is already set', function (): void {
        $promotion = DiscountPromotion::factory()->create(['is_active' => true]);
        DiscountCoupon::factory()->create([
            'discount_promotion_id' => $promotion->id,
            'code'                  => 'SAVE10',
        ]);

        $order = Order::factory()->create([
            'status'                      => OrderStatusEnum::COMPLETED,
            'applied_coupon_code'         => 'SAVE10',
            'applied_cart_discounts_json' => [
                [
                    'promotion_id'   => $promotion->id,
                    'promotion_name' => $promotion->name,
                    'applied_amount' => 5000,
                    'coupon_code'    => 'SAVE10',
                ],
            ],
            'discount_usage_incremented_at' => now(),
        ]);

        app(IncrementDiscountUsageCountsAction::class)->handle($order);

        expect($promotion->fresh()->total_usage_count)->toBe(0)
            ->and($order->fresh()->discount_usage_incremented_at)->not->toBeNull();
    });
});

describe('Historical discount usage', function (): void {
    it('preserves valid promotion accounting when historical snapshot entries are missing or deleted', function (): void {
        $promotion = DiscountPromotion::factory()->create();
        $deleted   = DiscountPromotion::factory()->create();
        $deletedId = $deleted->id;
        $deleted->delete();
        $order = Order::factory()->create([
            'applied_coupon_code'         => null,
            'applied_cart_discounts_json' => [
                ['applied_amount' => 100],
                ['promotion_id' => $deletedId, 'applied_amount' => 100],
                ['promotion_id' => $promotion->id, 'applied_amount' => 100],
            ],
        ]);

        app(IncrementDiscountUsageCountsAction::class)->handle($order);
        app(IncrementDiscountUsageCountsAction::class)->handle($order->fresh());

        expect($promotion->fresh()->total_usage_count)->toBe(1);
        expect($order->fresh()->discount_usage_incremented_at)->not->toBeNull();
    });

    it('increments a promotion without incrementing a coupon owned by another promotion', function (): void {
        $snapshotPromotion = DiscountPromotion::factory()->create();
        $couponOwner       = DiscountPromotion::factory()->create();
        $coupon            = DiscountCoupon::factory()->for($couponOwner, 'promotion')->create([
            'code' => 'OTHER-PROMOTION',
        ]);
        $order = Order::factory()->create([
            'applied_coupon_code'         => $coupon->code,
            'applied_cart_discounts_json' => [
                ['promotion_id' => $snapshotPromotion->id, 'applied_amount' => 100],
            ],
        ]);

        app(IncrementDiscountUsageCountsAction::class)->handle($order);

        expect($snapshotPromotion->fresh()->total_usage_count)->toBe(1)
            ->and($couponOwner->fresh()->total_usage_count)->toBe(0)
            ->and($coupon->fresh()->usage_count)->toBe(0)
            ->and($order->fresh()->discount_usage_incremented_at)->not->toBeNull();
    });
});
