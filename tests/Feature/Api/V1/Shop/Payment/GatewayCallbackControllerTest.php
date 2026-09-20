<?php

declare(strict_types=1);

use App\Actions\Shop\Payment\VerifyPaymentAction;
use App\Enums\Payment\PaymentStatusEnum;
use App\Exceptions\Payment\DuplicatePaymentException;
use App\Http\Controllers\Api\Shop\Payment\GatewayCallbackController;
use App\Models\Payment;
use Mockery as m;

use function Pest\Laravel\postJson;

covers(GatewayCallbackController::class);

/**
 * Build the shop redirect URL the callback controller produces:
 * {shop domain}/{purpose path}/{identifier}
 */
function gatewayCallbackControllerRedirect(string $path, string $identifier): string
{
    return mb_rtrim(config('payments.redirect.shopdomain'), '/')
        .'/'.mb_trim($path, '/')
        .'/'.$identifier;
}

it('redirects customers to the order details page when the payment is verified', function (): void {
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

    $response->assertRedirect(gatewayCallbackControllerRedirect(config('payments.redirect.order'), $payment->order->increment_id));
})->group('payment');

it('redirects customers to the order details page when the payment verification fails', function (): void {
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

    $response->assertRedirect(gatewayCallbackControllerRedirect(config('payments.redirect.order'), $payment->order->increment_id));
})->group('payment');

it('redirects customers to the wallet topup details page when the payment is verified', function (): void {
    $payment = Payment::factory()->topup()->create([
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

    $response->assertRedirect(gatewayCallbackControllerRedirect(config('payments.redirect.topup'), $payment->uuid));
})->group('payment');

it('redirects customers to the order details page with an error when verification throws', function (): void {
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

    $response->assertRedirect(
        gatewayCallbackControllerRedirect(config('payments.redirect.order'), $payment->order->increment_id)
        ."?payment={$payment->uuid}&error=UNKNOWN_ERROR"
    );
})->group('payment');

it('redirects customers to the order details page with an error code when gateway throws PaymentExceptionContract', function (): void {
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
        ->andThrow(new DuplicatePaymentException(
            paymentId: $payment->id,
            orderId: $payment->order?->id,
        ));

    app()->instance(VerifyPaymentAction::class, $actionMock);

    $response = postJson(route('api.v1.shop.payment.gateway.callback', ['payment' => $payment->uuid]), $callbackPayload);

    $response->assertRedirect(
        gatewayCallbackControllerRedirect(config('payments.redirect.order'), $payment->order->increment_id)
        ."?payment={$payment->uuid}&error=DUPLICATE_PAYMENT"
    );
})->group('payment');
