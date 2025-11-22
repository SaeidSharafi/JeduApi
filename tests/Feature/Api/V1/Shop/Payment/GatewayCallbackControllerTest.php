<?php

declare(strict_types=1);

use App\Actions\Shop\Payment\VerifyPaymentAction;
use App\Data\Shop\Payment\GatewayCallbackData;
use App\Enums\Payment\PaymentStatusEnum;
use App\Models\Payment;
use Illuminate\Support\Facades\Route;
use Mockery as m;
use function Pest\Laravel\postJson;

beforeEach(function (): void {
    foreach (['success', 'failed', 'error'] as $state) {
        $routeName = "shop.payment.{$state}";

        if (! Route::has($routeName)) {
            Route::get("/tests/payment/{$state}", static fn () => "payment-{$state}")
                ->name($routeName);

            app('router')->getRoutes()->refreshNameLookups();
        }
    }
});

it('redirects customers to the success page when the payment is verified', function (): void {
    $payment = Payment::factory()->create([
        'status' => PaymentStatusEnum::PENDING,
    ]);

    expect(Route::has('shop.payment.success'))->toBeTrue();

    $callbackPayload = [
        'payment_uuid' => $payment->uuid,
        'ResCode'      => '0',
    ];

    $actionMock = m::mock(VerifyPaymentAction::class);
    $actionMock->expects('handle')
        ->once()
        ->with(m::on(function (GatewayCallbackData $data) use ($payment, $callbackPayload): bool {
            expect($data->payment_uuid)->toBe($payment->uuid);
            expect($data->gateway_response)->toBe($callbackPayload);

            return true;
        }))
        ->andReturn(tap($payment)->setAttribute('status', PaymentStatusEnum::COMPLETED));

    app()->instance(VerifyPaymentAction::class, $actionMock);

    $response = postJson(route('api.v1.shop.payment.gateway.callback'), $callbackPayload);

    $response->assertRedirect(route('shop.payment.success', ['payment' => $payment->uuid]));
    $response->assertSessionHas('success', 'Payment completed successfully');
});

it('redirects customers to the failure page when the payment verification fails', function (): void {
    $payment = Payment::factory()->create([
        'status' => PaymentStatusEnum::PENDING,
    ]);

    expect(Route::has('shop.payment.failed'))->toBeTrue();

    $callbackPayload = [
        'payment_uuid' => $payment->uuid,
        'ResCode'      => '12',
    ];

    $actionMock = m::mock(VerifyPaymentAction::class);
    $actionMock->expects('handle')
        ->once()
        ->andReturn(tap($payment)->setAttribute('status', PaymentStatusEnum::FAILED));

    app()->instance(VerifyPaymentAction::class, $actionMock);

    $response = postJson(route('api.v1.shop.payment.gateway.callback'), $callbackPayload);

    $response->assertRedirect(route('shop.payment.failed', ['payment' => $payment->uuid]));
    $response->assertSessionHas('error', 'Payment failed. Please try again.');
});

it('redirects customers to the generic error page when verification throws', function (): void {
    $payment = Payment::factory()->create([
        'status' => PaymentStatusEnum::PENDING,
    ]);

    expect(Route::has('shop.payment.error'))->toBeTrue();

    $callbackPayload = [
        'payment_uuid' => $payment->uuid,
        'ResCode'      => '999',
    ];

    $actionMock = m::mock(VerifyPaymentAction::class);
    $actionMock->expects('handle')
        ->once()
        ->andThrow(new RuntimeException('gateway boom'));

    app()->instance(VerifyPaymentAction::class, $actionMock);

    $response = postJson(route('api.v1.shop.payment.gateway.callback'), $callbackPayload);

    $response->assertRedirect(route('shop.payment.error'));
    $response->assertSessionHas('error', 'An error occurred while processing your payment.');
});
