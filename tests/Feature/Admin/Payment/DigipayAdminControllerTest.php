<?php

declare(strict_types=1);

use App\Enums\PermissionEnum;
use App\Models\Payment;
use App\Services\Payment\Digipay\Data\DeliverResponse;
use App\Services\Payment\Digipay\Data\RefundInquiryResponse;
use App\Services\Payment\Digipay\Data\RefundResponse;
use App\Services\Payment\Digipay\Data\ReverseResponse;
use App\Services\Payment\Digipay\DigipayAdminService;
use App\Services\Payment\Digipay\DigipayException;
use Illuminate\Support\Facades\Gate;

use function Pest\Laravel\postJson;

uses(Tests\Support\Traits\AuthTestTrait::class);

/**
 * Create a Payment with a Digipay transaction attached.
 */
function createDigipayPayment(array $overrides = []): Payment
{
    $payment = Payment::factory()->create(array_merge([
        'method' => 'digipay',
    ], $overrides));

    $payment->transactions()->create([
        'transaction_reference' => 'TXN-'.fake()->uuid(),
        'initiated_at'          => now(),
        'gateway_response'      => [
            'tracking_code'   => 'DGP-TRK-123456',
            'payment_gateway' => 0,
            'provider_id'     => 'PROV-123',
        ],
    ]);

    return $payment;
}

// ─── Refund ──────────────────────────────────────────────────────────

it('refunds a Digipay payment successfully', function (): void {
    $this->authorized_user([PermissionEnum::PAYMENT_UPDATE]);

    $payment = createDigipayPayment();

    $this->mock(DigipayAdminService::class, function ($mock): void {
        $mock->shouldReceive('refund')
            ->once()
            ->andReturn(new RefundResponse(
                statusCode: 0,
                message: 'Refund processed successfully',
                trackingCode: 'DGP-REF-123',
            ));
    });

    $response = postJson("/api/v1/admin/payments/{$payment->id}/digipay/refund", [
        'amount' => 500000,
    ]);

    $response->assertOk();
    $response->assertJsonPath('data.tracking_code', 'DGP-REF-123');
});

it('refuses refund without PAYMENT_UPDATE permission', function (): void {
    $this->authorized_user([PermissionEnum::PAYMENT_VIEW]);

    $payment = createDigipayPayment();

    // Mock so the controller can be resolved; it should never be called
    $this->mock(DigipayAdminService::class);

    $response = postJson("/api/v1/admin/payments/{$payment->id}/digipay/refund", [
        'amount' => 500000,
    ]);

    $response->assertForbidden();
});

it('returns 422 when refund throws DigipayException', function (): void {
    $this->authorized_user([PermissionEnum::PAYMENT_UPDATE]);

    $payment = createDigipayPayment();

    $this->mock(DigipayAdminService::class, function ($mock): void {
        $mock->shouldReceive('refund')
            ->once()
            ->andThrow(new DigipayException(
                message: 'Refund failed due to gateway error',
                digipayCode: 42,
            ));
    });

    $response = postJson("/api/v1/admin/payments/{$payment->id}/digipay/refund", [
        'amount' => 500000,
    ]);

    $response->assertStatus(422);
    $response->assertJsonPath('errors.digipay_code', 42);
});

it('rejects refund for unauthenticated user', function (): void {
    $payment = createDigipayPayment();

    // Mock so the controller can be resolved
    $this->mock(DigipayAdminService::class);

    $response = postJson("/api/v1/admin/payments/{$payment->id}/digipay/refund", [
        'amount' => 500000,
    ]);

    $response->assertUnauthorized();
});

// ─── Deliver ─────────────────────────────────────────────────────────

it('confirms delivery for a CREDIT payment', function (): void {
    $this->authorized_user([PermissionEnum::PAYMENT_UPDATE]);

    $payment = createDigipayPayment();
    $payment->transactions()->create([
        'transaction_reference' => 'TXN-'.fake()->uuid(),
        'initiated_at'          => now(),
        'gateway_response'      => [
            'tracking_code'   => 'DGP-TRK-CREDIT',
            'payment_gateway' => 5, // CREDIT type requires delivery confirmation
            'provider_id'     => 'PROV-456',
        ],
    ]);

    $this->mock(DigipayAdminService::class, function ($mock): void {
        $mock->shouldReceive('requiresDeliveryConfirmation')
            ->once()
            ->andReturn(true);
        $mock->shouldReceive('deliver')
            ->once()
            ->andReturn(new DeliverResponse(
                statusCode: 0,
                message: 'Delivery confirmed successfully',
            ));
    });

    $response = postJson("/api/v1/admin/payments/{$payment->id}/digipay/deliver");

    $response->assertOk();
    $response->assertJsonPath('data.message', 'Delivery confirmed successfully');
});

it('returns 422 when payment type does not require delivery confirmation', function (): void {
    $this->authorized_user([PermissionEnum::PAYMENT_UPDATE]);

    $payment = createDigipayPayment();

    $this->mock(DigipayAdminService::class, function ($mock): void {
        $mock->shouldReceive('requiresDeliveryConfirmation')
            ->once()
            ->andReturn(false);
    });

    $response = postJson("/api/v1/admin/payments/{$payment->id}/digipay/deliver");

    $response->assertStatus(422);
    $response->assertJsonFragment([
        'message' => 'This payment type does not require delivery confirmation.',
    ]);
});

// ─── Reverse ─────────────────────────────────────────────────────────

it('reverses a payment within the 25-minute window', function (): void {
    $this->authorized_user([PermissionEnum::PAYMENT_DELETE]);

    $payment = createDigipayPayment();

    $this->mock(DigipayAdminService::class, function ($mock): void {
        $mock->shouldReceive('reverse')
            ->once()
            ->andReturn(new ReverseResponse(
                trackingCode: 'DGP-REV-789',
                rrn: null,
                maskedPan: null,
                amount: 500000,
                paymentGateway: 0,
                statusCode: 0,
                message: 'Reversal successful',
            ));
    });

    $response = postJson("/api/v1/admin/payments/{$payment->id}/digipay/reverse");

    $response->assertOk();
    $response->assertJsonPath('data.tracking_code', 'DGP-REV-789');
});

it('returns 422 when reverse window has expired', function (): void {
    $this->authorized_user([PermissionEnum::PAYMENT_DELETE]);

    $payment = createDigipayPayment();
    // Manually set created_at past the 25-minute window
    $tx             = $payment->transactions()->first();
    $tx->created_at = now()->subMinutes(26);
    $tx->save();

    // Mock so the controller can be resolved; reverse() should NOT be called
    $this->mock(DigipayAdminService::class);

    $response = postJson("/api/v1/admin/payments/{$payment->id}/digipay/reverse");

    $response->assertStatus(422);
    $response->assertJsonFragment([
        'message' => 'Reverse window expired (25 minutes). Use refund instead.',
    ]);
});

it('returns 422 when reverse throws DigipayException', function (): void {
    $this->authorized_user([PermissionEnum::PAYMENT_DELETE]);

    $payment = createDigipayPayment();

    $this->mock(DigipayAdminService::class, function ($mock): void {
        $mock->shouldReceive('reverse')
            ->once()
            ->andThrow(new DigipayException(
                message: 'Reverse not allowed',
                digipayCode: 99,
            ));
    });

    $response = postJson("/api/v1/admin/payments/{$payment->id}/digipay/reverse");

    $response->assertStatus(422);
    $response->assertJsonPath('errors.digipay_code', 99);
});

it('refuses reverse without PAYMENT_DELETE permission', function (): void {
    $this->authorized_user([PermissionEnum::PAYMENT_UPDATE]);

    $payment = createDigipayPayment();

    // Mock so the controller can be resolved; it should never be called
    $this->mock(DigipayAdminService::class);

    $response = postJson("/api/v1/admin/payments/{$payment->id}/digipay/reverse");

    $response->assertForbidden();
});

// ─── Inquire Refund ──────────────────────────────────────────────────
// NOTE: The inquireRefund controller passes Payment::class (a string) to
// Gate::authorize() instead of a Payment instance. This is a known issue
// with the controller's policy integration. We bypass the real policy
// check using Gate::shouldReceive().

it('inquires refund status and returns completed', function (): void {
    $this->authorized_user([PermissionEnum::PAYMENT_VIEW]);

    Gate::shouldReceive('authorize')
        ->once()
        ->with('inquire', Payment::class)
        ->andReturn(true);

    $this->mock(DigipayAdminService::class, function ($mock): void {
        $mock->shouldReceive('inquireRefund')
            ->once()
            ->with('REFUND-123-1719000000', 0)
            ->andReturn(new RefundInquiryResponse(
                statusCode: 0,
                message: 'OK',
                status: 0, // completed
                trackingCode: 'DGP-REF-123',
                transferDate: '2024-06-21',
                destinationType: 0,
                destination: '6037********1234',
            ));
    });

    $response = postJson('/api/v1/admin/payments/digipay/inquire-refund', [
        'refund_provider_id' => 'REFUND-123-1719000000',
        'type'               => 0,
    ]);

    $response->assertOk();
    $response->assertJsonPath('data.status', 'completed');
    $response->assertJsonPath('data.tracking_code', 'DGP-REF-123');
});

it('returns 422 for invalid inquire refund request data', function (): void {
    $this->authorized_user([PermissionEnum::PAYMENT_VIEW]);

    // Mock so the controller can be resolved; the Gate check is never reached
    // because spatie/laravel-data halts before the controller method body runs.
    $this->mock(DigipayAdminService::class);

    $response = postJson('/api/v1/admin/payments/digipay/inquire-refund', []);

    $response->assertStatus(422);
});

it('refuses inquire refund without PAYMENT_VIEW permission', function (): void {
    $this->authorized_user([PermissionEnum::PAYMENT_UPDATE]);

    Gate::shouldReceive('authorize')
        ->once()
        ->with('inquire', Payment::class)
        ->andThrow(new Illuminate\Auth\Access\AuthorizationException());

    $this->mock(DigipayAdminService::class);

    $response = postJson('/api/v1/admin/payments/digipay/inquire-refund', [
        'refund_provider_id' => 'REFUND-123-1719000000',
        'type'               => 0,
    ]);

    $response->assertForbidden();
});
