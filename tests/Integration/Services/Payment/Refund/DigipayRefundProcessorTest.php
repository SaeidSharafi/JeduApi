<?php

declare(strict_types=1);

use App\Enums\Order\RefundStatusEnum;
use App\Enums\Payment\PaymentMethodEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Exceptions\Gateway\DigipayException;
use App\Exceptions\RefundGatewayException;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use App\Services\Payment\Digipay\Data\RefundResponse;
use App\Services\Payment\Digipay\DigipayAdminService;
use App\Services\Payment\Refund\DigipayRefundProcessor;
use SmartCache\Facades\SmartCache;

// ─── Success Cases ────────────────────────────────────────────────────

it('processes a Digipay refund successfully', function (): void {
    // Arrange
    $order   = Order::factory()->withCalculatedTotals([['total' => 500000]])->create();
    $payment = Payment::factory()->for($order)->create([
        'method'      => PaymentMethodEnum::DIGIPAY,
        'amount'      => 500000,
        'status'      => PaymentStatusEnum::COMPLETED,
        'customer_id' => $order->customer_id,
    ]);

    $payment->transactions()->create([
        'transaction_reference' => 'TXN-'.fake()->uuid(),
        'initiated_at'          => now(),
        'gateway_response'      => [
            'tracking_code'   => 'DGP-ORIGINAL',
            'payment_gateway' => 0,
        ],
    ]);

    $refund = Refund::factory()->create([
        'order_id'      => $order->id,
        'payment_id'    => $payment->id,
        'order_item_id' => $order->items->first()->id,
        'amount'        => 100000,
    ]);

    $this->mock(DigipayAdminService::class, function ($mock) use ($payment): void {
        $mock->shouldReceive('refund')
            ->once()
            ->with(Mockery::on(fn ($p): bool => $p->id === $payment->id), 100000)
            ->andReturn(new RefundResponse(
                statusCode: 0,
                message: 'Refund successful',
                trackingCode: 'DGP-REF-123',
            ));
    });

    // Act
    $processor    = resolve(DigipayRefundProcessor::class);
    $trackingCode = $processor->process($refund, $order, 100000);

    // Assert
    expect($trackingCode)->toBe('DGP-REF-123');
});

it('uses refund.payment_id if populated', function (): void {
    $order = Order::factory()->withCalculatedTotals([['total' => 300000]])->create();

    // Create two payments - one older, one newer
    $olderPayment = Payment::factory()->for($order)->create([
        'method'      => PaymentMethodEnum::DIGIPAY,
        'amount'      => 150000,
        'status'      => PaymentStatusEnum::COMPLETED,
        'customer_id' => $order->customer_id,
        'created_at'  => now()->subDays(2),
    ]);

    $newerPayment = Payment::factory()->for($order)->create([
        'method'      => PaymentMethodEnum::DIGIPAY,
        'amount'      => 150000,
        'status'      => PaymentStatusEnum::COMPLETED,
        'customer_id' => $order->customer_id,
        'created_at'  => now()->subDay(),
    ]);

    $olderPayment->transactions()->create([
        'transaction_reference' => 'TXN-OLD',
        'initiated_at'          => now(),
        'gateway_response'      => ['tracking_code' => 'DGP-OLD', 'payment_gateway' => 0],
    ]);

    $newerPayment->transactions()->create([
        'transaction_reference' => 'TXN-NEW',
        'initiated_at'          => now(),
        'gateway_response'      => ['tracking_code' => 'DGP-NEW', 'payment_gateway' => 0],
    ]);

    // Refund explicitly targets newer payment
    $refund = Refund::factory()->create([
        'order_id'      => $order->id,
        'payment_id'    => $newerPayment->id,
        'order_item_id' => $order->items->first()->id,
        'amount'        => 50000,
    ]);

    $this->mock(DigipayAdminService::class, function ($mock): void {
        $mock->shouldReceive('refund')
            ->once()
            ->andReturn(new RefundResponse(
                statusCode: 0,
                message: 'OK',
                trackingCode: 'DGP-REF-456',
            ));
    });

    $processor    = resolve(DigipayRefundProcessor::class);
    $trackingCode = $processor->process($refund, $order, 50000);

    expect($trackingCode)->toBe('DGP-REF-456');
});

it('falls back to oldest completed payment when refund.payment_id is null', function (): void {
    $order = Order::factory()->withCalculatedTotals([['total' => 100000]])->create();

    $olderPayment = Payment::factory()->for($order)->create([
        'method'      => PaymentMethodEnum::DIGIPAY,
        'amount'      => 50000,
        'status'      => PaymentStatusEnum::COMPLETED,
        'customer_id' => $order->customer_id,
        'created_at'  => now()->subDays(2),
    ]);

    $newerPayment = Payment::factory()->for($order)->create([
        'method'      => PaymentMethodEnum::DIGIPAY,
        'amount'      => 50000,
        'status'      => PaymentStatusEnum::COMPLETED,
        'customer_id' => $order->customer_id,
        'created_at'  => now()->subDay(),
    ]);

    $olderPayment->transactions()->create([
        'transaction_reference' => 'TXN-OLD',
        'initiated_at'          => now(),
        'gateway_response'      => ['tracking_code' => 'DGP-OLD', 'payment_gateway' => 0],
    ]);

    $newerPayment->transactions()->create([
        'transaction_reference' => 'TXN-NEW',
        'initiated_at'          => now(),
        'gateway_response'      => ['tracking_code' => 'DGP-NEW', 'payment_gateway' => 0],
    ]);

    $refund = Refund::factory()->create([
        'order_id'      => $order->id,
        'payment_id'    => null,
        'order_item_id' => $order->items->first()->id,
        'amount'        => 50000,
    ]);

    $this->mock(DigipayAdminService::class, function ($mock): void {
        $mock->shouldReceive('refund')
            ->once()
            ->andReturn(new RefundResponse(
                statusCode: 0,
                message: 'OK',
                trackingCode: 'DGP-REF-FALLBACK',
            ));
    });

    $processor    = resolve(DigipayRefundProcessor::class);
    $trackingCode = $processor->process($refund, $order, 50000);

    expect($trackingCode)->toBe('DGP-REF-FALLBACK');
});

// ─── Cumulative Cap Validation ────────────────────────────────────────

it('enforces cumulative refund cap - prevents exceeding payment amount', function (): void {
    $order   = Order::factory()->withCalculatedTotals([['total' => 500000]])->create();
    $payment = Payment::factory()->for($order)->create([
        'method'      => PaymentMethodEnum::DIGIPAY,
        'amount'      => 500000,
        'status'      => PaymentStatusEnum::COMPLETED,
        'customer_id' => $order->customer_id,
    ]);

    $payment->transactions()->create([
        'transaction_reference' => 'TXN-'.fake()->uuid(),
        'initiated_at'          => now(),
        'gateway_response'      => ['tracking_code' => 'DGP-ORIG', 'payment_gateway' => 0],
    ]);

    // Already refunded 400k
    Refund::factory()->create([
        'payment_id' => $payment->id,
        'amount'     => 400000,
        'status'     => RefundStatusEnum::COMPLETED,
    ]);

    $newRefund = Refund::factory()->create([
        'payment_id'    => $payment->id,
        'order_item_id' => $order->items->first()->id,
        'amount'        => 150000, // 400k + 150k > 500k
    ]);

    $processor = resolve(DigipayRefundProcessor::class);

    expect(fn () => $processor->process($newRefund, $order, 150000))
        ->toThrow(RefundGatewayException::class, 'would exceed payment');
});

it('allows refund when cumulative cap is not exceeded', function (): void {
    $order   = Order::factory()->withCalculatedTotals([['total' => 500000]])->create();
    $payment = Payment::factory()->for($order)->create([
        'method'      => PaymentMethodEnum::DIGIPAY,
        'amount'      => 500000,
        'status'      => PaymentStatusEnum::COMPLETED,
        'customer_id' => $order->customer_id,
    ]);

    $payment->transactions()->create([
        'transaction_reference' => 'TXN-'.fake()->uuid(),
        'initiated_at'          => now(),
        'gateway_response'      => ['tracking_code' => 'DGP-ORIG', 'payment_gateway' => 0],
    ]);

    // Already refunded 300k
    Refund::factory()->create([
        'payment_id' => $payment->id,
        'amount'     => 300000,
        'status'     => RefundStatusEnum::COMPLETED,
    ]);

    $newRefund = Refund::factory()->create([
        'order_id'      => $order->id,
        'payment_id'    => $payment->id,
        'order_item_id' => $order->items->first()->id,
        'amount'        => 150000, // 300k + 150k = 450k <= 500k
        'status'        => RefundStatusEnum::PENDING, // Not COMPLETED yet
    ]);

    $this->mock(DigipayAdminService::class, function ($mock): void {
        $mock->shouldReceive('refund')->andReturn(new RefundResponse(
            statusCode: 0,
            message: 'OK',
            trackingCode: 'DGP-REF-OK',
        ));
    });

    $processor    = resolve(DigipayRefundProcessor::class);
    $trackingCode = $processor->process($newRefund, $order, 150000);

    expect($trackingCode)->toBe('DGP-REF-OK');
});

it('serializes cumulative cap check with payment-level lock', function (): void {
    $order   = Order::factory()->withCalculatedTotals([['total' => 200000]])->create();
    $payment = Payment::factory()->for($order)->create([
        'method'      => PaymentMethodEnum::DIGIPAY,
        'amount'      => 200000,
        'status'      => PaymentStatusEnum::COMPLETED,
        'customer_id' => $order->customer_id,
    ]);

    $payment->transactions()->create([
        'transaction_reference' => 'TXN-LOCK',
        'initiated_at'          => now(),
        'gateway_response'      => ['tracking_code' => 'DGP-LOCK', 'payment_gateway' => 0],
    ]);

    $refund = Refund::factory()->create([
        'order_id'      => $order->id,
        'payment_id'    => $payment->id,
        'order_item_id' => $order->items->first()->id,
        'amount'        => 50000,
    ]);

    $lockMock = Mockery::mock(Illuminate\Contracts\Cache\Lock::class);
    $lockMock->shouldReceive('block')
        ->once()
        ->with(5, Mockery::type(Closure::class))
        ->andReturnUsing(fn (int $_timeout, Closure $callback) => $callback());

    SmartCache::shouldReceive('lock')
        ->once()
        ->with("digipay_refund_payment_{$payment->id}", 15)
        ->andReturn($lockMock);

    $this->mock(DigipayAdminService::class, function ($mock): void {
        $mock->shouldReceive('refund')
            ->once()
            ->andReturn(new RefundResponse(
                statusCode: 0,
                message: 'OK',
                trackingCode: 'DGP-LOCK-OK',
            ));
    });

    $processor    = resolve(DigipayRefundProcessor::class);
    $trackingCode = $processor->process($refund, $order, 50000);

    expect($trackingCode)->toBe('DGP-LOCK-OK');
});

// ─── BNPL/CREDIT Delivery Guard ──────────────────────────────────────

it('processes BNPL refund before delivery confirmation', function (): void {
    $order   = Order::factory()->withCalculatedTotals([['total' => 500000]])->create();
    $payment = Payment::factory()->for($order)->create([
        'method'      => PaymentMethodEnum::DIGIPAY,
        'amount'      => 500000,
        'status'      => PaymentStatusEnum::COMPLETED,
        'customer_id' => $order->customer_id,
    ]);

    $payment->transactions()->create([
        'transaction_reference' => 'TXN-'.fake()->uuid(),
        'initiated_at'          => now(),
        'gateway_response'      => [
            'tracking_code'      => 'DGP-BNPL',
            'payment_gateway'    => 13, // BNPL type
            'delivery_confirmed' => false, // NOT delivered
        ],
    ]);

    $refund = Refund::factory()->create([
        'payment_id'    => $payment->id,
        'order_item_id' => $order->items->first()->id,
        'amount'        => 100000,
    ]);

    $this->mock(DigipayAdminService::class, function ($mock): void {
        $mock->shouldReceive('refund')->andReturn(new RefundResponse(
            statusCode: 0,
            message: 'OK',
            trackingCode: 'DGP-REF-BNPL',
        ));
    });

    $processor    = resolve(DigipayRefundProcessor::class);
    $trackingCode = $processor->process($refund, $order, 100000);

    expect($trackingCode)->toBe('DGP-REF-BNPL');
});

it('processes CREDIT refund after delivery confirmation', function (): void {
    $order   = Order::factory()->withCalculatedTotals([['total' => 500000]])->create();
    $payment = Payment::factory()->for($order)->create([
        'method'      => PaymentMethodEnum::DIGIPAY,
        'amount'      => 500000,
        'status'      => PaymentStatusEnum::COMPLETED,
        'customer_id' => $order->customer_id,
    ]);

    $payment->transactions()->create([
        'transaction_reference' => 'TXN-'.fake()->uuid(),
        'initiated_at'          => now(),
        'gateway_response'      => [
            'tracking_code'      => 'DGP-CREDIT',
            'payment_gateway'    => 5, // CREDIT type
            'delivery_confirmed' => true, // Delivered
        ],
    ]);

    $refund = Refund::factory()->create([
        'payment_id'    => $payment->id,
        'order_item_id' => $order->items->first()->id,
        'amount'        => 100000,
    ]);

    $this->mock(DigipayAdminService::class, function ($mock): void {
        $mock->shouldReceive('refund')->andReturn(new RefundResponse(
            statusCode: 0,
            message: 'OK',
            trackingCode: 'DGP-REF-DELIVERED',
        ));
    });

    $processor    = resolve(DigipayRefundProcessor::class);
    $trackingCode = $processor->process($refund, $order, 100000);

    expect($trackingCode)->toBe('DGP-REF-DELIVERED');
});

// ─── Error Handling ───────────────────────────────────────────────────

it('throws RefundGatewayException when Digipay API fails', function (): void {
    $order   = Order::factory()->withCalculatedTotals([['total' => 500000]])->create();
    $payment = Payment::factory()->for($order)->create([
        'method'      => PaymentMethodEnum::DIGIPAY,
        'amount'      => 500000,
        'status'      => PaymentStatusEnum::COMPLETED,
        'customer_id' => $order->customer_id,
    ]);

    $payment->transactions()->create([
        'transaction_reference' => 'TXN-FAIL',
        'initiated_at'          => now(),
        'gateway_response'      => ['tracking_code' => 'DGP-FAIL', 'payment_gateway' => 0],
    ]);

    $refund = Refund::factory()->create([
        'payment_id'    => $payment->id,
        'order_item_id' => $order->items->first()->id,
        'amount'        => 100000,
    ]);

    $this->mock(DigipayAdminService::class, function ($mock): void {
        $mock->shouldReceive('refund')
            ->andThrow(new DigipayException('Gateway timeout', 500));
    });

    $processor = resolve(DigipayRefundProcessor::class);

    expect(fn () => $processor->process($refund, $order, 100000))
        ->toThrow(RefundGatewayException::class, 'Digipay refund failed');
});

it('returns null when no Digipay payment found', function (): void {
    $order = Order::factory()->withCalculatedTotals([['total' => 100000]])->create();

    // No payments at all - refund has null payment_id
    $refund = Refund::factory()->create([
        'order_id'      => $order->id,
        'payment_id'    => null,
        'order_item_id' => $order->items->first()->id,
        'amount'        => 100000,
    ]);

    $processor    = resolve(DigipayRefundProcessor::class);
    $trackingCode = $processor->process($refund, $order, 100000);

    expect($trackingCode)->toBeNull();
});
