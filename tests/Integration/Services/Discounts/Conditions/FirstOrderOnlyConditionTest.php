<?php

declare(strict_types=1);

use App\Data\Admin\Discounts\OrderContextData;
use App\Enums\Order\OrderStatusEnum;
use App\Models\Order;
use App\Models\User;
use App\Services\Discounts\Cart\Conditions\FirstOrderOnlyCondition;
use App\Services\Discounts\Configs\FirstOrderOnlyData;

describe('FirstOrderOnlyCondition', function (): void {
    test('it passes when user has zero completed orders', function (): void {
        $condition = new FirstOrderOnlyCondition();
        $config = new FirstOrderOnlyData();
        $user = User::factory()->create();

        // User has a pending order, but no completed ones
        Order::factory()->create([
            'customer_id' => $user->id,
            'status' => OrderStatusEnum::PENDING->value,
        ]);

        $context = OrderContextData::from([
            'customer' => $user,
            'items' => [],
            'subtotal_full_payment_items' => 0,
            'subtotal_all_items' => 0,
        ]);

        expect($condition->passes($context, $config))->toBeTrue();
    });

    test('it fails when user has a completed order', function (): void {
        $condition = new FirstOrderOnlyCondition();
        $config = new FirstOrderOnlyData();
        $user = User::factory()->create();

        Order::factory()->create([
            'customer_id' => $user->id,
            'status' => OrderStatusEnum::COMPLETED->value,
        ]);

        $context = OrderContextData::from([
            'customer' => $user,
            'items' => [],
            'subtotal_full_payment_items' => 0,
            'subtotal_all_items' => 0,
        ]);

        expect($condition->passes($context, $config))->toBeFalse();
    });

    test('it fails when customer is null (guest)', function (): void {
        $condition = new FirstOrderOnlyCondition();
        $config = new FirstOrderOnlyData();

        $context = OrderContextData::from([
            'customer' => null,
            'items' => [],
            'subtotal_full_payment_items' => 0,
            'subtotal_all_items' => 0,
        ]);

        expect($condition->passes($context, $config))->toBeFalse();
    });
});
