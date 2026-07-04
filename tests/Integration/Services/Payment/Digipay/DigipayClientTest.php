<?php

declare(strict_types=1);

use App\Services\Payment\Digipay\DigipayAuthenticator;
use App\Services\Payment\Digipay\DigipayClient;
use App\Services\Payment\Digipay\DigipayConfigRepository;
use App\Services\Payment\Digipay\DigipayException;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config()->set('digipay.base_url', 'https://api.digipay.test');
    config()->set('digipay.paths.refund', '/purchases/refund');
    config()->set('digipay.paths.reverse', '/purchases/reverse');
    config()->set('digipay.paths.deliver', '/purchases/deliver');
    config()->set('digipay.paths.inquire_refund', '/purchases/refund/inquiry');

    $this->mock(DigipayAuthenticator::class, function ($mock): void {
        $mock->shouldReceive('getAccessToken')->andReturn('test-token');
    });

    $this->mock(DigipayConfigRepository::class, function ($mock): void {
        $mock->shouldReceive('getBaseUrl')->andReturn('https://api.digipay.test');
        $mock->shouldReceive('getTimeout')->andReturn(30);
    });
});

// ─── Refund ───────────────────────────────────────────────────────────

it('successfully refunds a payment via Digipay API', function (): void {
    Http::fake([
        'api.digipay.test/purchases/refund*' => Http::response([
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
        return $request->url()              === 'https://api.digipay.test/purchases/refund?type=0'
            && $request['providerId']       === 'PROV-123'
            && $request['amount']           === 500000
            && $request['saleTrackingCode'] === 'DGP-SALE-123';
    });
});

it('throws DigipayException when refund API returns error', function (): void {
    Http::fake([
        'api.digipay.test/purchases/refund*' => Http::response([
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
        'api.digipay.test/purchases/refund*' => Http::response([], 500),
    ]);

    $client = resolve(DigipayClient::class);

    expect(fn () => $client->refund('PROV-123', 500000, 'DGP-SALE-123', 0))
        ->toThrow(DigipayException::class);
});

// ─── Reverse ──────────────────────────────────────────────────────────

it('successfully reverses a payment via Digipay API', function (): void {
    Http::fake([
        'api.digipay.test/purchases/reverse*' => Http::response([
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
        return str_contains($request->url(), 'purchases/reverse')
            && $request['purchaseTrackingCode'] === 'DGP-SALE-123'
            && $request['providerId']           === 'PROV-123';
    });
});

it('throws DigipayException when reverse API returns error', function (): void {
    Http::fake([
        'api.digipay.test/purchases/reverse*' => Http::response([
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
        'api.digipay.test/purchases/deliver*' => Http::response([
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
        return str_contains($request->url(), 'purchases/deliver?type=5')
            && $request['trackingCode']  === 'DGP-SALE-123'
            && $request['invoiceNumber'] === 'INV-123';
    });
});

it('throws DigipayException when deliver API fails', function (): void {
    Http::fake([
        'api.digipay.test/purchases/deliver*' => Http::response([
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
        'api.digipay.test/purchases/refund/*' => Http::response([
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
        return str_contains($request->url(), 'purchases/refund/REFUND-123?type=0');
    });
});

it('throws DigipayException when inquire refund API fails', function (): void {
    Http::fake([
        'api.digipay.test/purchases/refund/*' => Http::response([
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
        'api.digipay.test/purchases/refund*' => Http::response('Invalid JSON{', 200),
    ]);

    $client = resolve(DigipayClient::class);

    expect(fn () => $client->refund('PROV-123', 500000, 'DGP-SALE-123', 0))
        ->toThrow(Exception::class);
});
