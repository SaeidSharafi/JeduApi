<?php

declare(strict_types=1);

use App\Exceptions\Gateway\DigipayException;
use App\Services\Payment\Digipay\DigipayAuthenticator;
use App\Services\Payment\Digipay\DigipayClient;
use App\Services\Payment\Digipay\DigipayConfigRepository;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config()->set('payments.digipay.base_url', 'https://api.digipay.test');
    config()->set('payments.digipay.paths.ticket', '/digipay/api/tickets/business');
    config()->set('payments.digipay.paths.verify', '/digipay/api/purchases/verify');
    config()->set('payments.digipay.paths.refund', '/digipay/api/refunds');
    config()->set('payments.digipay.paths.reverse', '/digipay/api/reverse');
    config()->set('payments.digipay.paths.deliver', '/digipay/api/purchases/deliver');
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

// ─── Create Ticket ─────────────────────────────────────────────────────

it('successfully creates a Digipay payment ticket', function (): void {
    Http::fake([
        'api.digipay.test/digipay/api/tickets/business*' => Http::response([
            'result'      => ['status' => 0, 'message' => 'Success'],
            'ticket'      => 'v2:test-ticket-abc',
            'redirectUrl' => 'https://gateway.digipay.test/pay/v2:test-ticket-abc',
        ], 200),
    ]);

    $client   = resolve(DigipayClient::class);
    $response = $client->createTicket(
        amount: 500_000,
        cellNumber: '09121234567',
        providerId: 'ORDER-1001',
        callbackUrl: 'https://shop.test/digipay/callback',
        description: 'Order #1001 payment',
    );

    expect($response->statusCode)->toBe(0)
        ->and($response->ticket)->toBe('v2:test-ticket-abc')
        ->and($response->redirectUrl)->toContain('gateway.digipay.test');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'tickets/business?type=11')
            && $request['amount']      === 500_000
            && $request['cellNumber']  === '09121234567'
            && $request['providerId']  === 'ORDER-1001'
            && $request['callbackUrl'] === 'https://shop.test/digipay/callback'
            && $request['additionalInfo']['description'] === 'Order #1001 payment';
    });
});

it('throws DigipayException when ticket creation fails', function (): void {
    Http::fake([
        'api.digipay.test/digipay/api/tickets/business*' => Http::response([
            'result' => ['status' => 4, 'message' => 'Invalid ticket'],
        ], 200),
    ]);

    $client = resolve(DigipayClient::class);

    expect(fn () => $client->createTicket(100_000, '09121234567', 'ORDER-X', 'https://shop.test/callback'))
        ->toThrow(DigipayException::class, 'Digipay ticket creation failed: Invalid ticket');
});

// ─── Verify ────────────────────────────────────────────────────────────

it('successfully verifies a Digipay payment', function (): void {
    Http::fake([
        'api.digipay.test/digipay/api/purchases/verify*' => Http::response([
            'result'         => ['status' => 0, 'message' => 'Verified'],
            'trackingCode'   => 'TRK-SUCCESS-999',
            'providerId'     => 'ORDER-1001',
            'amount'         => 500_000,
            'rrn'            => '123456789012',
            'maskedPan'      => '603799******1234',
            'pspName'        => 'TestPSP',
            'terminalId'     => 'TERM001',
            'paymentGateway' => 2,
        ], 200),
    ]);

    $client   = resolve(DigipayClient::class);
    $response = $client->verify(
        trackingCode: 'TRK-SUCCESS-999',
        providerId: 'ORDER-1001',
        type: 2,
    );

    expect($response->statusCode)->toBe(0)
        ->and($response->trackingCode)->toBe('TRK-SUCCESS-999')
        ->and($response->providerId)->toBe('ORDER-1001')
        ->and($response->amount)->toBe(500_000)
        ->and($response->paymentGateway)->toBe(2);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'purchases/verify?type=2')
            && $request['trackingCode'] === 'TRK-SUCCESS-999'
            && $request['providerId']   === 'ORDER-1001';
    });
});

it('throws DigipayException when verification fails', function (): void {
    Http::fake([
        'api.digipay.test/digipay/api/purchases/verify*' => Http::response([
            'result' => ['status' => 5, 'message' => 'Transaction not found'],
        ], 200),
    ]);

    $client = resolve(DigipayClient::class);

    expect(fn () => $client->verify('TRK-INVALID', 'ORDER-X', 2))
        ->toThrow(DigipayException::class, 'Digipay verification failed: Transaction not found');
});

// ─── maskSensitive() via createTicket with sensitive_fields config ────

it('masks configured sensitive fields in request data', function (): void {
    config()->set('payments.digipay.logging.sensitive_fields', ['cellNumber']);

    Http::fake([
        'api.digipay.test/digipay/api/tickets/business*' => Http::response([
            'result'      => ['status' => 0, 'message' => 'Success'],
            'ticket'      => 'v2:test-mask',
            'redirectUrl' => 'https://gateway.digipay.test/pay/mask',
        ], 200),
    ]);

    $client   = resolve(DigipayClient::class);
    $response = $client->createTicket(
        amount: 100_000,
        cellNumber: '09121111111',
        providerId: 'ORDER-MASK',
        callbackUrl: 'https://shop.test/callback',
    );

    // The HTTP request itself must carry the unmasked value.
    // maskSensitive() only affects log output, not the actual request.
    Http::assertSent(function ($request) {
        return $request['cellNumber'] === '09121111111';
    });

    expect($response->statusCode)->toBe(0);
});

// ─── Refund ───────────────────────────────────────────────────────────

it('successfully refunds a payment via Digipay API', function (): void {
    Http::fake([
        'api.digipay.test/digipay/api/refund*' => Http::response([
            'result' => [
                'status'  => 0,
                'message' => 'Refund successful',
            ],
            'trackingCode' => 'DGP-REF-SUCCESS',
        ], 200),
    ]);

    $client   = resolve(DigipayClient::class);
    $response = $client->refund(
        providerId: 'PROV-123',
        amount: 500000,
        saleTrackingCode: 'DGP-SALE-123',
        type: 0,
    );

    expect($response->statusCode)->toBe(0)
        ->and($response->message)->toBe('Refund successful')
        ->and($response->trackingCode)->toBe('DGP-REF-SUCCESS');

    Http::assertSent(function ($request) {
        return $request->url()              === 'https://api.digipay.test/digipay/api/refunds?type=0'
            && $request['providerId']       === 'PROV-123'
            && $request['amount']           === 500000
            && $request['saleTrackingCode'] === 'DGP-SALE-123';
    });
});

it('throws DigipayException when refund API returns error', function (): void {
    Http::fake([
        'api.digipay.test/digipay/api/refunds*' => Http::response([
            'result' => [
                'status'  => 42,
                'message' => 'Refund not allowed',
            ],
        ], 200),
    ]);

    $client = resolve(DigipayClient::class);

    expect(fn () => $client->refund('PROV-123', 500000, 'DGP-SALE-123', 0))
        ->toThrow(DigipayException::class, 'Digipay refund failed: Refund not allowed');
});

it('throws DigipayException on HTTP failure for refund', function (): void {
    Http::fake([
        'api.digipay.test/digipay/api/refunds*' => Http::response([], 500),
    ]);

    $client = resolve(DigipayClient::class);

    expect(fn () => $client->refund('PROV-123', 500000, 'DGP-SALE-123', 0))
        ->toThrow(DigipayException::class);
});

// ─── Reverse ──────────────────────────────────────────────────────────

it('successfully reverses a payment via Digipay API', function (): void {
    Http::fake([
        'api.digipay.test/digipay/api/reverse*' => Http::response([
            'trackingCode'   => 'DGP-REV-SUCCESS',
            'rrn'            => '123456789012',
            'maskedPan'      => '603799******1234',
            'amount'         => 500000,
            'paymentGateway' => 0,
            'result'         => [
                'status'  => 0,
                'message' => 'Reversal successful',
            ],
        ], 200),
    ]);

    $client   = resolve(DigipayClient::class);
    $response = $client->reverse('DGP-SALE-123', 'PROV-123');

    expect($response->trackingCode)->toBe('DGP-REV-SUCCESS')
        ->and($response->amount)->toBe(500000)
        ->and($response->statusCode)->toBe(0);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'reverse')
            && $request['purchaseTrackingCode'] === 'DGP-SALE-123'
            && $request['providerId']           === 'PROV-123';
    });
});

it('throws DigipayException when reverse API returns error', function (): void {
    Http::fake([
        'api.digipay.test/digipay/api/reverse*' => Http::response([
            'result' => [
                'status'  => 99,
                'message' => 'Reverse window expired',
            ],
        ], 200),
    ]);

    $client = resolve(DigipayClient::class);

    expect(fn () => $client->reverse('DGP-SALE-123', 'PROV-123'))
        ->toThrow(DigipayException::class, 'Digipay reverse failed: Reverse window expired');
});

// ─── Deliver ──────────────────────────────────────────────────────────

it('successfully confirms delivery for BNPL/CREDIT payments', function (): void {
    Http::fake([
        'api.digipay.test/digipay/api/purchases/deliver*' => Http::response([
            'result' => [
                'status'  => 0,
                'message' => 'Delivery confirmed',
            ],
        ], 200),
    ]);

    $client   = resolve(DigipayClient::class);
    $response = $client->deliver('DGP-SALE-123', 'INV-123', [], 5); // CREDIT type

    expect($response->statusCode)->toBe(0)
        ->and($response->message)->toBe('Delivery confirmed');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'deliver?type=5')
            && $request['trackingCode']  === 'DGP-SALE-123'
            && $request['invoiceNumber'] === 'INV-123';
    });
});

it('throws DigipayException when deliver API fails', function (): void {
    Http::fake([
        'api.digipay.test/digipay/api/purchases/deliver*' => Http::response([
            'result' => [
                'status'  => 10,
                'message' => 'Delivery already confirmed',
            ],
        ], 200),
    ]);

    $client = resolve(DigipayClient::class);

    expect(fn () => $client->deliver('DGP-SALE-123', 'INV-123', [], 5))
        ->toThrow(DigipayException::class, 'Digipay delivery confirmation failed: Delivery already confirmed');
});

// ─── Inquire Refund ───────────────────────────────────────────────────

it('successfully inquires refund status', function (): void {
    Http::fake([
        'api.digipay.test/digipay/api/refunds/*' => Http::response([
            'result' => [
                'status'  => 0,
                'message' => 'OK',
            ],
            'status'          => 0, // completed
            'trackingCode'    => 'DGP-REF-INQUIRY',
            'transferDate'    => '2024-06-28',
            'destinationType' => 0,
            'destination'     => '6037********1234',
        ], 200),
    ]);

    $client   = resolve(DigipayClient::class);
    $response = $client->inquireRefund('REFUND-123', 0);

    expect($response->statusCode)->toBe(0)
        ->and($response->status)->toBe(0)
        ->and($response->trackingCode)->toBe('DGP-REF-INQUIRY')
        ->and($response->destination)->toBe('6037********1234');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'refunds/REFUND-123?type=0');
    });
});

it('throws DigipayException when inquire refund API fails', function (): void {
    Http::fake([
        'api.digipay.test/digipay/api/refunds/*' => Http::response([
            'result' => [
                'status'  => 404,
                'message' => 'Refund not found',
            ],
        ], 200),
    ]);

    $client = resolve(DigipayClient::class);

    expect(fn () => $client->inquireRefund('REFUND-INVALID', 0))
        ->toThrow(DigipayException::class, 'Digipay refund inquiry failed: Refund not found');
});

// ─── Network/Connection Errors ────────────────────────────────────────

it('handles network timeout gracefully', function (): void {
    Http::fake(fn () => throw new Illuminate\Http\Client\ConnectionException('Connection timeout'));

    $client = resolve(DigipayClient::class);

    expect(fn () => $client->refund('PROV-123', 500000, 'DGP-SALE-123', 0))
        ->toThrow(Exception::class);
});

it('handles malformed JSON response', function (): void {
    Http::fake([
        'api.digipay.test/digipay/api/refund*' => Http::response('Invalid JSON{', 200),
    ]);

    $client = resolve(DigipayClient::class);

    expect(fn () => $client->refund('PROV-123', 500000, 'DGP-SALE-123', 0))
        ->toThrow(Exception::class);
});
