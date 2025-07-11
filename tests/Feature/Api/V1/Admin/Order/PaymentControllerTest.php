<?php

declare(strict_types=1);

use App\Enums\PaymentMethodEnum;
use App\Enums\PaymentStatusEnum;

uses(Tests\AuthTestTrait::class);

beforeEach(function () {
    $this->customer = App\Models\User::factory()->create();
});
it('returns order payments list', function () {
    $this->authorized_user([App\Enums\PermissionEnum::ORDER_VIEW->value]);
    $order   = App\Models\Order::factory()->create();
    $payment = App\Models\Payment::factory()
        ->create([
            'order_id'    => $order->id,
            'customer_id' => $this->customer->id,
            'staff_id'    => $this->user->id,
            'amount'      => 1000,
            'method'      => PaymentMethodEnum::ONLINE_GATEWAY,
            'status'      => PaymentStatusEnum::COMPLETED,
            'admin_notes' => 'Test payment',
        ]);
    App\Models\Payment::factory()->create([
        'order_id' => $order->id,
    ]);
    $response = \Pest\Laravel\getJson("/api/v1/admin/order/{$order->id}/payment");
    $response->assertOk();
    $response->assertJsonStructure([
        'message',
        'data' => [
            [
                'id', 'order_id', 'customer_id', 'staff_id', 'amount',
                'method' => ['value', 'label'], 'status' => ['value', 'label'], 'admin_notes',
            ],
        ],
        'metadata',
    ]);
    $response->assertJsonCount(2, 'data');
    $response->assertJsonFragment([
        'id'          => $payment->id,
        'order_id'    => $order->id,
        'customer_id' => $this->customer->id,
        'staff_id'    => $this->user->id,
        'amount'      => $payment->amount,
        'method'      => [
            'label' => PaymentMethodEnum::ONLINE_GATEWAY->translate(),
            'value' => PaymentMethodEnum::ONLINE_GATEWAY->value,
        ],
        'status' => [
            'label' => PaymentStatusEnum::COMPLETED->translate(),
            'value' => PaymentStatusEnum::COMPLETED->value,
        ],
        'admin_notes' => $payment->admin_notes,
    ]);
});
it('returns order payment detail', function () {
    $this->authorized_user([App\Enums\PermissionEnum::ORDER_VIEW->value]);
    $order   = App\Models\Order::factory()->create();
    $payment = App\Models\Payment::factory()->create([
        'order_id'    => $order->id,
        'customer_id' => $this->customer->id,
        'staff_id'    => $this->user->id,
        'amount'      => 1000,
        'method'      => PaymentMethodEnum::ONLINE_GATEWAY,
        'status'      => PaymentStatusEnum::COMPLETED,
        'admin_notes' => 'Test payment',
    ]);
    $response = \Pest\Laravel\getJson("/api/v1/admin/order/{$order->id}/payment/{$payment->id}");
    $response->assertOk();
    $response->assertJsonStructure([
        'message',
        'data' => [
            'id', 'order_id', 'customer_id', 'staff_id', 'amount',
            'method' => ['value', 'label'], 'status' => ['value', 'label'], 'admin_notes',
        ],
        'metadata',
    ]);
    $response->assertJson([
        'data' => [
            'id'          => $payment->id,
            'order_id'    => $order->id,
            'customer_id' => $this->customer->id,
            'staff_id'    => $this->user->id,
            'amount'      => $payment->amount,
            'method'      => [
                'label' => PaymentMethodEnum::ONLINE_GATEWAY->translate(),
                'value' => PaymentMethodEnum::ONLINE_GATEWAY->value,
            ],
            'status' => [
                'label' => PaymentStatusEnum::COMPLETED->translate(),
                'value' => PaymentStatusEnum::COMPLETED->value,
            ],
            'admin_notes' => $payment->admin_notes,
        ],
    ]);
});
it('create payment successfully', function () {
    $product1 = App\Models\ProductDeliveryOption::factory()
        ->create([
            'price'                   => 1000,
            'is_prepayment_available' => false,
        ]);
    $product2 = App\Models\ProductDeliveryOption::factory()
        ->create([
            'price'                   => 2000,
            'is_prepayment_available' => false,
        ]);
    $order = App\Models\Order::factory()->create([
        'customer_id'      => $this->customer->id,
        'total_item_count' => 2,
        'subtotal'         => $product1->price + $product2->price,
        'grand_total'      => $product1->price + $product2->price,
        'discount_amount'  => 0,
        'tax_amount'       => 0,
    ])->fresh();

    App\Models\OrderItem::factory()
        ->create([
            'qty_ordered'                => 1,
            'product_delivery_option_id' => $product1->id,
            'total'                      => $product1->price,
            'price'                      => $product1->price,
            'discount_amount'            => 0,
            'order_id'                   => $order->id,
            'payment_type'               => App\Enums\OrderItemPaymentTypeEnum::FULL_PAYMENT->value,
            'tax_amount'                 => 0,
        ]);
    App\Models\OrderItem::factory()
        ->create([
            'qty_ordered'                => 1,
            'product_delivery_option_id' => $product2->id,
            'total'                      => $product2->price,
            'price'                      => $product2->price,
            'discount_amount'            => 0,
            'order_id'                   => $order->id,
            'payment_type'               => App\Enums\OrderItemPaymentTypeEnum::FULL_PAYMENT->value,
            'tax_amount'                 => 0,
        ]);
    $data = [
        'method'      => PaymentMethodEnum::ONLINE_GATEWAY,
        'status'      => PaymentStatusEnum::COMPLETED,
        'admin_notes' => 'Test payment',
    ];

    $this->authorized_user([App\Enums\PermissionEnum::ORDER_CREATE]);
    $response = $this->postJson(route('api.v1.admin.payment.store', ['order' => $order->id]), $data);
    $response->assertCreated()
        ->assertJsonStructure([
            'data' => [
                'id',
                'order_id',
                'customer_id',
                'staff_id',
                'amount',
                'method',
                'status',
                'admin_notes',
            ],
        ]);
    $order->refresh();

    $this->assertDatabaseHas('payments', [
        'order_id'    => $order->id,
        'customer_id' => $this->customer->id,
        'staff_id'    => $this->user->id,
        'amount'      => 3000,
        'method'      => PaymentMethodEnum::ONLINE_GATEWAY->value,
        'status'      => PaymentStatusEnum::COMPLETED->value,
        'admin_notes' => 'Test payment',
    ]);

    $this->assertEquals($order->total_paid, 3000);
    $this->assertEquals($order->balance_due, 0);
    $this->assertEquals($order->payment_status, App\Enums\OrderPaymentStatusEnum::PAID->value);
});

it('create partiall payment successfully', function () {
    $product1 = App\Models\ProductDeliveryOption::factory()
        ->create([
            'price'                   => 1000,
            'is_prepayment_available' => true,
            'prepayment_amount'       => 200,
        ]);
    $product2 = App\Models\ProductDeliveryOption::factory()
        ->create([
            'price'                   => 2000,
            'is_prepayment_available' => true,
            'prepayment_amount'       => 300,
        ]);
    $order = App\Models\Order::factory()->create([
        'customer_id'      => $this->customer->id,
        'total_item_count' => 2,
        'subtotal'         => $product1->price + $product2->price,
        'grand_total'      => $product1->price + $product2->price,
        'discount_amount'  => 0,
        'tax_amount'       => 0,
    ])->fresh();

    App\Models\OrderItem::factory()
        ->create([
            'qty_ordered'                => 1,
            'product_delivery_option_id' => $product1->id,
            'total'                      => $product1->price,
            'price'                      => $product1->price,
            'discount_amount'            => 0,
            'order_id'                   => $order->id,
            'payment_type'               => App\Enums\OrderItemPaymentTypeEnum::PRE_PAYMENT->value,
            'prepayment_amount'          => $product1->prepayment_amount,
            'tax_amount'                 => 0,
        ]);
    App\Models\OrderItem::factory()
        ->create([
            'qty_ordered'                => 1,
            'product_delivery_option_id' => $product2->id,
            'total'                      => $product2->price,
            'price'                      => $product2->price,
            'discount_amount'            => 0,
            'order_id'                   => $order->id,
            'payment_type'               => App\Enums\OrderItemPaymentTypeEnum::PRE_PAYMENT->value,
            'prepayment_amount'          => $product2->prepayment_amount,
            'tax_amount'                 => 0,
        ]);
    $data = [
        'method' => PaymentMethodEnum::ONLINE_GATEWAY,
        'status' => PaymentStatusEnum::COMPLETED,
        'data'   => [
            'transaction_id' => '123456789',
        ],
        'admin_notes' => 'Test payment',
    ];

    $this->authorized_user([App\Enums\PermissionEnum::ORDER_CREATE]);
    $response = $this->postJson(route('api.v1.admin.payment.store', ['order' => $order->id]), $data);
    $response->assertCreated()
        ->assertJsonStructure([
            'data' => [
                'id',
                'order_id',
                'customer_id',
                'staff_id',
                'amount',
                'method',
                'status',
                'admin_notes',
            ],
        ]);
    $order->refresh();

    $this->assertDatabaseHas('payments', [
        'order_id'             => $order->id,
        'customer_id'          => $this->customer->id,
        'staff_id'             => $this->user->id,
        'amount'               => 500,
        'method'               => PaymentMethodEnum::ONLINE_GATEWAY->value,
        'status'               => PaymentStatusEnum::COMPLETED->value,
        'data->transaction_id' => '123456789',
        'admin_notes'          => 'Test payment',
    ]);

    $this->assertEquals($order->total_paid, 500);
    $this->assertEquals($order->balance_due, 2500);
    $this->assertEquals($order->payment_status, App\Enums\OrderPaymentStatusEnum::PARTIALLY_PAID->value);
});
it('prevent creating payment if amount to pay is 0', function () {
    $product = App\Models\ProductDeliveryOption::factory()
        ->create([
            'price'                   => 1000,
            'is_prepayment_available' => false,
        ]);
    $order = App\Models\Order::factory()->create([
        'customer_id'      => $this->customer->id,
        'total_item_count' => 2,
        'subtotal'         => $product->price,
        'grand_total'      => 0, // Total is 0 due to discount
        'discount_amount'  => 1000, // This will make the total 0
        'tax_amount'       => 0,
    ])->fresh();
    App\Models\OrderItem::factory()
        ->create([
            'qty_ordered'                => 1,
            'product_delivery_option_id' => $product->id,
            'total'                      => $product->price,
            'price'                      => $product->price,
            'discount_amount'            => 1000,
            'order_id'                   => $order->id,
            'payment_type'               => App\Enums\OrderItemPaymentTypeEnum::FULL_PAYMENT->value,
            'tax_amount'                 => 0,
        ]);
    $data = [
        'method'      => PaymentMethodEnum::ONLINE_GATEWAY,
        'status'      => PaymentStatusEnum::COMPLETED,
        'admin_notes' => 'Test payment',
    ];

    $this->authorized_user([App\Enums\PermissionEnum::ORDER_CREATE]);
    $response = $this->postJson(route('api.v1.admin.payment.store', ['order' => $order->id]), $data);
    $response->assertStatus(400)
        ->assertJsonFragment([
            'message' => __('messages.order.amount_to_pay_is_zero'),
        ]);
    $order->refresh();

    $this->assertDatabaseMissing('payments', [
        'order_id' => $order->id,
    ]);

    $this->assertEquals($order->total_paid, 0);
    $this->assertEquals($order->balance_due, 0);
    $this->assertEquals($order->payment_status, App\Enums\OrderPaymentStatusEnum::PAID->value);
});
it('can update payment data', function () {
    $payment = App\Models\Payment::factory()->create([
        'order_id'    => App\Models\Order::factory()->create(),
        'customer_id' => $this->customer->id,
        'staff_id'    => null,
        'amount'      => 1000,
        'method'      => PaymentMethodEnum::ONLINE_GATEWAY,
        'status'      => PaymentStatusEnum::PENDING,
        'admin_notes' => 'Initial payment',
    ]);

    $data = [
        'status'      => PaymentStatusEnum::COMPLETED,
        'admin_notes' => 'Updated payment',
    ];
    $this->authorized_user([App\Enums\PermissionEnum::ORDER_UPDATE]);
    $response = $this->putJson(route('api.v1.admin.payment.update',
        ['order' => $payment->order_id, 'payment' => $payment->id]), $data);
    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'id',
                'order_id',
                'customer_id',
                'staff_id',
                'amount',
                'method',
                'status',
                'admin_notes',
            ],
        ]);
    $payment->refresh();
    $this->assertDatabaseHas('payments', [
        'id'          => $payment->id,
        'order_id'    => $payment->order_id,
        'customer_id' => $this->customer->id,
        'staff_id'    => null,
        'amount'      => 1000,
        'method'      => PaymentMethodEnum::ONLINE_GATEWAY->value,
        'status'      => PaymentStatusEnum::COMPLETED->value,
        'admin_notes' => 'Updated payment',
    ]);
});

it('can delete payment', function () {
    $product1 = App\Models\ProductDeliveryOption::factory()
        ->create([
            'price'                   => 1000,
            'is_prepayment_available' => false,
        ]);
    $product2 = App\Models\ProductDeliveryOption::factory()
        ->create([
            'price'                   => 2000,
            'is_prepayment_available' => false,
        ]);
    $order = App\Models\Order::factory()->create([
        'customer_id'      => $this->customer->id,
        'total_item_count' => 2,
        'subtotal'         => $product1->price + $product2->price,
        'grand_total'      => $product1->price + $product2->price,
        'discount_amount'  => 0,
        'tax_amount'       => 0,
    ])->fresh();

    App\Models\OrderItem::factory()
        ->create([
            'qty_ordered'                => 1,
            'product_delivery_option_id' => $product1->id,
            'total'                      => $product1->price,
            'price'                      => $product1->price,
            'discount_amount'            => 0,
            'order_id'                   => $order->id,
            'payment_type'               => App\Enums\OrderItemPaymentTypeEnum::FULL_PAYMENT->value,
            'tax_amount'                 => 0,
        ]);
    App\Models\OrderItem::factory()
        ->create([
            'qty_ordered'                => 1,
            'product_delivery_option_id' => $product2->id,
            'total'                      => $product2->price,
            'price'                      => $product2->price,
            'discount_amount'            => 0,
            'order_id'                   => $order->id,
            'payment_type'               => App\Enums\OrderItemPaymentTypeEnum::FULL_PAYMENT->value,
            'tax_amount'                 => 0,
        ]);
    $payment = App\Models\Payment::factory()->create([
        'order_id'    => $order->id,
        'customer_id' => $this->customer->id,
        'staff_id'    => null,
        'amount'      => 3000,
        'method'      => PaymentMethodEnum::ONLINE_GATEWAY,
        'status'      => PaymentStatusEnum::COMPLETED,
        'admin_notes' => 'Initial payment',
    ]);
    $order->refresh();

    $this->assertEquals($order->total_paid, 3000);
    $this->assertEquals($order->balance_due, 0);
    $this->assertEquals($order->payment_status, App\Enums\OrderPaymentStatusEnum::PAID->value);

    $this->authorized_user([App\Enums\PermissionEnum::ORDER_DELETE]);
    $response = $this->deleteJson(route('api.v1.admin.payment.destroy',
        ['order' => $order->id, 'payment' => $payment->id]));
    $response->assertNoContent();
    $this->assertDatabaseMissing('payments', [
        'id'          => $payment->id,
        'order_id'    => $order->id,
        'customer_id' => $this->customer->id,
        'staff_id'    => null,
        'amount'      => 300,
        'method'      => PaymentMethodEnum::ONLINE_GATEWAY->value,
        'status'      => PaymentStatusEnum::COMPLETED->value,
        'admin_notes' => 'Initial payment',
    ]);
    $order->refresh();
    $this->assertEquals($order->total_paid, 0);
    $this->assertEquals($order->balance_due, 3000);
    $this->assertEquals($order->payment_status, App\Enums\OrderPaymentStatusEnum::PENDING->value);
});
