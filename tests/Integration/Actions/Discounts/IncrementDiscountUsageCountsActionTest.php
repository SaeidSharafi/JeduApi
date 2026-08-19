<?php

declare(strict_types=1);

use App\Actions\Admin\Discounts\IncrementDiscountUsageCountsAction;
use App\Enums\Order\OrderStatusEnum;
use App\Models\DiscountCoupon;
use App\Models\DiscountPromotion;
use App\Models\Order;

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
