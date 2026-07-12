<?php

declare(strict_types=1);

use App\Actions\Shop\Payment\VerifyPaymentAction;
use App\Enums\Payment\PaymentMethodEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Models\Payment;
use Mockery as m;

use function Pest\Laravel\postJson;

/**
 * Tests for gateway callback specifically covering wallet topup payments
 * and both Mellat / Digipay gateway flows.
 */
describe('Gateway callback with wallet topup', function (): void {

    it('redirects to success for completed Mellat topup', function (): void {
        $payment = Payment::factory()->topup()->create([
            'status' => PaymentStatusEnum::PENDING,
            'method' => PaymentMethodEnum::MELLAT_GATEWAY,
        ]);

        $callbackPayload = ['ResCode' => '0', 'RefId' => 'ref123', 'SaleOrderId' => '123', 'SaleReferenceId' => 'sale456'];

        $actionMock = m::mock(VerifyPaymentAction::class);
        $actionMock->expects('handle')
            ->once()
            ->with(m::on(fn (Payment $p) => $p->is($payment)), $callbackPayload)
            ->andReturn(tap($payment)->setAttribute('status', PaymentStatusEnum::COMPLETED));

        app()->instance(VerifyPaymentAction::class, $actionMock);

        $response = postJson(route('api.v1.shop.payment.gateway.callback', ['payment' => $payment->uuid]), $callbackPayload);

        $response->assertRedirect(config('payments.redirect.success')."?payment={$payment->uuid}&purpose={$payment->purpose->value}");
    })->group('payment', 'wallet');

    it('redirects to failure for failed Mellat topup', function (): void {
        $payment = Payment::factory()->topup()->create([
            'status' => PaymentStatusEnum::PENDING,
            'method' => PaymentMethodEnum::MELLAT_GATEWAY,
        ]);

        $callbackPayload = ['ResCode' => '12', 'RefId' => 'ref123'];

        $actionMock = m::mock(VerifyPaymentAction::class);
        $actionMock->expects('handle')
            ->once()
            ->with(m::on(fn (Payment $p) => $p->is($payment)), $callbackPayload)
            ->andReturn(tap($payment)->setAttribute('status', PaymentStatusEnum::FAILED));

        app()->instance(VerifyPaymentAction::class, $actionMock);

        $response = postJson(route('api.v1.shop.payment.gateway.callback', ['payment' => $payment->uuid]), $callbackPayload);

        $response->assertRedirect(config('payments.redirect.failure')."?payment={$payment->uuid}&purpose={$payment->purpose->value}");
    })->group('payment', 'wallet');

    it('redirects to success for completed Digipay topup', function (): void {
        $payment = Payment::factory()->topup()->create([
            'status' => PaymentStatusEnum::PENDING,
            'method' => PaymentMethodEnum::DIGIPAY,
        ]);

        $callbackPayload = [
            'amount'       => $payment->amount,
            'providerId'   => 'TOP-'.$payment->id.'-ref',
            'trackingCode' => 'track123',
            'result'       => 'SUCCESS',
            'type'         => 2,
        ];

        $actionMock = m::mock(VerifyPaymentAction::class);
        $actionMock->expects('handle')
            ->once()
            ->with(m::on(fn (Payment $p) => $p->is($payment)), $callbackPayload)
            ->andReturn(tap($payment)->setAttribute('status', PaymentStatusEnum::COMPLETED));

        app()->instance(VerifyPaymentAction::class, $actionMock);

        $response = postJson(route('api.v1.shop.payment.gateway.callback', ['payment' => $payment->uuid]), $callbackPayload);

        $response->assertRedirect(config('payments.redirect.success')."?payment={$payment->uuid}&purpose={$payment->purpose->value}");
    })->group('payment', 'wallet');

    it('redirects to failure for failed Digipay topup', function (): void {
        $payment = Payment::factory()->topup()->create([
            'status' => PaymentStatusEnum::PENDING,
            'method' => PaymentMethodEnum::DIGIPAY,
        ]);

        $callbackPayload = [
            'amount'       => $payment->amount,
            'providerId'   => 'TOP-'.$payment->id.'-ref',
            'trackingCode' => 'track456',
            'result'       => 'FAILURE',
            'type'         => 2,
        ];

        $actionMock = m::mock(VerifyPaymentAction::class);
        $actionMock->expects('handle')
            ->once()
            ->with(m::on(fn (Payment $p) => $p->is($payment)), $callbackPayload)
            ->andReturn(tap($payment)->setAttribute('status', PaymentStatusEnum::FAILED));

        app()->instance(VerifyPaymentAction::class, $actionMock);

        $response = postJson(route('api.v1.shop.payment.gateway.callback', ['payment' => $payment->uuid]), $callbackPayload);

        $response->assertRedirect(config('payments.redirect.failure')."?payment={$payment->uuid}&purpose={$payment->purpose->value}");
    })->group('payment', 'wallet');

});

describe('Gateway callback amount mismatch', function (): void {

    it('handles Mellat FinalAmount/amount mismatch via verify action', function (): void {
        $payment = Payment::factory()->topup()->create([
            'status' => PaymentStatusEnum::PENDING,
            'method' => PaymentMethodEnum::MELLAT_GATEWAY,
            'amount' => 500000,
        ]);

        $callbackPayload = [
            'ResCode'         => '0',
            'RefId'           => 'ref123',
            'SaleOrderId'     => '123',
            'SaleReferenceId' => 'sale456',
            'FinalAmount'     => '400000',
        ];

        // The VerifyPaymentAction delegates to the processor which detects the mismatch.
        // We simulate the action returning FAILED status.
        $actionMock = m::mock(VerifyPaymentAction::class);
        $actionMock->expects('handle')
            ->once()
            ->with(m::on(fn (Payment $p) => $p->is($payment)), $callbackPayload)
            ->andReturn(tap($payment)->setAttribute('status', PaymentStatusEnum::FAILED));

        app()->instance(VerifyPaymentAction::class, $actionMock);

        $response = postJson(route('api.v1.shop.payment.gateway.callback', ['payment' => $payment->uuid]), $callbackPayload);

        $response->assertRedirect(config('payments.redirect.failure')."?payment={$payment->uuid}&purpose={$payment->purpose->value}");
    })->group('payment', 'wallet');

    it('handles Digipay amount mismatch via verify action', function (): void {
        $payment = Payment::factory()->topup()->create([
            'status' => PaymentStatusEnum::PENDING,
            'method' => PaymentMethodEnum::DIGIPAY,
            'amount' => 500000,
        ]);

        $callbackPayload = [
            'amount'       => 400000,
            'providerId'   => 'TOP-'.$payment->id.'-ref',
            'trackingCode' => 'track789',
            'result'       => 'SUCCESS',
            'type'         => 2,
        ];

        // The VerifyPaymentAction delegates to Digipay processor which detects amount mismatch.
        $actionMock = m::mock(VerifyPaymentAction::class);
        $actionMock->expects('handle')
            ->once()
            ->with(m::on(fn (Payment $p) => $p->is($payment)), $callbackPayload)
            ->andReturn(tap($payment)->setAttribute('status', PaymentStatusEnum::FAILED));

        app()->instance(VerifyPaymentAction::class, $actionMock);

        $response = postJson(route('api.v1.shop.payment.gateway.callback', ['payment' => $payment->uuid]), $callbackPayload);

        $response->assertRedirect(config('payments.redirect.failure')."?payment={$payment->uuid}&purpose={$payment->purpose->value}");
    })->group('payment', 'wallet');

});

describe('Gateway callback with order payments', function (): void {

    it('redirects to success for completed Mellat order payment', function (): void {
        $payment = Payment::factory()->create([
            'status' => PaymentStatusEnum::PENDING,
            'method' => PaymentMethodEnum::MELLAT_GATEWAY,
        ]);

        $callbackPayload = ['ResCode' => '0', 'RefId' => 'ref123', 'SaleOrderId' => '123', 'SaleReferenceId' => 'sale456'];

        $actionMock = m::mock(VerifyPaymentAction::class);
        $actionMock->expects('handle')
            ->once()
            ->with(m::on(fn (Payment $p) => $p->is($payment)), $callbackPayload)
            ->andReturn(tap($payment)->setAttribute('status', PaymentStatusEnum::COMPLETED));

        app()->instance(VerifyPaymentAction::class, $actionMock);

        $response = postJson(route('api.v1.shop.payment.gateway.callback', ['payment' => $payment->uuid]), $callbackPayload);

        $response->assertRedirect(config('payments.redirect.success')."?payment={$payment->uuid}&purpose={$payment->purpose->value}&order={$payment->order->increment_id}");
    })->group('payment', 'wallet');

    it('redirects to success for completed Digipay order payment', function (): void {
        $payment = Payment::factory()->create([
            'status' => PaymentStatusEnum::PENDING,
            'method' => PaymentMethodEnum::DIGIPAY,
        ]);

        $callbackPayload = [
            'amount'       => $payment->amount,
            'providerId'   => 'ORD-'.$payment->id.'-ref',
            'trackingCode' => 'track_digi_order',
            'result'       => 'SUCCESS',
            'type'         => 2,
        ];

        $actionMock = m::mock(VerifyPaymentAction::class);
        $actionMock->expects('handle')
            ->once()
            ->with(m::on(fn (Payment $p) => $p->is($payment)), $callbackPayload)
            ->andReturn(tap($payment)->setAttribute('status', PaymentStatusEnum::COMPLETED));

        app()->instance(VerifyPaymentAction::class, $actionMock);

        $response = postJson(route('api.v1.shop.payment.gateway.callback', ['payment' => $payment->uuid]), $callbackPayload);

        $response->assertRedirect(config('payments.redirect.success')."?payment={$payment->uuid}&purpose={$payment->purpose->value}&order={$payment->order->increment_id}");
    })->group('payment', 'wallet');

});
