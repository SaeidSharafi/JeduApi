<?php

declare(strict_types=1);

use App\Actions\Shop\Payment\VerifyPaymentAction;
use App\Enums\Payment\PaymentStatusEnum;
use App\Models\Payment;
use Mockery as m;

use function Pest\Laravel\postJson;

it('redirects customers to the success page when the payment is verified', function (): void {
    $payment = Payment::factory()->create([
        'status' => PaymentStatusEnum::PENDING,
        'method' => App\Enums\Payment\PaymentMethodEnum::MELLAT_GATEWAY,
    ]);

    $callbackPayload = [
        'ResCode' => '0',
    ];

    $actionMock = m::mock(VerifyPaymentAction::class);
    $actionMock->expects('handle')
        ->once()
        ->with(m::on(fn (Payment $p) => $p->is($payment)), $callbackPayload)
        ->andReturn(tap($payment)->setAttribute('status', PaymentStatusEnum::COMPLETED));

    app()->instance(VerifyPaymentAction::class, $actionMock);

    $response = postJson(route('api.v1.shop.payment.gateway.callback', ['payment' => $payment->uuid]), $callbackPayload);

    $response->assertRedirect(config('payments.redirect.success')."?payment={$payment->uuid}&purpose={$payment->purpose->value}&order={$payment->order->increment_id}");
})->group('payment');

it('redirects customers to the failure page when the payment verification fails', function (): void {
    $payment = Payment::factory()->create([
        'status' => PaymentStatusEnum::PENDING,
        'method' => App\Enums\Payment\PaymentMethodEnum::MELLAT_GATEWAY,
    ]);

    $callbackPayload = [
        'ResCode' => '12',
    ];

    $actionMock = m::mock(VerifyPaymentAction::class);
    $actionMock->expects('handle')
        ->once()
        ->with(m::on(fn (Payment $p) => $p->is($payment)), $callbackPayload)
        ->andReturn(tap($payment)->setAttribute('status', PaymentStatusEnum::FAILED));

    app()->instance(VerifyPaymentAction::class, $actionMock);

    $response = postJson(route('api.v1.shop.payment.gateway.callback', ['payment' => $payment->uuid]), $callbackPayload);

    $response->assertRedirect(config('payments.redirect.failure')
        ."?payment={$payment->uuid}&purpose={$payment->purpose->value}&order={$payment->order->increment_id}"
    );
})->group('payment');

it('redirects customers to the generic error page when verification throws', function (): void {
    $payment = Payment::factory()->create([
        'status' => PaymentStatusEnum::PENDING,
        'method' => App\Enums\Payment\PaymentMethodEnum::MELLAT_GATEWAY,
    ]);

    $callbackPayload = [
        'ResCode' => '999',
    ];

    $actionMock = m::mock(VerifyPaymentAction::class);
    $actionMock->expects('handle')
        ->once()
        ->with(m::on(fn (Payment $p) => $p->is($payment)), $callbackPayload)
        ->andThrow(new RuntimeException('gateway boom'));

    app()->instance(VerifyPaymentAction::class, $actionMock);

    $response = postJson(route('api.v1.shop.payment.gateway.callback', ['payment' => $payment->uuid]), $callbackPayload);

    $response->assertRedirect(config('payments.redirect.failure')."?payment={$payment->uuid}&error=UNKNOWN_ERROR");
})->group('payment');
