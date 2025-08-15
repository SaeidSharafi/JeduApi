<?php

declare(strict_types=1);

use App\Enums\Order\OrderItemPaymentTypeEnum;
use App\Enums\Order\OrderPaymentStatusEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;

test('to array', function () {
    $order = Order::factory()->create()->fresh();

    expect($order->toArray())
        ->toEqual([
            'id'                          => $order->id,
            'increment_id'                => $order->increment_id,
            'status'                      => $order->status->value,
            'customer_id'                 => $order->customer_id,
            'customer_email'              => $order->customer_email,
            'customer_phone'              => $order->customer_phone,
            'customer_first_name'         => $order->customer_first_name,
            'customer_last_name'          => $order->customer_last_name,
            'customer_snapshot_json'      => $order->customer_snapshot_json,
            'total_item_count'            => $order->total_item_count,
            'total_qty_ordered'           => $order->total_qty_ordered,
            'subtotal'                    => $order->subtotal,
            'discount_amount'             => $order->discount_amount,
            'tax_amount'                  => $order->tax_amount,
            'grand_total'                 => $order->grand_total,
            'full_value_grand_total'      => $order->full_value_grand_total,
            'currency_code'               => $order->currency_code,
            'applied_cart_discounts_json' => $order->applied_cart_discounts_json,
            'applied_coupon_code'         => $order->applied_coupon_code,
            'admin_notes'                 => $order->admin_notes,
            'created_at'                  => $order->created_at->format('Y-m-d H:i:s'),
            'updated_at'                  => $order->updated_at->format('Y-m-d H:i:s'),
            'created_by'                  => $order->created_by,
            'payments'                    => $order->payments->toArray(),

        ]);
});

test('items relationship', function () {
    $order = Order::factory()->create();
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
    $order   = Order::factory()->create();
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
    $items = [
        [
            'qty_ordered'  => 1,
            'payment_type' => OrderItemPaymentTypeEnum::FULL_PAYMENT->value,
            'total'        => 10000,
            'price'        => 10000,
            'name'         => 'Workshop',
        ],
    ];
    $order = Order::factory()
        ->withCalculatedTotals($items)
        ->create()
        ->fresh();
    expect($order->payment_status)
        ->toEqual(OrderPaymentStatusEnum::PENDING->value);

    $payment = App\Models\Payment::factory()->create([
        'order_id' => $order->id,
        'amount'   => $order->grand_total,
        'status'   => PaymentStatusEnum::COMPLETED,
    ]);
    $order->refresh();
    expect($order->payment_status)
        ->toEqual(OrderPaymentStatusEnum::PAID->value);

    $items = [
        [
            'qty_ordered'  => 1,
            'payment_type' => OrderItemPaymentTypeEnum::PRE_PAYMENT->value,
            'total'        => 5000,
            'price'        => 10000,
            'name'         => 'Workshop',
        ],
    ];
    $order = Order::factory()
        ->withCalculatedTotals($items)
        ->create()
        ->fresh();

    $payment = App\Models\Payment::factory()->create([
        'order_id' => $order->id,
        'amount'   => 5000,
        'status'   => PaymentStatusEnum::COMPLETED,
    ]);
    $order->refresh();
    expect($order->payment_status)
        ->toEqual(OrderPaymentStatusEnum::PAID->value)
        ->and($order->overall_payment_status)
        ->toEqual(OrderPaymentStatusEnum::PARTIALLY_PAID->value);

});

test('enrolments relationship', function () {
    $order     = Order::factory()->create();
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
    $order          = Order::factory()->create();
    $newIncrementId = Order::generateIncrementId();

    expect($newIncrementId)
        ->toBeString()
        ->and((int) $newIncrementId)
        ->toEqual((int) $order->increment_id + 1);
});

test('totalPaid', function () {
    $order    = Order::factory()->create();
    $payment1 = App\Models\Payment::factory()->create([
        'order_id' => $order->id,
        'amount'   => 5000,
        'status'   => PaymentStatusEnum::COMPLETED,
    ]);
    $payment2 = App\Models\Payment::factory()->create([
        'order_id' => $order->id,
        'amount'   => 3000,
        'status'   => PaymentStatusEnum::COMPLETED,
    ]);

    expect($order->total_paid)
        ->toEqual(8000);

    // Add a payment with a different status
    $payment3 = App\Models\Payment::factory()->create([
        'order_id' => $order->id,
        'amount'   => 2000,
        'status'   => PaymentStatusEnum::PENDING,
    ]);

    expect($order->total_paid)
        ->toEqual(8000); // Should not include the pending payment

    // using withSum

    $orderWithSum = Order::withSum([
        'payments as completed_payments_sum_amount' => function (Builder $query) {
            $query->where('status', PaymentStatusEnum::COMPLETED);
        },
    ], 'amount')
        ->find($order->id);
    expect($orderWithSum->total_paid)
        ->toEqual(8000); // Should match the total paid from the payments relationship
});

test('paymentStatus edgecase', function () {
    $items = [
        [
            'qty_ordered'  => 1,
            'payment_type' => OrderItemPaymentTypeEnum::FULL_PAYMENT->value,
            'total'        => 10000,
            'price'        => 10000,
            'name'         => 'Workshop',
        ],
    ];
    $order = Order::factory()
        ->withCalculatedTotals($items)
        ->create()
        ->fresh();
    expect($order->payment_status)
        ->toEqual(OrderPaymentStatusEnum::PENDING->value);

    $payment = App\Models\Payment::factory()->create([
        'order_id' => $order->id,
        'amount'   => $order->grand_total - 100,
        'status'   => PaymentStatusEnum::COMPLETED,
    ]);
    $order->refresh();

    expect($order->payment_status)
        ->toEqual(OrderPaymentStatusEnum::PARTIALLY_PAID->value); // Should still be PAID as the first payment is completed
});
