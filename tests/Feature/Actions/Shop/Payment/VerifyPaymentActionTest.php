<?php

declare(strict_types=1);

use App\Actions\Shop\Payment\VerifyPaymentAction;
use App\Contracts\Payment\PaymentProcessorContract;
use App\Enums\Payment\PaymentMethodEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Models\Payment;
use App\Services\Payment\PaymentProcessorFactory;
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

    $result = $action->handle($payment, $callbackPayload);

    expect($result->status)->toBe(PaymentStatusEnum::COMPLETED);
});

it('throws ValidationException when payment status is neither PENDING nor COMPLETED', function (): void {

    $payment = Payment::factory()->create([
        'status' => PaymentStatusEnum::FAILED,
        'method' => PaymentMethodEnum::MELLAT_GATEWAY,
    ]);

    $factory = m::mock(PaymentProcessorFactory::class);
    $factory->shouldNotReceive('make');

    $action = new VerifyPaymentAction($factory);

    expect(fn (): Payment => $action->handle($payment, ['SaleReferenceId' => '987654']))
        ->toThrow(ValidationException::class);

});

it('return same payemnt when the payment is no longer pending', function (): void {
    $payment = Payment::factory()->create([
        'status' => PaymentStatusEnum::COMPLETED,
        'method' => PaymentMethodEnum::MELLAT_GATEWAY,
    ]);

    $factory = m::mock(PaymentProcessorFactory::class);
    $factory->shouldNotReceive('make');

    $action          = new VerifyPaymentAction($factory);
    $returnedPayment = $action->handle($payment, ['SaleReferenceId' => '987654']);

    expect($returnedPayment->status)->toBe(PaymentStatusEnum::COMPLETED)
        ->and($returnedPayment->id)->toBe($payment->id);
});
