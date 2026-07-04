<?php

declare(strict_types=1);

use App\Enums\Order\OrderItemStatusEnum;
use App\Enums\Order\RefundStatusEnum;
use App\Enums\Payment\PaymentMethodEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Enums\PermissionEnum;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use App\Services\Payment\Digipay\Data\RefundResponse;
use App\Services\Payment\Digipay\DigipayAdminService;
use App\Services\Payment\Digipay\DigipayException;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\postJson;

uses(Tests\Support\Traits\AuthTestTrait::class);

beforeEach(function (): void {
    Notification::fake();
});

// ─── Success Cases ────────────────────────────────────────────────────

it('refunds entire order successfully with bank transfer', function (): void {
    $this->authorized_user([PermissionEnum::REFUND_CREATE]);

    $order = Order::factory()->withCalculatedTotals([
        ['price' => 100000, 'total' => 100000],
        ['price' => 200000, 'total' => 200000],
    ])->create();

    $order->payments()->create([
        'customer_id' => $order->customer_id,
        'method'      => PaymentMethodEnum::BANK_TRANSFER,
        'amount'      => 300000,
        'status'      => PaymentStatusEnum::COMPLETED,
    ]);

    $order->items->each->update(['status' => OrderItemStatusEnum::COMPLETED]);

    $response = postJson("/api/v1/admin/orders/{$order->id}/refund", [
        'deduction_amount'  => 0,
        'deduction_percent' => null,
        'admin_notes'       => 'Full order refund',
        'receiver_name'     => 'John Doe',
        'card_number'       => '1234567812345678',
        'iban'              => 'DE89370400440532013000',
        'skip_gateway'      => false,
    ]);

    $response->assertCreated();
    $response->assertJsonCount(2, 'data');
    $response->assertJsonPath('data.0.status.value', 'completed');
    $response->assertJsonPath('data.1.status.value', 'completed');

    $this->assertDatabaseCount('refunds', 2);
});

it('applies deduction percentage to all items in order', function (): void {
    $this->authorized_user([PermissionEnum::REFUND_CREATE]);

    $order = Order::factory()->withCalculatedTotals([
        ['price' => 100000, 'total' => 100000],
        ['price' => 200000, 'total' => 200000],
    ])->create();

    $order->payments()->create([
        'customer_id' => $order->customer_id,
        'method'      => PaymentMethodEnum::BANK_TRANSFER,
        'amount'      => 300000,
        'status'      => PaymentStatusEnum::COMPLETED,
    ]);

    $order->items->each->update(['status' => OrderItemStatusEnum::COMPLETED]);

    $response = postJson("/api/v1/admin/orders/{$order->id}/refund", [
        'deduction_percent' => 10,
        'admin_notes'       => 'Refund with 10% penalty',
        'receiver_name'     => 'John Doe',
        'card_number'       => '1234567812345678',
        'iban'              => 'DE89370400440532013000',
        'skip_gateway'      => false,
    ]);

    $response->assertCreated();

    // Item 1: 100k - 10k = 90k
    $this->assertDatabaseHas('refunds', [
        'order_item_id'    => $order->items[0]->id,
        'amount'           => 90000,
        'deduction_amount' => 10000,
    ]);

    // Item 2: 200k - 20k = 180k
    $this->assertDatabaseHas('refunds', [
        'order_item_id'    => $order->items[1]->id,
        'amount'           => 180000,
        'deduction_amount' => 20000,
    ]);
});

it('processes Digipay full-order refund with gateway call', function (): void {
    $this->authorized_user([PermissionEnum::REFUND_CREATE]);

    $order = Order::factory()->withCalculatedTotals([
        ['price' => 100000, 'total' => 100000],
        ['price' => 200000, 'total' => 200000],
    ])->create();

    $payment = $order->payments()->create([
        'customer_id' => $order->customer_id,
        'method'      => PaymentMethodEnum::DIGIPAY,
        'amount'      => 300000,
        'status'      => PaymentStatusEnum::COMPLETED,
    ]);

    $payment->transactions()->create([
        'transaction_reference' => 'TXN-FULL-ORDER',
        'initiated_at'          => now(),
        'gateway_response'      => [
            'tracking_code'   => 'DGP-ORIGINAL',
            'payment_gateway' => 0,
        ],
    ]);

    $order->items->each->update(['status' => OrderItemStatusEnum::COMPLETED]);

    $this->mock(DigipayAdminService::class, function ($mock): void {
        $mock->shouldReceive('refund')
            ->once()
            ->with(Mockery::type(Payment::class), 300000)
            ->andReturn(new RefundResponse(statusCode: 0, message: 'OK', trackingCode: 'DGP-FULL-REF-123'));
    });

    $response = postJson("/api/v1/admin/orders/{$order->id}/refund", [
        'deduction_amount' => 0,
        'admin_notes'      => 'Digipay full refund',
        'skip_gateway'     => false,
    ]);

    $response->assertCreated();
    $this->assertDatabaseHas('refunds', [
        'transaction_details->gateway_tracking_code' => 'DGP-FULL-REF-123',
    ]);
});

it('skips gateway when skip_gateway is true', function (): void {
    $this->authorized_user([PermissionEnum::REFUND_CREATE, PermissionEnum::REFUND_SKIP_GATEWAY]);

    $order = Order::factory()->withCalculatedTotals([
        ['price' => 100000, 'total' => 100000],
    ])->create();

    $order->payments()->create([
        'customer_id' => $order->customer_id,
        'method'      => PaymentMethodEnum::DIGIPAY,
        'amount'      => 100000,
        'status'      => PaymentStatusEnum::COMPLETED,
    ]);

    $order->items->each->update(['status' => OrderItemStatusEnum::COMPLETED]);

    $this->mock(DigipayAdminService::class, function ($mock): void {
        $mock->shouldNotReceive('refund');
    });

    $response = postJson("/api/v1/admin/orders/{$order->id}/refund", [
        'deduction_amount' => 0,
        'admin_notes'      => 'Manual refund',
        'receiver_name'    => 'John Doe',
        'card_number'      => '1234567812345678',
        'iban'             => 'DE89370400440532013000',
        'skip_gateway'     => true,
    ]);

    $response->assertCreated();
    $refund = Refund::first();
    expect($refund->admin_notes)->toContain('[Gateway skipped by Admin');
});

// ─── Validation Cases ─────────────────────────────────────────────────

it('returns 422 when no refundable items exist', function (): void {
    $this->authorized_user([PermissionEnum::REFUND_CREATE]);

    $order = Order::factory()->withCalculatedTotals([
        ['price' => 100000, 'total' => 100000],
    ])->create();

    // All items already refunded
    $order->items->each->update(['status' => OrderItemStatusEnum::REFUNDED]);

    $response = postJson("/api/v1/admin/orders/{$order->id}/refund", [
        'deduction_amount' => 0,
        'admin_notes'      => 'Test',
    ]);

    $response->assertStatus(422);
    $response->assertJsonFragment(['message' => __('messages.order.refund.no_refundable_items')]);
});

it('returns 422 when cumulative cap is exceeded', function (): void {
    $this->authorized_user([PermissionEnum::REFUND_CREATE]);

    $order = Order::factory()->withCalculatedTotals([
        ['price' => 200000, 'total' => 200000],
    ])->create();

    $payment = $order->payments()->create([
        'customer_id' => $order->customer_id,
        'method'      => PaymentMethodEnum::DIGIPAY,
        'amount'      => 300000,
        'status'      => PaymentStatusEnum::COMPLETED,
    ]);

    // Already refunded 250k
    Refund::factory()->create([
        'payment_id' => $payment->id,
        'amount'     => 250000,
        'status'     => RefundStatusEnum::COMPLETED,
    ]);

    $order->items->each->update(['status' => OrderItemStatusEnum::COMPLETED]);

    $response = postJson("/api/v1/admin/orders/{$order->id}/refund", [
        'deduction_amount' => 0,
        'admin_notes'      => 'Test',
        'skip_gateway'     => false,
    ]);

    // 250k + 200k > 300k
    $response->assertStatus(422);
    $response->assertJsonFragment(['message' => __('validation.custom.refund.exceeds_payment_amount')]);
});

it('returns 422 when deduction_amount and deduction_percent conflict', function (): void {
    $this->authorized_user([PermissionEnum::REFUND_CREATE]);

    $order = Order::factory()->withCalculatedTotals([
        ['price' => 100000, 'total' => 100000],
    ])->create();

    $order->payments()->create([
        'customer_id' => $order->customer_id,
        'method'      => PaymentMethodEnum::BANK_TRANSFER,
        'amount'      => 100000,
        'status'      => PaymentStatusEnum::COMPLETED,
    ]);

    $order->items->each->update(['status' => OrderItemStatusEnum::COMPLETED]);

    $response = postJson("/api/v1/admin/orders/{$order->id}/refund", [
        'deduction_amount'  => 15000,
        'deduction_percent' => 10, // 10% of 100k = 10k, not 15k
        'admin_notes'       => 'Test',
    ]);

    $response->assertStatus(422);
    $response->assertJsonFragment(['message' => __('messages.order.refund.deduction_conflict')]);
});

it('returns 422 when Digipay gateway throws exception', function (): void {
    $this->authorized_user([PermissionEnum::REFUND_CREATE]);

    $order = Order::factory()->withCalculatedTotals([
        ['price' => 100000, 'total' => 100000],
    ])->create();

    $payment = $order->payments()->create([
        'customer_id' => $order->customer_id,
        'method'      => PaymentMethodEnum::DIGIPAY,
        'amount'      => 100000,
        'status'      => PaymentStatusEnum::COMPLETED,
    ]);

    $payment->transactions()->create([
        'transaction_reference' => 'TXN-FAIL',
        'initiated_at'          => now(),
        'gateway_response'      => ['tracking_code' => 'DGP-FAIL', 'payment_gateway' => 0],
    ]);

    $order->items->each->update(['status' => OrderItemStatusEnum::COMPLETED]);

    $this->mock(DigipayAdminService::class, function ($mock): void {
        $mock->shouldReceive('refund')
            ->andThrow(new DigipayException('Gateway timeout', 500));
    });

    $response = postJson("/api/v1/admin/orders/{$order->id}/refund", [
        'deduction_amount' => 0,
        'admin_notes'      => 'Test',
        'skip_gateway'     => false,
    ]);

    $response->assertStatus(422);

    // Refund should be marked as FAILED in DB
    $this->assertDatabaseHas('refunds', [
        'order_item_id' => $order->items[0]->id,
        'status'        => RefundStatusEnum::FAILED->value,
    ]);
});

// ─── Authorization ────────────────────────────────────────────────────

it('refuses order refund without REFUND_CREATE permission', function (): void {
    $this->authorized_user([PermissionEnum::REFUND_VIEW]);

    $order = Order::factory()->create();

    $response = postJson("/api/v1/admin/orders/{$order->id}/refund", [
        'deduction_amount' => 0,
        'admin_notes'      => 'Test',
    ]);

    $response->assertForbidden();
});

it('refuses skip_gateway without REFUND_SKIP_GATEWAY permission', function (): void {
    $this->authorized_user([PermissionEnum::REFUND_CREATE]);

    $order = Order::factory()->withCalculatedTotals([
        ['price' => 100000, 'total' => 100000],
    ])->create();

    $response = postJson("/api/v1/admin/orders/{$order->id}/refund", [
        'deduction_amount' => 0,
        'admin_notes'      => 'Test',
        'skip_gateway'     => true,
    ]);

    $response->assertForbidden();
});

it('rejects order refund for unauthenticated user', function (): void {
    $order = Order::factory()->create();

    $response = postJson("/api/v1/admin/orders/{$order->id}/refund", [
        'deduction_amount' => 0,
        'admin_notes'      => 'Test',
    ]);

    $response->assertUnauthorized();
});
