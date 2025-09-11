<?php

declare(strict_types=1);

it('to array', function () {
    $refund = App\Models\Refund::factory()->create();

    expect($refund->toArray())->toBeArray()
        ->and($refund->toArray())->toHaveKeys([
            'id',
            'order_id',
            'order_item_id',
            'customer_id',
            'amount',
            'status',
            'transaction_details',
            'refunded_at',
            'created_at',
            'updated_at',
        ]);
});

it('casts', function () {
    $refund = App\Models\Refund::factory()->create();

    expect($refund->transaction_details)->toBeArray()
        ->and($refund->status)->toBeInstanceOf(App\Enums\Order\RefundStatusEnum::class)
        ->and($refund->refunded_at)->toBeInstanceOf(Carbon\CarbonImmutable::class);
});

it('relationships', function () {
    $refund = App\Models\Refund::factory()->create();

    expect($refund->order)->toBeInstanceOf(App\Models\Order::class)
        ->and($refund->orderItem)->toBeInstanceOf(App\Models\OrderItem::class)
        ->and($refund->customer)->toBeInstanceOf(App\Models\User::class);
});
