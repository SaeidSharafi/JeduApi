<?php

declare(strict_types=1);

use App\Enums\EnrollmentStatusEnum;
use App\Enums\Order\OrderCancellationReasonEnum;
use App\Enums\Order\OrderStatusEnum;
use App\Enums\Payment\PaymentMethodEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Enums\Payment\PaymentTransactionErrorCodeEnum;
use App\Enums\Payment\PaymentTransactionStatusEnum;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentTransaction;
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

    $response = postJson(route('api.v1.shop.student.orders.cancel', $order->increment_id));

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
    expect($order->status)->toBe(OrderStatusEnum::CANCELLED)
        ->and($order->cancelled_at)->not->toBeNull()
        ->and($order->cancelled_reason)->toBe(OrderCancellationReasonEnum::CUSTOMER);
});

it('closes pending payment attempts when the customer cancels', function (): void {
    $order = Order::factory()->create([
        'customer_id' => $this->user->id,
        'status'      => OrderStatusEnum::PENDING,
    ]);

    $payment = Payment::factory()->create([
        'order_id'    => $order->id,
        'customer_id' => $this->user->id,
        'method'      => PaymentMethodEnum::MELLAT_GATEWAY,
        'status'      => PaymentStatusEnum::PENDING,
    ]);

    $transaction = PaymentTransaction::factory()->create([
        'payment_id'            => $payment->id,
        'status'                => PaymentTransactionStatusEnum::INITIATED,
        'transaction_reference' => '200000011',
        'initiated_at'          => now()->subMinutes(5),
        'completed_at'          => null,
    ]);

    postJson(route('api.v1.shop.student.orders.cancel', $order->increment_id))
        ->assertOk();

    $transaction->refresh();
    expect($payment->fresh()->status)->toBe(PaymentStatusEnum::FAILED)
        ->and($transaction->status)->toBe(PaymentTransactionStatusEnum::FAILED)
        ->and($transaction->error_code)->toBe(PaymentTransactionErrorCodeEnum::ORDER_CLOSED->value)
        ->and($transaction->completed_at)->not->toBeNull();
});

it('cancels enrollments when order is cancelled', function (): void {
    $order = Order::factory()->create([
        'customer_id' => $this->user->id,
        'status'      => OrderStatusEnum::PENDING,
    ]);

    $orderItem = App\Models\OrderItem::factory()->for($order)->withEnrollment()->create([
        'payment_type' => App\Enums\Order\OrderItemPaymentTypeEnum::FULL_PAYMENT,
    ]);
    $enrollment = $orderItem->enrollment;

    $response = postJson(route('api.v1.shop.student.orders.cancel', $order->increment_id));
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

    $response = postJson(route('api.v1.shop.student.orders.cancel', $order->increment_id));

    $response->assertStatus(422);
    $response->assertJsonFragment([
        'message' => __('messages.order.cannot_cancel_order_with_completed_payments', ['order_id' => $order->id]),
    ]);

    $order->refresh();
    expect($order->status)->not->toBe(OrderStatusEnum::CANCELLED);
});

it('prevents cancellation of non-pending orders', function (): void {
    $order = Order::factory()->create([
        'customer_id' => $this->user->id,
        'status'      => OrderStatusEnum::COMPLETED,
    ]);

    $response = postJson(route('api.v1.shop.student.orders.cancel', $order->increment_id));

    $response->assertStatus(422);
});

it('prevents customer from cancelling another users order', function (): void {
    $otherUser = User::factory()->create();
    $order     = Order::factory()->create([
        'customer_id' => $otherUser->id,
        'status'      => OrderStatusEnum::PENDING,
    ]);

    $response = postJson(route('api.v1.shop.student.orders.cancel', $order->increment_id));

    $response->assertNotFound();
});

it('requires authentication', function (): void {
    $order = Order::factory()->create([
        'status' => OrderStatusEnum::PENDING,
    ]);

    $response = $this->postJson(route('api.v1.shop.student.orders.cancel', $order->increment_id));

    $response->assertNotFound(); // unauthenticated leads to model not found due to ownership check
});
