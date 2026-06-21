<?php

declare(strict_types=1);

use App\Enums\Order\OrderStatusEnum;
use App\Enums\Payment\PaymentMethodEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Enums\PermissionEnum;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\User;

use function Pest\Laravel\postJson;

uses(Tests\Support\Traits\AuthTestTrait::class);

beforeEach(function (): void {
    $this->customer = User::factory()->create();
});

it('approves an order with full payment successfully', function (): void {
    $this->authorized_user([PermissionEnum::ORDER_APPROVE]);

    $order = Order::factory()->create([
        'customer_id'            => $this->customer->id,
        'status'                 => OrderStatusEnum::PENDING,
        'grand_total'            => 1000000,
        'full_value_grand_total' => 1000000,
    ]);

    OrderItem::factory()->create([
        'order_id' => $order->id,
        'price'    => 1000000,
        'total'    => 1000000,
        'status'   => App\Enums\Order\OrderItemStatusEnum::PENDING,
    ]);

    Payment::factory()->create([
        'order_id'    => $order->id,
        'customer_id' => $this->customer->id,
        'method'      => PaymentMethodEnum::MELLAT_GATEWAY,
        'amount'      => 1000000,
        'status'      => PaymentStatusEnum::COMPLETED,
    ]);

    $response = postJson("/api/v1/admin/order/{$order->id}/approve");

    $response->assertOk();
    $order->refresh();
    expect($order->status)->toBe(OrderStatusEnum::COMPLETED);
});
it('fails to approve order with insufficient payment', function (): void {
    $this->authorized_user([PermissionEnum::ORDER_APPROVE]);

    $order = Order::factory()->create([
        'customer_id'            => $this->customer->id,
        'status'                 => OrderStatusEnum::PENDING,
        'grand_total'            => 1000000,
        'full_value_grand_total' => 1000000,
    ]);

    OrderItem::factory()->create([
        'order_id' => $order->id,
        'price'    => 1000000,
        'total'    => 1000000,
        'status'   => App\Enums\Order\OrderItemStatusEnum::PENDING,
    ]);

    Payment::factory()->create([
        'order_id'    => $order->id,
        'customer_id' => $this->customer->id,
        'method'      => PaymentMethodEnum::MELLAT_GATEWAY,
        'amount'      => 500000,
        'status'      => PaymentStatusEnum::COMPLETED,
    ]);

    $response = postJson("/api/v1/admin/order/{$order->id}/approve");

    $response->assertStatus(422);
});

it('approves an order with pre_payment items when prepayment amount is paid', function (): void {
    $this->authorized_user([PermissionEnum::ORDER_APPROVE]);

    $order = Order::factory()->create([
        'customer_id'            => $this->customer->id,
        'status'                 => OrderStatusEnum::PENDING,
        'grand_total'            => 1000000,
        'full_value_grand_total' => 1000000,
    ]);

    OrderItem::factory()->create([
        'order_id'          => $order->id,
        'price'             => 1000000,
        'total'             => 1000000,
        'payment_type'      => 'pre_payment',
        'prepayment_amount' => 300000,
        'status'            => App\Enums\Order\OrderItemStatusEnum::PENDING,
    ]);

    Payment::factory()->create([
        'order_id'    => $order->id,
        'customer_id' => $this->customer->id,
        'method'      => PaymentMethodEnum::MELLAT_GATEWAY,
        'amount'      => 300000,
        'status'      => PaymentStatusEnum::COMPLETED,
    ]);

    $response = postJson("/api/v1/admin/order/{$order->id}/approve");

    $response->assertOk();
    $order->refresh();
    expect($order->status)->toBe(OrderStatusEnum::COMPLETED);
});

it('fails to approve an already completed order', function (): void {
    $this->authorized_user([PermissionEnum::ORDER_APPROVE]);

    $order = Order::factory()->create([
        'customer_id' => $this->customer->id,
        'status'      => OrderStatusEnum::COMPLETED,
    ]);

    $response = postJson("/api/v1/admin/order/{$order->id}/approve");

    $response->assertStatus(422);
});

it('fails to approve a cancelled order', function (): void {
    $this->authorized_user([PermissionEnum::ORDER_APPROVE]);

    $order = Order::factory()->create([
        'customer_id' => $this->customer->id,
        'status'      => OrderStatusEnum::CANCELLED,
    ]);

    $response = postJson("/api/v1/admin/order/{$order->id}/approve");

    $response->assertStatus(422);
});

it('requires ORDER_APPROVE permission', function (): void {
    $this->authorized_user([PermissionEnum::ORDER_VIEW]);

    $order = Order::factory()->create([
        'customer_id' => $this->customer->id,
        'status'      => OrderStatusEnum::PENDING,
    ]);

    $response = postJson("/api/v1/admin/order/{$order->id}/approve");

    $response->assertForbidden();
});

it('requires authentication', function (): void {
    $order = Order::factory()->create();

    $response = postJson("/api/v1/admin/order/{$order->id}/approve");

    $response->assertUnauthorized();
});
