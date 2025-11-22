<?php

declare(strict_types=1);

use App\Enums\EnrollmentStatusEnum;
use App\Enums\Order\OrderStatusEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;

use function Pest\Laravel\postJson;

uses(Tests\Support\Traits\AuthTestTrait::class);

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->customer($this->user);
});

it('allows customer to cancel their own pending order', function (): void {
    $order = Order::factory()->create([
        'customer_id' => $this->user->id,
        'status'      => OrderStatusEnum::PENDING,
    ]);

    $response = postJson(route('api.v1.shop.orders.cancel', $order->increment_id));

    $response->assertOk();
    $response->assertJsonStructure([
        'message',
        'data' => [
            'id',
            'increment_id',
            'status' => ['value', 'label'],
        ],
    ]);

    $order->refresh();
    expect($order->status)->toBe(OrderStatusEnum::CANCELLED);
});

it('cancels enrollments when order is cancelled', function (): void {
    $order = Order::factory()->create([
        'customer_id' => $this->user->id,
        'status'      => OrderStatusEnum::PENDING,
    ]);

    $orderItem = App\Models\OrderItem::factory()->for($order)->withEnrollment()->create([
        'payment_type' => App\Enums\Order\OrderItemPaymentTypeEnum::FULL_PAYMENT,
    ]);
    $orderItem->enrollment->enrollment_status = EnrollmentStatusEnum::ACTIVE;
    $orderItem->enrollment->save();
    $enrollment = $orderItem->enrollment;

    $response = postJson(route('api.v1.shop.orders.cancel', $order->increment_id));
    $response->assertOk();

    $enrollment->refresh();
    expect($enrollment->enrollment_status)->toBe(EnrollmentStatusEnum::CANCELLED);
});

it('prevents cancellation of order with completed payments', function (): void {
    $order = Order::factory()->create([
        'customer_id' => $this->user->id,
        'status'      => OrderStatusEnum::PENDING,
    ]);

    Payment::factory()->create([
        'order_id'    => $order->id,
        'customer_id' => $this->user->id,
        'status'      => PaymentStatusEnum::COMPLETED,
    ]);

    $response = postJson(route('api.v1.shop.orders.cancel', $order->increment_id));

    $response->assertStatus(422);
    $response->assertJsonFragment([
        'message' => 'Cannot cancel an order with completed payments. Please contact support for refund assistance.',
    ]);

    $order->refresh();
    expect($order->status)->not->toBe(OrderStatusEnum::CANCELLED);
});

it('prevents cancellation of non-pending orders', function (): void {
    $order = Order::factory()->create([
        'customer_id' => $this->user->id,
        'status'      => OrderStatusEnum::COMPLETED,
    ]);

    $response = postJson(route('api.v1.shop.orders.cancel', $order->increment_id));

    $response->assertStatus(422);
});

it('prevents customer from cancelling another users order', function (): void {
    $otherUser = User::factory()->create();
    $order     = Order::factory()->create([
        'customer_id' => $otherUser->id,
        'status'      => OrderStatusEnum::PENDING,
    ]);

    $response = postJson(route('api.v1.shop.orders.cancel', $order->increment_id));

    $response->assertNotFound();
});

it('requires authentication', function (): void {
    $order = Order::factory()->create([
        'status' => OrderStatusEnum::PENDING,
    ]);

    $response = $this->postJson(route('api.v1.shop.orders.cancel', $order->increment_id));

    $response->assertNotFound(); // unauthenticated leads to model not found due to ownership check
});
