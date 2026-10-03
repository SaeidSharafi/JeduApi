<?php

declare(strict_types=1);

use App\Exceptions\Gateway\DigipayException;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payment\Digipay\Data\RefundInquiryResponse;
use App\Services\Payment\Digipay\Data\RefundResponse;
use App\Services\Payment\Digipay\DigipayAdminService;
use App\Services\Payment\Digipay\DigipayAuthenticator;
use App\Services\Payment\Digipay\DigipayConfigRepository;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config()->set('payments.digipay.endpoints.sandbox.base_url', 'https://api.digipay.test');
    config()->set('payments.digipay.endpoints.sandbox.web_url', 'https://web.digipay.test');
    config()->set('payments.digipay.paths.refund', '/digipay/api/refunds');
    config()->set('payments.digipay.paths.deliver', '/digipay/api/purchases/deliver');
    config()->set('payments.digipay.paths.reverse', '/digipay/api/reverse');
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

// ─── Helpers ──────────────────────────────────────────────────────────

function createDigipayPaymentForService(array $gatewayResponse = []): Payment
{
    $payment = Payment::factory()->create([
        'method' => 'digipay',
        'status' => 'completed',
    ]);

    $payment->transactions()->create([
        'transaction_reference' => 'TXN-'.fake()->uuid(),
        'initiated_at'          => now(),
        'gateway_response'      => $gatewayResponse ?: [
            'tracking_code'   => 'DGP-TRK-SERVICE',
            'payment_gateway' => 0,
            'provider_id'     => 'PROV-123',
        ],
    ]);

    return $payment;
}

// ─── getGatewayResponse() — tracking_code missing ────────────────────

describe('DigipayAdminService', function (): void {

    it('throws DigipayException when transaction has no tracking code', function (): void {
        // Arrange
        $payment = createDigipayPaymentForService([
            'payment_gateway' => 0,
            'provider_id'     => 'PROV-NO-TRK',
            // No 'tracking_code' key
        ]);

        $service = app(DigipayAdminService::class);

        // Act & Assert — all public methods call getGatewayResponse() internally
        expect(fn (): RefundResponse => $service->refund($payment))
            ->toThrow(DigipayException::class, "Payment #{$payment->id} has no Digipay tracking code");

        expect(fn () => $service->deliver($payment))
            ->toThrow(DigipayException::class, "Payment #{$payment->id} has no Digipay tracking code");

        expect(fn () => $service->reverse($payment))
            ->toThrow(DigipayException::class, "Payment #{$payment->id} has no Digipay tracking code");

        expect(fn () => $service->requiresDeliveryConfirmation($payment))
            ->toThrow(DigipayException::class, "Payment #{$payment->id} has no Digipay tracking code");
    });

    // ─── refund() ──────────────────────────────────────────────────────

    it('refunds a payment with specified amount and returns RefundResponse', function (): void {
        // Arrange
        $payment = createDigipayPaymentForService();

        Http::fake([
            'api.digipay.test/digipay/api/refunds*' => Http::response([
                'result' => [
                    'status'  => 0,
                    'message' => 'Refund successful',
                ],
                'trackingCode' => 'DGP-REF-SUCCESS',
            ], 200),
        ]);

        $service = app(DigipayAdminService::class);

        // Act
        $response = $service->refund($payment, amount: 300_000);

        // Assert
        expect($response)->toBeInstanceOf(RefundResponse::class)
            ->and($response->statusCode)->toBe(0)
            ->and($response->message)->toBe('Refund successful')
            ->and($response->trackingCode)->toBe('DGP-REF-SUCCESS');

        Http::assertSent(function (Illuminate\Http\Client\Request $request): bool {
            return str_contains($request->url(), '/digipay/api/refunds')
                && $request['amount']           === 300_000
                && $request['saleTrackingCode'] === 'DGP-TRK-SERVICE';
        });
    });

    it('refunds a payment with default amount (payment amount) when amount is null', function (): void {
        // Arrange
        $payment = createDigipayPaymentForService();
        $payment->update(['amount' => 500_000]);

        Http::fake([
            'api.digipay.test/digipay/api/refunds*' => Http::response([
                'result' => [
                    'status'  => 0,
                    'message' => 'Refund successful',
                ],
                'trackingCode' => 'DGP-REF-DEFAULT',
            ], 200),
        ]);

        $service = app(DigipayAdminService::class);

        // Act
        $response = $service->refund($payment, amount: null);

        // Assert
        expect($response->statusCode)->toBe(0);

        Http::assertSent(function (Illuminate\Http\Client\Request $request): bool {
            return $request['amount'] === 500_000;
        });
    });

    // ─── deliver() ─────────────────────────────────────────────────────

    it('confirms delivery for a CREDIT payment and returns DeliverResponse', function (): void {
        // Arrange
        $user  = User::factory()->create();
        $order = Order::factory()->create(['customer_id' => $user->id]);

        // Create order items — each creates a ProductDeliveryOption with a Product.
        // The fixed deliver() code traverses $item->productDeliveryOption->product_id.
        OrderItem::factory()->count(2)->create(['order_id' => $order->id]);

        $payment = Payment::factory()->create([
            'method'      => 'digipay',
            'status'      => 'completed',
            'order_id'    => $order->id,
            'customer_id' => $user->id,
        ]);

        $payment->transactions()->create([
            'transaction_reference' => 'TXN-DELIVER',
            'initiated_at'          => now(),
            'gateway_response'      => [
                'tracking_code'   => 'DGP-TRK-DELIVER',
                'payment_gateway' => 5, // CREDIT type
                'provider_id'     => 'PROV-DELIVER',
            ],
        ]);

        Http::fake([
            'api.digipay.test/digipay/api/purchases/deliver*' => Http::response([
                'result' => [
                    'status'  => 0,
                    'message' => 'Delivery confirmed successfully',
                ],
            ], 200),
        ]);

        $service = app(DigipayAdminService::class);

        // Act
        $response = $service->deliver($payment);

        // Assert
        expect($response->statusCode)->toBe(0)
            ->and($response->message)->toBe('Delivery confirmed successfully');

        Http::assertSent(function (Illuminate\Http\Client\Request $request) use ($payment): bool {
            return str_contains($request->url(), '/digipay/api/purchases/deliver')
                && $request['trackingCode']  === 'DGP-TRK-DELIVER'
                && $request['invoiceNumber'] === (string) $payment->order->id
                && isset($request['deliveryDate']);
        });
    });

    // ─── reverse() ─────────────────────────────────────────────────────

    it('reverses a payment and returns ReverseResponse', function (): void {
        // Arrange
        $payment = createDigipayPaymentForService();

        Http::fake([
            'api.digipay.test/digipay/api/reverse*' => Http::response([
                'trackingCode'   => 'DGP-REV-SUCCESS',
                'rrn'            => '123456789012',
                'maskedPan'      => '603799******1234',
                'amount'         => 500_000,
                'paymentGateway' => 0,
                'result'         => [
                    'status'  => 0,
                    'message' => 'Reversal successful',
                ],
            ], 200),
        ]);

        $service = app(DigipayAdminService::class);

        // Act
        $response = $service->reverse($payment);

        // Assert
        expect($response->statusCode)->toBe(0)
            ->and($response->message)->toBe('Reversal successful')
            ->and($response->trackingCode)->toBe('DGP-REV-SUCCESS')
            ->and($response->amount)->toBe(500_000);

        Http::assertSent(function (Illuminate\Http\Client\Request $request): bool {
            return str_contains($request->url(), '/digipay/api/reverse')
                && $request['purchaseTrackingCode'] === 'DGP-TRK-SERVICE'
                && $request['providerId']           === 'PROV-123';
        });
    });

    // ─── inquireRefund() ───────────────────────────────────────────────

    it('inquires refund status and returns RefundInquiryResponse', function (): void {
        // Arrange
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

        $service = app(DigipayAdminService::class);

        // Act
        $response = $service->inquireRefund('REFUND-123', type: 0);

        // Assert
        expect($response)->toBeInstanceOf(RefundInquiryResponse::class)
            ->and($response->statusCode)->toBe(0)
            ->and($response->isRefundCompleted())->toBeTrue()
            ->and($response->trackingCode)->toBe('DGP-REF-INQUIRY')
            ->and($response->destination)->toBe('6037********1234');

        Http::assertSent(function (Illuminate\Http\Client\Request $request): bool {
            return str_contains($request->url(), 'refunds/REFUND-123');
        });
    });

    // ─── requiresDeliveryConfirmation() ────────────────────────────────

    it('returns true for CREDIT payments (payment_gateway=5)', function (): void {
        // Arrange
        $payment = createDigipayPaymentForService([
            'tracking_code'   => 'DGP-TRK-CREDIT',
            'payment_gateway' => 5,
            'provider_id'     => 'PROV-CREDIT',
        ]);

        $service = app(DigipayAdminService::class);

        // Act
        $result = $service->requiresDeliveryConfirmation($payment);

        // Assert
        expect($result)->toBeTrue();
    });

    it('returns true for BNPL payments (payment_gateway=13)', function (): void {
        // Arrange
        $payment = createDigipayPaymentForService([
            'tracking_code'   => 'DGP-TRK-BNPL',
            'payment_gateway' => 13,
            'provider_id'     => 'PROV-BNPL',
        ]);

        $service = app(DigipayAdminService::class);

        // Act
        $result = $service->requiresDeliveryConfirmation($payment);

        // Assert
        expect($result)->toBeTrue();
    });

    it('returns false for WALLET payments (payment_gateway=0)', function (): void {
        // Arrange
        $payment = createDigipayPaymentForService([
            'tracking_code'   => 'DGP-TRK-WALLET',
            'payment_gateway' => 0,
            'provider_id'     => 'PROV-WALLET',
        ]);

        $service = app(DigipayAdminService::class);

        // Act
        $result = $service->requiresDeliveryConfirmation($payment);

        // Assert
        expect($result)->toBeFalse();
    });

    it('returns false for IPG payments (payment_gateway=2)', function (): void {
        // Arrange
        $payment = createDigipayPaymentForService([
            'tracking_code'   => 'DGP-TRK-IPG',
            'payment_gateway' => 2,
            'provider_id'     => 'PROV-IPG',
        ]);

        $service = app(DigipayAdminService::class);

        // Act
        $result = $service->requiresDeliveryConfirmation($payment);

        // Assert
        expect($result)->toBeFalse();
    });

});
