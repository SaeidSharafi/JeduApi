<?php

declare(strict_types=1);

use App\Data\Admin\Payment\PaymentProcessResultData;
use App\Enums\Payment\PaymentMethodEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Enums\Payment\PaymentTransactionStatusEnum;
use App\Events\PaymentCompletedEvent;
use App\Exceptions\Gateway\DigipayException;
use App\Exceptions\Payment\DuplicatePaymentException;
use App\Exceptions\Payment\PaymentTransactionNotFoundException;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payment\Digipay\DigipayAuthenticator;
use App\Services\Payment\Digipay\DigipayConfigRepository;
use App\Services\Payment\DigipayPaymentProcessor;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config()->set('payments.digipay.endpoints.sandbox.base_url', 'https://api.digipay.test');
    config()->set('payments.digipay.endpoints.sandbox.web_url', 'https://web.digipay.test');
    config()->set('payments.digipay.paths.ticket', '/digipay/api/tickets/business');
    config()->set('payments.digipay.paths.verify', '/digipay/api/purchases/verify');
    config()->set('payments.digipay.ticket_type', 11);
    config()->set('payments.digipay.timeout', 30);
    config()->set('payments.digipay.logging.channel', 'stack');

    $this->mock(DigipayAuthenticator::class, function ($mock): void {
        $mock->shouldReceive('getAccessToken')->andReturn('test-token');
    });

    $this->mock(DigipayConfigRepository::class, function ($mock): void {
        $mock->shouldReceive('getBaseUrl')->andReturn('https://api.digipay.test');
        $mock->shouldReceive('getTimeout')->andReturn(30);
    });
});

describe('DigipayPaymentProcessor', function (): void {

    // ─── process() ──────────────────────────────────────────────────────

    it('creates Digipay ticket and returns redirect details for order payment', function (): void {
        // Arrange
        $user  = User::factory()->create(['phone' => '09121234567']);
        $order = Order::factory()->create([
            'customer_id'            => $user->id,
            'customer_phone'         => $user->phone,
            'customer_first_name'    => $user->first_name,
            'customer_last_name'     => $user->last_name,
            'customer_snapshot_json' => $user->toArray(),
        ]);
        $payment = Payment::factory()->create([
            'customer_id' => $user->id,
            'order_id'    => $order->id,
            'amount'      => 520_000,
            'method'      => PaymentMethodEnum::DIGIPAY,
            'status'      => PaymentStatusEnum::PENDING,
        ]);

        Http::fake([
            'api.digipay.test/digipay/api/tickets/business*' => Http::response([
                'redirectUrl' => 'https://gateway.digipay.test/pay/test-ticket-123',
                'ticket'      => 'TEST-TICKET-001',
                'result'      => [
                    'status'  => 0,
                    'message' => 'Success',
                ],
            ], 200),
        ]);

        // Act
        $processor = app(DigipayPaymentProcessor::class);
        $result    = $processor->process($payment);

        // Assert
        expect($result)->toBeInstanceOf(PaymentProcessResultData::class)
            ->and($result->requiresRedirect())->toBeTrue()
            ->and($result->redirect_url)->toBe('https://gateway.digipay.test/pay/test-ticket-123')
            ->and($result->redirect_method)->toBe('GET');

        $payment->refresh();
        expect($payment->last_gateway_reference)->not->toBeNull()
            ->and($payment->transactions()->count())->toBe(1);

        $transaction = $payment->transactions()->first();
        expect($transaction->status)->toBe(PaymentTransactionStatusEnum::INITIATED)
            ->and($transaction->gateway_request)->toHaveKey('ticket', 'TEST-TICKET-001')
            ->and($transaction->gateway_request)->toHaveKey('provider_id')
            ->and($transaction->gateway_request)->toHaveKey('amount', 520_000);

        Http::assertSent(function (Illuminate\Http\Client\Request $request): bool {
            return str_contains($request->url(), '/digipay/api/tickets/business');
        });
    });

    it('creates Digipay ticket for wallet topup (no order)', function (): void {
        // Arrange
        $user    = User::factory()->create(['phone' => '09121234567']);
        $payment = Payment::factory()->topup()->create([
            'customer_id' => $user->id,
            'order_id'    => null,
            'amount'      => 250_000,
            'method'      => PaymentMethodEnum::DIGIPAY,
            'status'      => PaymentStatusEnum::PENDING,
        ]);

        Http::fake([
            'api.digipay.test/digipay/api/tickets/business*' => Http::response([
                'redirectUrl' => 'https://gateway.digipay.test/pay/topup-ticket',
                'ticket'      => 'TOPUP-TICKET-001',
                'result'      => [
                    'status'  => 0,
                    'message' => 'Success',
                ],
            ], 200),
        ]);

        // Act
        $processor = app(DigipayPaymentProcessor::class);
        $result    = $processor->process($payment);

        // Assert
        expect($result->redirect_url)->toBe('https://gateway.digipay.test/pay/topup-ticket');

        $payment->refresh();
        expect($payment->transactions()->count())->toBe(1);
    });

    // ─── process() — DigipayException ──────────────────────────────────

    it('marks payment as failed when Digipay client throws during process', function (): void {
        // Arrange
        $user    = User::factory()->create(['phone' => '09121234567']);
        $order   = Order::factory()->create(['customer_id' => $user->id]);
        $payment = Payment::factory()->create([
            'customer_id' => $user->id,
            'order_id'    => $order->id,
            'amount'      => 100_000,
            'method'      => PaymentMethodEnum::DIGIPAY,
            'status'      => PaymentStatusEnum::PENDING,
        ]);

        Http::fake([
            'api.digipay.test/digipay/api/tickets/business*' => Http::response([
                'result' => [
                    'status'  => 99,
                    'message' => 'Gateway unavailable',
                ],
            ], 200),
        ]);

        // Act & Assert
        $processor = app(DigipayPaymentProcessor::class);

        expect(fn (): PaymentProcessResultData => $processor->process($payment))
            ->toThrow(DigipayException::class, 'Digipay ticket creation failed: Gateway unavailable');

        $payment->refresh();
        expect($payment->status)->toBe(PaymentStatusEnum::FAILED);

        $transaction = $payment->transactions()->first();
        expect($transaction->status)->toBe(PaymentTransactionStatusEnum::FAILED)
            ->and($transaction->error_message)->toContain('Gateway unavailable')
            ->and($transaction->completed_at)->not->toBeNull();
    });

    // ─── verify() — successful flow ────────────────────────────────────

    it('verifies successful Digipay callback and dispatches completion event', function (): void {
        // Arrange
        Event::fake([PaymentCompletedEvent::class]);

        $user    = User::factory()->create();
        $order   = Order::factory()->create(['customer_id' => $user->id]);
        $payment = Payment::factory()->create([
            'order_id'    => $order->id,
            'customer_id' => $user->id,
            'amount'      => 520_000,
            'method'      => PaymentMethodEnum::DIGIPAY,
            'status'      => PaymentStatusEnum::PENDING,
        ]);

        $transactionRef = 'DGP-TXN-SUCCESS-001';
        $payment->transactions()->create([
            'transaction_reference' => $transactionRef,
            'attempt_number'        => 1,
            'status'                => PaymentTransactionStatusEnum::INITIATED,
            'gateway_request'       => [
                'provider_id' => $transactionRef,
                'amount'      => 520_000,
            ],
            'initiated_at' => now(),
        ]);

        Http::fake([
            'api.digipay.test/digipay/api/purchases/verify*' => Http::response([
                'trackingCode'   => 'TRK-SUCCESS-999',
                'providerId'     => $transactionRef,
                'amount'         => 520_000,
                'rrn'            => '123456789012',
                'maskedPan'      => '603799******1234',
                'pspName'        => 'TestPSP',
                'terminalId'     => 'TERM001',
                'paymentGateway' => 2,
                'result'         => [
                    'status'  => 0,
                    'message' => 'Verified',
                ],
            ], 200),
        ]);

        $processor    = app(DigipayPaymentProcessor::class);
        $callbackData = [
            'amount'       => 520_000,
            'providerId'   => $transactionRef,
            'trackingCode' => 'TRK-SUCCESS-999',
            'result'       => 'SUCCESS',
            'type'         => 2,
        ];

        // Act
        $updatedPayment = $processor->verify($payment, $callbackData);

        // Assert
        expect($updatedPayment->status)->toBe(PaymentStatusEnum::COMPLETED)
            ->and($updatedPayment->last_gateway_reference)->toBe('TRK-SUCCESS-999');

        $transaction = $payment->transactions()->first();
        expect($transaction->status)->toBe(PaymentTransactionStatusEnum::COMPLETED)
            ->and($transaction->completed_at)->not->toBeNull()
            ->and($transaction->gateway_response)->toHaveKey('tracking_code', 'TRK-SUCCESS-999');

        Event::assertDispatched(PaymentCompletedEvent::class, function (PaymentCompletedEvent $event) use ($updatedPayment): bool {
            return $event->payment->is($updatedPayment);
        });
    });

    // ─── verify() — already completed (gatekeeper) ──────────────────────

    it('returns payment as-is when already completed (idempotent)', function (): void {
        // Arrange
        $payment = Payment::factory()->create([
            'status' => PaymentStatusEnum::COMPLETED,
            'method' => PaymentMethodEnum::DIGIPAY,
        ]);

        // Act
        $processor = app(DigipayPaymentProcessor::class);
        $result    = $processor->verify($payment, []);

        // Assert
        expect($result->id)->toBe($payment->id)
            ->and($result->status)->toBe(PaymentStatusEnum::COMPLETED);
    });

    // ─── verify() — duplicate payment ──────────────────────────────────

    it('throws DuplicatePaymentException when order has completed payment', function (): void {
        // Arrange: Order with an existing completed payment
        $order = Order::factory()->create();

        Payment::factory()->create([
            'order_id' => $order->id,
            'status'   => PaymentStatusEnum::COMPLETED,
            'method'   => PaymentMethodEnum::DIGIPAY,
        ]);

        // Second pending payment on same order
        $pendingPayment = Payment::factory()->create([
            'order_id' => $order->id,
            'status'   => PaymentStatusEnum::PENDING,
            'method'   => PaymentMethodEnum::DIGIPAY,
        ]);

        // Act & Assert
        $processor = app(DigipayPaymentProcessor::class);

        expect(fn (): Payment => $processor->verify($pendingPayment, []))
            ->toThrow(DuplicatePaymentException::class);
    });

    it('allows verify for wallet topup (no order — skips duplicate check)', function (): void {
        // Arrange
        $payment = Payment::factory()->topup()->create([
            'order_id' => null,
            'amount'   => 100_000,
            'method'   => PaymentMethodEnum::DIGIPAY,
            'status'   => PaymentStatusEnum::PENDING,
        ]);

        $transactionRef = 'DGP-TXN-NOORDER-001';
        $payment->transactions()->create([
            'transaction_reference' => $transactionRef,
            'attempt_number'        => 1,
            'status'                => PaymentTransactionStatusEnum::INITIATED,
            'initiated_at'          => now(),
        ]);

        Http::fake([
            'api.digipay.test/digipay/api/purchases/verify*' => Http::response([
                'trackingCode' => 'TRK-NOORDER',
                'providerId'   => $transactionRef,
                'amount'       => 100_000,
                'result'       => ['status' => 0, 'message' => 'OK'],
            ], 200),
        ]);

        // Act — should pass through duplicate check and fail at callback handling
        // (callback result is empty so it will go through the unsuccessful path)
        $processor    = app(DigipayPaymentProcessor::class);
        $callbackData = [
            'amount'       => 0,
            'providerId'   => $transactionRef,
            'trackingCode' => '',
            'result'       => 'FAILURE',
            'type'         => 2,
        ];

        $updatedPayment = $processor->verify($payment, $callbackData);

        // Assert: no exception thrown; payment marked as failed by callback handling
        expect($updatedPayment->status)->toBe(PaymentStatusEnum::FAILED);
    });

    // ─── verify() — amount mismatch ────────────────────────────────────

    it('marks payment as failed when callback amount mismatches payment amount', function (): void {
        // Arrange
        $user    = User::factory()->create();
        $order   = Order::factory()->create(['customer_id' => $user->id]);
        $payment = Payment::factory()->create([
            'order_id'    => $order->id,
            'customer_id' => $user->id,
            'amount'      => 500_000,
            'method'      => PaymentMethodEnum::DIGIPAY,
            'status'      => PaymentStatusEnum::PENDING,
        ]);

        $transactionRef = 'DGP-TXN-MISMATCH-001';
        $payment->transactions()->create([
            'transaction_reference' => $transactionRef,
            'attempt_number'        => 1,
            'status'                => PaymentTransactionStatusEnum::INITIATED,
            'gateway_request'       => [
                'provider_id' => $transactionRef,
                'amount'      => 500_000,
            ],
            'initiated_at' => now(),
        ]);

        Http::fake([
            'api.digipay.test/digipay/api/purchases/verify*' => Http::response([
                'trackingCode' => 'TRK-MM-001',
                'providerId'   => $transactionRef,
                'amount'       => 400_000,
                'result'       => ['status' => 0, 'message' => 'Verified'],
            ], 200),
        ]);

        $processor    = app(DigipayPaymentProcessor::class);
        $callbackData = [
            'amount'       => 400_000, // Does NOT match payment->amount (500_000)
            'providerId'   => $transactionRef,
            'trackingCode' => 'TRK-MM-001',
            'result'       => 'SUCCESS',
            'type'         => 2,
        ];

        // Act
        $updatedPayment = $processor->verify($payment, $callbackData);

        // Assert
        expect($updatedPayment->status)->toBe(PaymentStatusEnum::FAILED);

        $transaction = $payment->transactions()->first();
        expect($transaction->status)->toBe(PaymentTransactionStatusEnum::FAILED)
            ->and($transaction->gateway_response)->toHaveKey('amount_mismatch', true)
            ->and($transaction->gateway_response)->toHaveKey('callback_amount', 400_000)
            ->and($transaction->gateway_response)->toHaveKey('expected_amount', 500_000)
            ->and($transaction->completed_at)->not->toBeNull();
    });

    // ─── verify() — unsuccessful callback ──────────────────────────────

    it('marks payment as failed when Digipay callback indicates failure', function (): void {
        // Arrange
        $user    = User::factory()->create();
        $payment = Payment::factory()->create([
            'customer_id' => $user->id,
            'amount'      => 100_000,
            'method'      => PaymentMethodEnum::DIGIPAY,
            'status'      => PaymentStatusEnum::PENDING,
        ]);

        $transactionRef = 'DGP-TXN-CALLBACK-FAIL';
        $payment->transactions()->create([
            'transaction_reference' => $transactionRef,
            'attempt_number'        => 1,
            'status'                => PaymentTransactionStatusEnum::INITIATED,
            'gateway_request'       => ['provider_id' => $transactionRef],
            'initiated_at'          => now(),
        ]);

        $processor    = app(DigipayPaymentProcessor::class);
        $callbackData = [
            'amount'       => 0,
            'providerId'   => $transactionRef,
            'trackingCode' => '',
            'result'       => 'FAILURE',
            'type'         => 2,
        ];

        // Act — no Http fake needed; client->verify() is never called
        $updatedPayment = $processor->verify($payment, $callbackData);

        // Assert
        expect($updatedPayment->status)->toBe(PaymentStatusEnum::FAILED);

        $transaction = $payment->transactions()->first();
        expect($transaction->status)->toBe(PaymentTransactionStatusEnum::FAILED)
            ->and($transaction->gateway_response)->toHaveKey('result', 'FAILURE')
            ->and($transaction->completed_at)->not->toBeNull();
    });

    // ─── verify() — transaction not found ──────────────────────────────

    it('throws PaymentTransactionNotFoundException when no matching transaction', function (): void {
        // Arrange
        $payment = Payment::factory()->create([
            'method' => PaymentMethodEnum::DIGIPAY,
            'status' => PaymentStatusEnum::PENDING,
        ]);

        $processor    = app(DigipayPaymentProcessor::class);
        $callbackData = [
            'providerId' => 'NONEXISTENT-REF',
            'result'     => 'SUCCESS',
        ];

        // Act & Assert
        expect(fn (): Payment => $processor->verify($payment, $callbackData))
            ->toThrow(PaymentTransactionNotFoundException::class);
    });

    // ─── verify() — DigipayException from client ───────────────────────

    it('rethrows DigipayException when client verify fails', function (): void {
        // Arrange
        $user    = User::factory()->create();
        $order   = Order::factory()->create(['customer_id' => $user->id]);
        $payment = Payment::factory()->create([
            'order_id'    => $order->id,
            'customer_id' => $user->id,
            'amount'      => 300_000,
            'method'      => PaymentMethodEnum::DIGIPAY,
            'status'      => PaymentStatusEnum::PENDING,
        ]);

        $transactionRef = 'DGP-TXN-VERIFY-ERR';
        $payment->transactions()->create([
            'transaction_reference' => $transactionRef,
            'attempt_number'        => 1,
            'status'                => PaymentTransactionStatusEnum::INITIATED,
            'gateway_request'       => ['provider_id' => $transactionRef],
            'initiated_at'          => now(),
        ]);

        Http::fake([
            'api.digipay.test/digipay/api/purchases/verify*' => Http::response([
                'result' => [
                    'status'  => 50,
                    'message' => 'Verification rejected',
                ],
            ], 200),
        ]);

        $processor    = app(DigipayPaymentProcessor::class);
        $callbackData = [
            'amount'       => 300_000,
            'providerId'   => $transactionRef,
            'trackingCode' => 'TRK-ERR',
            'result'       => 'SUCCESS',
            'type'         => 2,
        ];

        // Act & Assert
        expect(fn (): Payment => $processor->verify($payment, $callbackData))
            ->toThrow(DigipayException::class, 'Digipay verification failed: Verification rejected');

        $payment->refresh();
        // Payment should NOT be marked failed by processor since exception propagates
        // (processor only marks failed in the process() catch, not in verify())
        // The VerifyPaymentAction wraps this in a DB transaction, but here we test
        // the processor directly — the exception is thrown but no status change in processor.
        // Actually re-reading verify(): it does NOT catch DigipayException, so
        // the exception propagates without marking anything as failed.
    });

});
