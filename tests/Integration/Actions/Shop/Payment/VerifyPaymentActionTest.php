<?php

declare(strict_types=1);

use App\Actions\Shop\Payment\VerifyPaymentAction;
use App\Contracts\Payment\PaymentProcessorContract;
use App\Data\Shop\Payment\GatewayCallbackData;
use App\Enums\Payment\PaymentMethodEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Models\Payment;
use App\Services\Payment\PaymentProcessorFactory;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Mockery as m;

it('verifies pending payments via the resolved processor and returns the updated payment', function (): void {
    $payment = Payment::factory()->create([
        'status' => PaymentStatusEnum::PENDING,
        'method' => PaymentMethodEnum::MELLAT_GATEWAY,
    ]);

    $callbackPayload = ['SaleReferenceId' => '987654'];

    $processor = m::mock(PaymentProcessorContract::class);
    $processor->expects('verify')
        ->once()
        ->with(
            m::on(fn (Payment $pending) => $pending->is($payment)),
            $callbackPayload
        )
        ->andReturn(tap($payment)->setAttribute('status', PaymentStatusEnum::COMPLETED));

    $factory = m::mock(PaymentProcessorFactory::class);
    $factory->expects('make')
        ->once()
        ->with(PaymentMethodEnum::MELLAT_GATEWAY)
        ->andReturn($processor);

    $action = new VerifyPaymentAction($factory);

    $result = $action->handle(new GatewayCallbackData(
        payment_uuid: $payment->uuid,
        gateway_response: $callbackPayload
    ));

    expect($result->status)->toBe(PaymentStatusEnum::COMPLETED);
});

it('throws validation exception when the payment is no longer pending', function (): void {
    $payment = Payment::factory()->create([
        'status' => PaymentStatusEnum::COMPLETED,
        'method' => PaymentMethodEnum::MELLAT_GATEWAY,
    ]);

    $factory = m::mock(PaymentProcessorFactory::class);
    $factory->shouldNotReceive('make');

    $action = new VerifyPaymentAction($factory);

    expect(fn (): \App\Models\Payment => $action->handle(new GatewayCallbackData(
        payment_uuid: $payment->uuid,
        gateway_response: ['SaleReferenceId' => '987654'],
    )))->toThrow(ValidationException::class, "Payment {$payment->uuid} is not in pending state.");
});

it('throws model not found exception when the payment uuid is invalid', function (): void {
    $factory = m::mock(PaymentProcessorFactory::class);
    $factory->shouldNotReceive('make');

    $action = new VerifyPaymentAction($factory);

    expect(fn (): \App\Models\Payment => $action->handle(new GatewayCallbackData(
        payment_uuid: (string) Str::uuid(),
        gateway_response: ['SaleReferenceId' => 'missing'],
    )))->toThrow(ModelNotFoundException::class);
});
