<?php

declare(strict_types=1);

use App\Actions\Shop\Payment\VerifyPaymentAction;
use App\Data\Shop\Payment\GatewayCallbackData;
use App\Enums\Payment\PaymentStatusEnum;
use App\Models\Payment;
use Mockery as m;

use function Pest\Laravel\postJson;

it('redirects customers to the success page when the payment is verified', function (): void {
    $payment = Payment::factory()->create([
        'status' => PaymentStatusEnum::PENDING,
    ]);

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

    $response->assertRedirect(config('payments.redirect.success').'?payment='.$payment->uuid);
});

it('redirects customers to the failure page when the payment verification fails', function (): void {
    $payment = Payment::factory()->create([
        'status' => PaymentStatusEnum::PENDING,
    ]);

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

    $response->assertRedirect(config('payments.redirect.failure').'?payment='.$payment->uuid);
});

it('redirects customers to the generic error page when verification throws', function (): void {
    $payment = Payment::factory()->create([
        'status' => PaymentStatusEnum::PENDING,
    ]);

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

    $response->assertRedirect(config('payments.redirect.failure').'?error=processing_error');
});
