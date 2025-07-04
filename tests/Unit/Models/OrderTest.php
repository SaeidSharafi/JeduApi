<?php

test('to array', function () {
    $order = \App\Models\Order::factory()->create();

    expect($order->toArray())
        ->toEqual([
            'id' => $order->id,
            'increment_id' => $order->increment_id,
            'status' => $order->status->value,
            'customer_id' => $order->customer_id,
            'customer_email' => $order->customer_email,
            'customer_phone' => $order->customer_phone,
            'customer_first_name' => $order->customer_first_name,
            'customer_last_name' => $order->customer_last_name,
            'customer_snapshot_json' => $order->customer_snapshot_json,
            'subtotal' => $order->subtotal,
            'discount_amount' => $order->discount_amount,
            'tax_amount' => $order->tax_amount,
            'grand_total' => $order->grand_total,
            'applied_coupon_code' => $order->applied_coupon_code,
            'admin_notes' => $order->admin_notes,
            'created_at' => $order->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $order->updated_at->format('Y-m-d H:i:s'),
        ])->toBeArray();
});

test('items relationship', function () {
    $order = \App\Models\Order::factory()->create();
    $item = \App\Models\OrderItem::factory()->create([
        'order_id' => $order->id,
    ]);

    expect($order->items)
        ->toHaveCount(1)
        ->and($order->items->first())
        ->toBeInstanceOf(\App\Models\OrderItem::class)
        ->and($order->items->first()->id)
        ->toEqual($item->id);

    $items = \App\Models\OrderItem::factory()->count(3)->create([
        'order_id' => $order->id,
    ]);
    $order->refresh();
    expect($order->items)
        ->toHaveCount(4);
});
test('generate increment ID', function () {
    $order = \App\Models\Order::factory()->create();
    $newIncrementId = \App\Models\Order::generateIncrementId();

    expect($newIncrementId)
        ->toBeString()
        ->and((int)$newIncrementId)
        ->toEqual((int)$order->increment_id + 1);
});
