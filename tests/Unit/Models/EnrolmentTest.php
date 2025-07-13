<?php

it('to array', function () {

    $enrolment = \App\Models\Enrolment::factory()->create();

    $array = $enrolment->toArray();

    expect($array)->toBeArray()
        ->and($array)->toHaveKeys([
            'id',
            'order_id',
            'order_item_id',
            'customer_id',
            'product_delivery_option_id',
            'enrollment_status',
            'access_start_date',
            'access_end_date',
            'external_enrollment_id',
            'provisioning_data',
            'notes',
            'created_at',
            'updated_at',
        ]);
});

test('order relationship', function () {
    $enrolment = \App\Models\Enrolment::factory()->create();

    $order = $enrolment->order;

    expect($order)->toBeInstanceOf(\App\Models\Order::class)
        ->and($order->id)->toBe($enrolment->order_id);
});

test('order item relationship', function () {
    $enrolment = \App\Models\Enrolment::factory()->create();

    $orderItem = $enrolment->orderItem;

    expect($orderItem)->toBeInstanceOf(\App\Models\OrderItem::class)
        ->and($orderItem->id)->toBe($enrolment->order_item_id);
});

test('customer relationship', function () {
    $enrolment = \App\Models\Enrolment::factory()->create();

    $customer = $enrolment->customer;

    expect($customer)->toBeInstanceOf(\App\Models\User::class)
        ->and($customer->id)->toBe($enrolment->customer_id);
});
