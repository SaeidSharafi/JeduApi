<?php

declare(strict_types=1);

use App\Actions\Admin\Refund\RefundOrderAction;
use App\Data\Admin\Refund\RefundOrderData;
use App\Enums\Content\PublicationStatusEnum;
use App\Enums\Order\OrderItemStatusEnum;
use App\Enums\Order\RefundStatusEnum;
use App\Enums\Payment\PaymentMethodEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Events\RefundCompletedEvent;
use App\Exceptions\RefundGatewayException;
use App\Exceptions\RefundValidationException;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ProductDeliveryOption;
use App\Models\Refund;
use App\Services\OrderStatusService;
use App\Services\Payment\Digipay\Data\RefundResponse;
use App\Services\Payment\Digipay\DigipayAdminService;
use App\Services\Payment\Digipay\DigipayException;
use Illuminate\Support\Facades\Event;
use Mockery\MockInterface;

beforeEach(function (): void {
    Event::fake([RefundCompletedEvent::class]);

    $this->mock(OrderStatusService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('updateEnrollmentStatus')->zeroOrMoreTimes();
        $mock->shouldReceive('updateParentOrderStatus')->zeroOrMoreTimes();
    });
});

// ─── Success Cases ────────────────────────────────────────────────────

it('refunds all refundable items in an order with bank transfer', function (): void {
    // Arrange
    $product1 = ProductDeliveryOption::factory()->create([
        'price'  => 100000,
        'status' => PublicationStatusEnum::PUBLISHED,
    ]);
    $product2 = ProductDeliveryOption::factory()->create([
        'price'  => 200000,
        'status' => PublicationStatusEnum::PUBLISHED,
    ]);

    $order = Order::factory()->withCalculatedTotals([
        ['product_delivery_option_id' => $product1->id, 'price' => 100000, 'total' => 100000],
        ['product_delivery_option_id' => $product2->id, 'price' => 200000, 'total' => 200000],
    ])->create();

    $order->payments()->create([
        'customer_id' => $order->customer_id,
        'method'      => PaymentMethodEnum::BANK_TRANSFER,
        'amount'      => 300000,
        'status'      => PaymentStatusEnum::COMPLETED,
    ]);

    $order->items->each->update(['status' => OrderItemStatusEnum::COMPLETED]);

    $data = new RefundOrderData(
        deduction_amount: 0,
        deduction_percent: null,
        skip_gateway: false,
        admin_notes: 'Full order refund',
        receiver_name: 'John Doe',
        card_number: '1234567812345678',
        iban: 'DE89370400440532013000',
    );

    // Act
    $refunds = (resolve(RefundOrderAction::class))->handle($order, $data);

    // Assert
    expect($refunds)->toHaveCount(2);

    $sortedItems = $order->items->sortBy('price')->values();

    $this->assertDatabaseHas('refunds', [
        'order_item_id' => $sortedItems[0]->id,
        'amount'        => 100000,
        'status'        => RefundStatusEnum::COMPLETED->value,
    ]);

    $this->assertDatabaseHas('refunds', [
        'order_item_id' => $sortedItems[1]->id,
        'amount'        => 200000,
        'status'        => RefundStatusEnum::COMPLETED->value,
    ]);

    $this->assertDatabaseHas('order_items', [
        'id'             => $sortedItems[0]->id,
        'status'         => OrderItemStatusEnum::REFUNDED->value,
        'total_refunded' => 100000,
    ]);

    Event::assertDispatched(RefundCompletedEvent::class, 2);
});

it('applies deduction percentage to all items', function (): void {
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

    $data = new RefundOrderData(
        deduction_amount: null,
        deduction_percent: 10, // 10% of each item's price
        skip_gateway: false,
        admin_notes: 'Refund with 10% penalty',
        receiver_name: 'John Doe',
        card_number: '1234567812345678',
        iban: 'DE89370400440532013000',
    );

    $refunds = (resolve(RefundOrderAction::class))->handle($order, $data);

    $sortedItems = $order->items->sortBy('price')->values();

    // Item 1: 100k - 10k = 90k
    $this->assertDatabaseHas('refunds', [
        'order_item_id'    => $sortedItems[0]->id,
        'amount'           => 90000,
        'deduction_amount' => 10000,
    ]);

    // Item 2: 200k - 20k = 180k
    $this->assertDatabaseHas('refunds', [
        'order_item_id'    => $sortedItems[1]->id,
        'amount'           => 180000,
        'deduction_amount' => 20000,
    ]);
});

it('processes full-order Digipay refund with gateway call', function (): void {
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
        'transaction_reference' => 'TXN-FULL-REFUND',
        'initiated_at'          => now(),
        'gateway_response'      => [
            'tracking_code'   => 'DGP-ORIGINAL',
            'payment_gateway' => 0,
        ],
    ]);

    $order->items->each->update(['status' => OrderItemStatusEnum::COMPLETED]);

    $this->mock(DigipayAdminService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('refund')
            ->once()
            ->with(\Mockery::type(Payment::class), 300000)
            ->andReturn(new RefundResponse(statusCode: 0, message: 'OK', trackingCode: 'DGP-FULL-REF'));
    });

    $data = new RefundOrderData(
        deduction_amount: 0,
        deduction_percent: null,
        skip_gateway: false,
        admin_notes: 'Full Digipay refund',
        receiver_name: null,
        card_number: null,
        iban: null,
    );

    $refunds = (resolve(RefundOrderAction::class))->handle($order, $data);

    expect($refunds)->toHaveCount(2);
    $this->assertDatabaseHas('refunds', [
        'transaction_details->gateway_tracking_code' => 'DGP-FULL-REF',
    ]);
});

it('skips gateway when skip_gateway is true', function (): void {
    $order = Order::factory()->withCalculatedTotals([
        ['price' => 100000, 'total' => 100000],
    ])->create();

    $payment = $order->payments()->create([
        'customer_id' => $order->customer_id,
        'method'      => PaymentMethodEnum::DIGIPAY,
        'amount'      => 100000,
        'status'      => PaymentStatusEnum::COMPLETED,
    ]);

    $order->items->each->update(['status' => OrderItemStatusEnum::COMPLETED]);

    // Mock should NOT be called
    $this->mock(DigipayAdminService::class, function (MockInterface $mock): void {
        $mock->shouldNotReceive('refund');
    });

    $data = new RefundOrderData(
        deduction_amount: 0,
        deduction_percent: null,
        skip_gateway: true,
        admin_notes: 'Manual refund',
        receiver_name: 'John Doe',
        card_number: '1234567812345678',
        iban: 'DE89370400440532013000',
    );

    $refunds = (resolve(RefundOrderAction::class))->handle($order, $data);

    $refund = $refunds->first();
    expect($refund->admin_notes)->toContain('[Gateway skipped by Admin');
});

// ─── Validation Cases ─────────────────────────────────────────────────

it('throws exception when no refundable items exist', function (): void {
    $order = Order::factory()->withCalculatedTotals([
        ['price' => 100000, 'total' => 100000],
    ])->create();

    // Mark all items as already refunded
    $order->items->each->update(['status' => OrderItemStatusEnum::REFUNDED]);

    $data = new RefundOrderData(
        deduction_amount: 0,
        deduction_percent: null,
        skip_gateway: false,
        admin_notes: 'Test',
        receiver_name: null,
        card_number: null,
        iban: null,
    );

    expect(fn () => (resolve(RefundOrderAction::class))->handle($order, $data))
        ->toThrow(RefundValidationException::class, 'no_refundable_items');
});

it('excludes cancelled items from refund', function (): void {
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

    $sortedItems = $order->items->sortBy('price')->values();

    $sortedItems[0]->update(['status' => OrderItemStatusEnum::COMPLETED]);
    $sortedItems[1]->update(['status' => OrderItemStatusEnum::CANCELLED]); // Excluded

    $data = new RefundOrderData(
        deduction_amount: 0,
        deduction_percent: null,
        skip_gateway: false,
        admin_notes: 'Partial refund',
        receiver_name: 'John Doe',
        card_number: '1234567812345678',
        iban: 'DE89370400440532013000',
    );

    $refunds = (resolve(RefundOrderAction::class))->handle($order, $data);

    expect($refunds)->toHaveCount(1)
        ->and($refunds->first()->order_item_id)->toBe($sortedItems[0]->id);
});

it('excludes items with existing non-failed refunds', function (): void {
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

    $sortedItems = $order->items->sortBy('price')->values();

    // Item 1 (100k) has a pending refund
    Refund::factory()->create([
        'order_item_id' => $sortedItems[0]->id,
        'status'        => RefundStatusEnum::PENDING,
    ]);

    $data = new RefundOrderData(
        deduction_amount: 0,
        deduction_percent: null,
        skip_gateway: false,
        admin_notes: 'Test',
        receiver_name: 'John Doe',
        card_number: '1234567812345678',
        iban: 'DE89370400440532013000',
    );

    $refunds = (resolve(RefundOrderAction::class))->handle($order, $data);

    // Only item 2 (200k) is refunded
    expect($refunds)->toHaveCount(1)
        ->and($refunds->first()->order_item_id)->toBe($sortedItems[1]->id);
});

// ─── Digipay Cumulative Cap ──────────────────────────────────────────

it('validates cumulative cap before gateway call for Digipay', function (): void {
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

    $data = new RefundOrderData(
        deduction_amount: 0,
        deduction_percent: null,
        skip_gateway: false,
        admin_notes: 'Test',
        receiver_name: null,
        card_number: null,
        iban: null,
    );

    // 250k + 200k > 300k
    expect(fn () => (resolve(RefundOrderAction::class))->handle($order, $data))
        ->toThrow(RefundGatewayException::class, 'exceeds original payment');
});

// ─── Error Handling ───────────────────────────────────────────────────

it('marks refunds as FAILED when Digipay gateway throws exception', function (): void {
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

    $this->mock(DigipayAdminService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('refund')
            ->with(\Mockery::type(Payment::class), \Mockery::type('int'))
            ->andThrow(new DigipayException('Gateway error', 500));
    });

    $data = new RefundOrderData(
        deduction_amount: 0,
        deduction_percent: null,
        skip_gateway: false,
        admin_notes: 'Test',
        receiver_name: null,
        card_number: null,
        iban: null,
    );

    try {
        (resolve(RefundOrderAction::class))->handle($order, $data);
    } catch (RefundValidationException $e) {
        // Expected
    }

    // Refund should be marked as FAILED
    $this->assertDatabaseHas('refunds', [
        'order_item_id' => $order->items[0]->id,
        'status'        => RefundStatusEnum::FAILED->value,
    ]);
});

it('validates deduction_amount and deduction_percent conflict', function (): void {
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

    // 10% of 100k = 10k, but we pass 15k as amount
    $data = new RefundOrderData(
        deduction_amount: 15000,
        deduction_percent: 10,
        skip_gateway: false,
        admin_notes: 'Test',
        receiver_name: 'John Doe',
        card_number: '1234567812345678',
        iban: 'DE89370400440532013000',
    );

    expect(fn () => (resolve(RefundOrderAction::class))->handle($order, $data))
        ->toThrow(RefundValidationException::class, 'deduction amount and percentage conflict');
});
