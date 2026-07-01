<?php

declare(strict_types=1);

test('to array', function (): void {
    $orderItem = App\Models\OrderItem::factory()->create()->fresh();

    expect($orderItem->toArray())
        ->toEqual([
            'id'                            => $orderItem->id,
            'order_id'                      => $orderItem->order_id,
            'product_delivery_option_id'    => $orderItem->product_delivery_option_id,
            'qty_ordered'                   => $orderItem->qty_ordered,
            'payment_type'                  => $orderItem->payment_type->value,
            'name'                          => $orderItem->name,
            'sku'                           => $orderItem->sku,
            'vendor_id'                     => $orderItem->vendor_id,
            'product_data_snapshot_json'    => $orderItem->product_data_snapshot_json,
            'price'                         => $orderItem->price,
            'discount_amount'               => $orderItem->discount_amount,
            'tax_amount'                    => $orderItem->tax_amount,
            'total'                         => $orderItem->total,
            'prepayment_amount'             => $orderItem->prepayment_amount,
            'total_refunded'                => $orderItem->total_refunded,
            'qty_refunded'                  => $orderItem->qty_refunded,
            'status'                        => $orderItem->status->value,
            'created_at'                    => $orderItem->created_at?->utc()->toJSON(),
            'updated_at'                    => $orderItem->updated_at?->utc()->toJSON(),
            'applied_discount_details_json' => $orderItem->applied_discount_details_json,
            'pricing_metadata'              => $orderItem->pricing_metadata,
        ]);
});

test('relation order', function (): void {
    $orderItem = App\Models\OrderItem::factory()->create();

    expect($orderItem->order)
        ->toBeInstanceOf(App\Models\Order::class)
        ->and($orderItem->order->id)
        ->toEqual($orderItem->order_id);
});

test('relation vendor', function (): void {
    $orderItem = App\Models\OrderItem::factory()->create();

    expect($orderItem->vendor)
        ->toBeInstanceOf(App\Models\Vendor::class)
        ->and($orderItem->vendor->id)
        ->toEqual($orderItem->vendor_id);
});

test('relation enrollment', function (): void {
    $orderItem = App\Models\OrderItem::factory()->create();
    $enrolmet  = App\Models\Enrollment::factory()->create([
        'order_id'      => $orderItem->order_id,
        'order_item_id' => $orderItem->id,
        'customer_id'   => $orderItem->order->customer_id,
    ]);
    expect($orderItem->enrollment)
        ->toBeInstanceOf(App\Models\Enrollment::class)
        ->and($orderItem->enrollment->order_item_id)
        ->toEqual($orderItem->id);
});

test('refunds relationship', function (): void {
    $order = App\Models\Order::factory()->create();
    $item  = App\Models\OrderItem::factory()->create([
        'order_id' => $order->id,
    ]);
    $refund = App\Models\Refund::factory()->create([
        'order_id'      => $order->id,
        'order_item_id' => $item->id,
    ]);
    expect($item->refunds)
        ->toHaveCount(1)
        ->and($item->refunds->first())
        ->toBeInstanceOf(App\Models\Refund::class)
        ->and($item->refunds->first()->id)
        ->toEqual($refund->id);
});
