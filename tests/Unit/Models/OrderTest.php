<?php

declare(strict_types=1);

test('to array', function () {
    $order = App\Models\Order::factory()->create();

    expect($order->toArray())
        ->toEqual([
            'id'                     => $order->id,
            'increment_id'           => $order->increment_id,
            'status'                 => $order->status->value,
            'customer_id'            => $order->customer_id,
            'customer_email'         => $order->customer_email,
            'customer_phone'         => $order->customer_phone,
            'customer_first_name'    => $order->customer_first_name,
            'customer_last_name'     => $order->customer_last_name,
            'customer_snapshot_json' => $order->customer_snapshot_json,
            'total_item_count'       => $order->total_item_count,
            'total_qty_ordered'      => $order->total_qty_ordered,
            'subtotal'               => $order->subtotal,
            'discount_amount'        => $order->discount_amount,
            'tax_amount'             => $order->tax_amount,
            'grand_total'            => $order->grand_total,
            'currency_code'          => $order->currency_code,
            'applied_coupon_code'    => $order->applied_coupon_code,
            'admin_notes'            => $order->admin_notes,
            'created_at'             => $order->created_at->format('Y-m-d H:i:s'),
            'updated_at'             => $order->updated_at->format('Y-m-d H:i:s'),
        ]);
});

test('items relationship', function () {
    $order = App\Models\Order::factory()->create();
    $item  = App\Models\OrderItem::factory()->create([
        'order_id' => $order->id,
    ]);

    expect($order->items)
        ->toHaveCount(1)
        ->and($order->items->first())
        ->toBeInstanceOf(App\Models\OrderItem::class)
        ->and($order->items->first()->id)
        ->toEqual($item->id);

    $items = App\Models\OrderItem::factory()->count(3)->create([
        'order_id' => $order->id,
    ]);
    $order->refresh();
    expect($order->items)
        ->toHaveCount(4);
});

test('payments relationship', function () {
    $order   = App\Models\Order::factory()->create();
    $payment = App\Models\Payment::factory()->create([
        'order_id' => $order->id,
    ]);

    expect($order->payments)
        ->toHaveCount(1)
        ->and($order->payments->first())
        ->toBeInstanceOf(App\Models\Payment::class)
        ->and($order->payments->first()->id)
        ->toEqual($payment->id);

    $payments = App\Models\Payment::factory()->count(3)->create([
        'order_id' => $order->id,
    ]);
    $order->refresh();
    expect($order->payments)
        ->toHaveCount(4);
});

test('payment status', function () {
    $order = App\Models\Order::factory()->create();
    expect($order->payment_status)
        ->toEqual(\App\Enums\Order\OrderPaymentStatusEnum::PENDING->value);

    $payment = App\Models\Payment::factory()->create([
        'order_id' => $order->id,
        'amount'   => $order->grand_total,
        'status'   => \App\Enums\Payment\PaymentStatusEnum::COMPLETED,
    ]);

    expect($order->payment_status)
        ->toEqual(\App\Enums\Order\OrderPaymentStatusEnum::PAID->value);

    $order = App\Models\Order::factory()->create(
        [
            'grand_total' => 5000,
        ]
    );
    $payment = App\Models\Payment::factory()->create([
        'order_id' => $order->id,
        'amount'   => 1000,
        'status'   => \App\Enums\Payment\PaymentStatusEnum::COMPLETED,
    ]);
    expect($order->payment_status)
        ->toEqual(\App\Enums\Order\OrderPaymentStatusEnum::PARTIALLY_PAID->value);

});

test('enrolments relationship', function () {
    $order = App\Models\Order::factory()->create();
    $enrolment = App\Models\Enrolment::factory()->create([
        'order_id' => $order->id,
    ]);

    expect($order->enrolments)
        ->toHaveCount(1)
        ->and($order->enrolments->first())
        ->toBeInstanceOf(App\Models\Enrolment::class)
        ->and($order->enrolments->first()->id)
        ->toEqual($enrolment->id);

    $enrolments = App\Models\Enrolment::factory()->count(3)->create([
        'order_id' => $order->id,
    ]);
    $order->refresh();
    expect($order->enrolments)
        ->toHaveCount(4);
});

test('generate increment ID', function () {
    $order          = App\Models\Order::factory()->create();
    $newIncrementId = App\Models\Order::generateIncrementId();

    expect($newIncrementId)
        ->toBeString()
        ->and((int) $newIncrementId)
        ->toEqual((int) $order->increment_id + 1);
});

