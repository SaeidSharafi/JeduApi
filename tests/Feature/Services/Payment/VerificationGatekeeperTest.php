<?php

declare(strict_types=1);

use App\Enums\Payment\PaymentMethodEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Payment\MellatGatewayPaymentProcessor;

describe('Verification Gatekeeper', function (): void {
    it('blocks verify when order has different completed payment', function (): void {
        // Arrange: Create order with an existing COMPLETED payment
        $order = Order::factory()->create();
        Payment::factory()->create([
            'order_id' => $order->id,
            'status'   => PaymentStatusEnum::COMPLETED,
            'method'   => PaymentMethodEnum::MELLAT_GATEWAY,
        ]);

        // Create a SECOND payment (PENDING) on the same order
        $pendingPayment = Payment::factory()->create([
            'order_id' => $order->id,
            'status'   => PaymentStatusEnum::PENDING,
            'method'   => PaymentMethodEnum::MELLAT_GATEWAY,
        ]);

        // Act & Assert: Verify throws
        $processor = app(MellatGatewayPaymentProcessor::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Order already has a completed payment.');

        $processor->verify($pendingPayment, []);
    });

    it('allows verify for same payment (idempotent retry)', function (): void {
        // Arrange: Create a payment that is ALREADY COMPLETED
        $payment = Payment::factory()->create([
            'status' => PaymentStatusEnum::COMPLETED,
            'method' => PaymentMethodEnum::MELLAT_GATEWAY,
        ]);

        // Act: Try to verify the SAME payment again
        $processor = app(MellatGatewayPaymentProcessor::class);
        $result    = $processor->verify($payment, []);

        // Assert: Returns the same payment, no exception
        expect($result->id)->toBe($payment->id);
    });

    it('allows first-time verification (no completed payment exists)', function (): void {
        // Arrange: Create PENDING payment with no completed payments on order
        $order   = Order::factory()->create();
        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'status'   => PaymentStatusEnum::PENDING,
            'method'   => PaymentMethodEnum::MELLAT_GATEWAY,
        ]);

        // Act: Verify should pass through gatekeeper (actual verification will fail due to mocking)
        $processor = app(MellatGatewayPaymentProcessor::class);
        try {
            $processor->verify($payment, ['RefId' => 'test', 'ResCode' => '0']);
        } catch (RuntimeException $e) {
            // Only allow non-gatekeeper exceptions
            expect($e->getMessage())->not->toBe('Order already has a completed payment.');
        } catch (Throwable $t) {
            // Other errors (mocking, API calls) are expected - gatekeeper passed
            expect($t->getMessage())->not->toBe('Order already has a completed payment.');
        }
    });
});
