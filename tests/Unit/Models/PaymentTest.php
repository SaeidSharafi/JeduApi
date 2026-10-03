<?php

declare(strict_types=1);

test('to array', function (): void {
    $payment = App\Models\Payment::factory()->create()->fresh();

    expect($payment->toArray())
        ->toEqual([
            'id'                     => $payment->id,
            'customer_id'            => $payment->customer_id,
            'order_id'               => $payment->order_id,
            'amount'                 => $payment->amount,
            'method'                 => $payment->method->value,
            'purpose'                => $payment->purpose->value,
            'status'                 => $payment->status->value,
            'data'                   => $payment->data,
            'admin_notes'            => $payment->admin_notes,
            'created_at'             => $payment->created_at?->utc()->toJSON(),
            'updated_at'             => $payment->updated_at?->utc()->toJSON(),
            'created_by'             => $payment->created_by,
            'last_gateway_reference' => $payment->last_gateway_reference,
            'attempt_count'          => $payment->attempt_count,
            'last_attempted_at'      => $payment->last_attempted_at?->utc()->toJSON(),
            'ip_address'             => $payment->ip_address,
            'user_agent'             => $payment->user_agent,
            'uuid'                   => $payment->uuid,
        ]);
});

test('order relationship', function (): void {
    $order   = App\Models\Order::factory()->create();
    $payment = App\Models\Payment::factory()->create([
        'order_id' => $order->id,
    ]);

    expect($payment->order)
        ->toBeInstanceOf(App\Models\Order::class)
        ->and($payment->order->id)
        ->toEqual($order->id);
});

test('customer relationship', function (): void {
    $customer = App\Models\User::factory()->create();
    $payment  = App\Models\Payment::factory()->create([
        'customer_id' => $customer->id,
    ]);

    expect($payment->customer)
        ->toBeInstanceOf(App\Models\User::class)
        ->and($payment->customer->id)
        ->toEqual($customer->id);
});
