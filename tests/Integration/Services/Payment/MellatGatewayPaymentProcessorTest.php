<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Payment;

use App\Data\Admin\Payment\PaymentCreateData;
use App\Enums\Payment\PaymentMethodEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Events\PaymentCompletedEvent;
use App\Exceptions\CustomValidationException;
use App\Exceptions\Gateway\MellatException;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payment\MellatGatewayPaymentProcessor;
use App\Services\Payment\SoapClientFactory;
use Illuminate\Support\Facades\Event;
use Mockery;
use SoapClient;
use SoapFault;

beforeEach(function (): void {
    config()->set('payments.mellat.test_mode', true);
    config()->set('payments.mellat.test_server_url', 'https://mellat.test/wsdl');
    config()->set('payments.mellat.test_gateway_url', 'https://mellat.test/redirect');
    config()->set('payments.mellat.terminal_id', '123456');
    config()->set('payments.mellat.username', 'merchant');
    config()->set('payments.mellat.password', 'secret');
    config()->set('payments.mellat.callback_url', 'https://callback.test/mellat');
});

describe('MellatGatewayPaymentProcessor', function (): void {
    it('reports its supported payment method and redirect behavior', function (): void {
        $factory   = Mockery::mock(SoapClientFactory::class);
        $processor = new MellatGatewayPaymentProcessor($factory);

        expect($processor->canHandle(PaymentMethodEnum::MELLAT_GATEWAY))->toBeTrue()
            ->and($processor->canHandle(PaymentMethodEnum::BANK_TRANSFER))->toBeFalse()
            ->and($processor->requiresRedirect())->toBeTrue();
    });

    it('initiates Mellat payment and returns redirect details', function (): void {
        $user  = User::factory()->create();
        $order = Order::factory()->create([
            'customer_id'            => $user->id,
            'customer_email'         => $user->email,
            'customer_phone'         => $user->phone,
            'customer_first_name'    => $user->first_name,
            'customer_last_name'     => $user->last_name,
            'customer_snapshot_json' => $user->toArray(),
        ]);

        $soapClient = Mockery::mock(SoapClient::class);
        $factory    = Mockery::mock(SoapClientFactory::class);
        $factory->shouldReceive('create')
            ->once()
            ->with('https://mellat.test/wsdl')
            ->andReturn($soapClient);

        $refId  = 'REF123456789';
        $amount = 520_000;
        $soapClient->shouldReceive('bpPayRequest')
            ->once()
            ->with(Mockery::on(function (array $payload) use ($amount, $order): bool {
                return $payload['amount'] === $amount && (string) $payload['orderId'] === (string) $order->increment_id;
            }))
            ->andReturn((object) ['return' => $refId]);

        $processor   = new MellatGatewayPaymentProcessor($factory);
        $paymentData = new PaymentCreateData(
            method: PaymentMethodEnum::MELLAT_GATEWAY->value,
            status: PaymentStatusEnum::PENDING->value,
            data: null,
            admin_notes: 'Online payment',
        );

        $result = $processor->process($order, $paymentData, $user, $amount);

        expect($result->requiresRedirect())->toBeTrue()
            ->and($result->redirect_url)->toBe('https://mellat.test/redirect')
            ->and($result->redirect_data)->toBe(['RefId' => $refId])
            ->and($result->payment->status)->toBe(PaymentStatusEnum::PENDING)
            ->and($result->payment->data['transaction_id'])->toBe($refId);
    });

    it('throws validation exception when Mellat gateway cannot be reached', function (): void {
        $user  = User::factory()->create();
        $order = Order::factory()->create([
            'customer_id'            => $user->id,
            'customer_email'         => $user->email,
            'customer_phone'         => $user->phone,
            'customer_first_name'    => $user->first_name,
            'customer_last_name'     => $user->last_name,
            'customer_snapshot_json' => $user->toArray(),
        ]);

        $soapClient = Mockery::mock(SoapClient::class);
        $soapClient->shouldReceive('bpPayRequest')
            ->once()
            ->andThrow(new SoapFault('Server', 'error'));

        $factory = Mockery::mock(SoapClientFactory::class);
        $factory->shouldReceive('create')
            ->once()
            ->andReturn($soapClient);

        $processor   = new MellatGatewayPaymentProcessor($factory);
        $paymentData = new PaymentCreateData(
            method: PaymentMethodEnum::MELLAT_GATEWAY->value,
            status: PaymentStatusEnum::PENDING->value,
            data: null,
            admin_notes: 'Online payment',
        );

        expect(fn () => $processor->process($order, $paymentData, $user, 120_000))
            ->toThrow(CustomValidationException::class);

        expect($order->payments()->count())->toBe(0);
    });
    it('throws validation exception when Mellat gateway return invalid response', function (): void {
        $user  = User::factory()->create();
        $order = Order::factory()->create([
            'customer_id'            => $user->id,
            'customer_email'         => $user->email,
            'customer_phone'         => $user->phone,
            'customer_first_name'    => $user->first_name,
            'customer_last_name'     => $user->last_name,
            'customer_snapshot_json' => $user->toArray(),
        ]);

        $soapClient = Mockery::mock(SoapClient::class);
        $soapClient->shouldReceive('bpPayRequest')
            ->once()
           ->andReturn((object) ['invalid' => 'INVALID_RESPONSE']);

        $factory = Mockery::mock(SoapClientFactory::class);
        $factory->shouldReceive('create')
            ->once()
            ->andReturn($soapClient);

        $processor   = new MellatGatewayPaymentProcessor($factory);
        $paymentData = new PaymentCreateData(
            method: PaymentMethodEnum::MELLAT_GATEWAY->value,
            status: PaymentStatusEnum::PENDING->value,
            data: null,
            admin_notes: 'Online payment',
        );

        expect(fn () => $processor->process($order, $paymentData, $user, 120_000))
            ->toThrow(MellatException::class);

        expect($order->payments()->count())->toBe(0);
    });

    it('throws Mellat exception when gateway response is invalid', function (): void {
        $user  = User::factory()->create();
        $order = Order::factory()->create([
            'customer_id'            => $user->id,
            'customer_email'         => $user->email,
            'customer_phone'         => $user->phone,
            'customer_first_name'    => $user->first_name,
            'customer_last_name'     => $user->last_name,
            'customer_snapshot_json' => $user->toArray(),
        ]);

        $soapClient = Mockery::mock(SoapClient::class);
        $soapClient->shouldReceive('bpPayRequest')
            ->once()
            ->andReturn((object) ['return' => '12']);

        $factory = Mockery::mock(SoapClientFactory::class);
        $factory->shouldReceive('create')
            ->once()
            ->andReturn($soapClient);

        $processor   = new MellatGatewayPaymentProcessor($factory);
        $paymentData = new PaymentCreateData(
            method: PaymentMethodEnum::MELLAT_GATEWAY->value,
            status: PaymentStatusEnum::PENDING->value,
            data: null,
            admin_notes: null,
        );

        expect(fn () => $processor->process($order, $paymentData, $user, 90_000))
            ->toThrow(MellatException::class, '12');

        expect($order->payments()->count())->toBe(0);
    });

    it('verifies successful Mellat callback and dispatches completion event', function (): void {
        Event::fake([PaymentCompletedEvent::class]);

        $user  = User::factory()->create();
        $order = Order::factory()->create([
            'customer_id'            => $user->id,
            'customer_email'         => $user->email,
            'customer_phone'         => $user->phone,
            'customer_first_name'    => $user->first_name,
            'customer_last_name'     => $user->last_name,
            'customer_snapshot_json' => $user->toArray(),
        ]);

        $payment = Payment::factory()->create([
            'order_id'    => $order->id,
            'customer_id' => $order->customer_id,
            'amount'      => 520_000,
            'method'      => PaymentMethodEnum::MELLAT_GATEWAY->value,
            'status'      => PaymentStatusEnum::PENDING->value,
            'data'        => [],
        ]);

        $verifyClient = Mockery::mock(SoapClient::class);
        $settleClient = Mockery::mock(SoapClient::class);
        $factory      = Mockery::mock(SoapClientFactory::class);
        $factory->shouldReceive('create')
            ->twice()
            ->with('https://mellat.test/wsdl')
            ->andReturn($verifyClient, $settleClient);

        $verifyClient->shouldReceive('bpVerifyRequest')
            ->once()
            ->andReturn((object) ['return' => '0']);
        $settleClient->shouldReceive('bpSettleRequest')
            ->once()
            ->andReturn((object) ['return' => '0']);

        $processor    = new MellatGatewayPaymentProcessor($factory);
        $callbackData = [
            'RefId'           => 'REF123456789',
            'ResCode'         => '0',
            'SaleOrderId'     => (string) $order->increment_id,
            'SaleReferenceId' => 'SALE-REF',
        ];

        $pendingPayment = Payment::query()->findOrFail($payment->id);
        $updatedPayment = $processor->verify($pendingPayment, $callbackData);

        expect($updatedPayment->status)->toBe(PaymentStatusEnum::COMPLETED)
            ->and($updatedPayment->data['callback_data'])->toBe($callbackData);

        Event::assertDispatched(PaymentCompletedEvent::class, function (PaymentCompletedEvent $event) use ($updatedPayment): bool {
            return $event->payment->is($updatedPayment);
        });
    });

    it('treats settlement code 45 as success', function (): void {
        Event::fake([PaymentCompletedEvent::class]);

        $user  = User::factory()->create();
        $order = Order::factory()->create([
            'customer_id'            => $user->id,
            'customer_email'         => $user->email,
            'customer_phone'         => $user->phone,
            'customer_first_name'    => $user->first_name,
            'customer_last_name'     => $user->last_name,
            'customer_snapshot_json' => $user->toArray(),
        ]);

        $payment = Payment::factory()->create([
            'order_id'    => $order->id,
            'customer_id' => $order->customer_id,
            'amount'      => 520_000,
            'method'      => PaymentMethodEnum::MELLAT_GATEWAY->value,
            'status'      => PaymentStatusEnum::PENDING->value,
            'data'        => [],
        ]);

        $verifyClient = Mockery::mock(SoapClient::class);
        $settleClient = Mockery::mock(SoapClient::class);
        $factory      = Mockery::mock(SoapClientFactory::class);
        $factory->shouldReceive('create')
            ->twice()
            ->with('https://mellat.test/wsdl')
            ->andReturn($verifyClient, $settleClient);

        $verifyClient->shouldReceive('bpVerifyRequest')
            ->once()
            ->andReturn((object) ['return' => '0']);
        $settleClient->shouldReceive('bpSettleRequest')
            ->once()
            ->andReturn((object) ['return' => '45']);

        $processor    = new MellatGatewayPaymentProcessor($factory);
        $callbackData = [
            'RefId'           => 'REF123456789',
            'ResCode'         => '0',
            'SaleOrderId'     => (string) $order->increment_id,
            'SaleReferenceId' => 'SALE-REF',
        ];

        $pendingPayment = Payment::query()->findOrFail($payment->id);
        $updatedPayment = $processor->verify($pendingPayment, $callbackData);

        expect($updatedPayment->status)->toBe(PaymentStatusEnum::COMPLETED);
        Event::assertDispatched(PaymentCompletedEvent::class, function (PaymentCompletedEvent $event) use ($updatedPayment): bool {
            return $event->payment->is($updatedPayment);
        });
    });

    it('marks payment as failed when Mellat returns an error code', function (): void {
        Event::fake([PaymentCompletedEvent::class]);

        $payment = Payment::factory()->create([
            'method' => PaymentMethodEnum::MELLAT_GATEWAY->value,
            'status' => PaymentStatusEnum::PENDING->value,
            'data'   => [],
        ]);

        $factory = Mockery::mock(SoapClientFactory::class);
        $factory->shouldNotReceive('create');

        $processor    = new MellatGatewayPaymentProcessor($factory);
        $callbackData = [
            'RefId'   => 'REF-FAIL',
            'ResCode' => '12',
        ];

        $pendingPayment = Payment::query()->findOrFail($payment->id);
        $updatedPayment = $processor->verify($pendingPayment, $callbackData);

        expect($updatedPayment->status)->toBe(PaymentStatusEnum::FAILED);
        Event::assertNothingDispatched();
    });

    it('marks payment as failed when callback data is missing', function (): void {
        $payment = Payment::factory()->create([
            'method' => PaymentMethodEnum::MELLAT_GATEWAY->value,
            'status' => PaymentStatusEnum::PENDING->value,
            'data'   => [],
        ]);

        $processor = new MellatGatewayPaymentProcessor(Mockery::mock(SoapClientFactory::class));

        expect(fn () => $processor->verify($payment, []))
            ->toThrow(MellatException::class);

        $payment->refresh();
        expect($payment->status)->toBe(PaymentStatusEnum::FAILED)
            ->and($payment->data)->toHaveKey('verification_error')
            ->and($payment->data['verification_error'])->toBeString();
    });

    it('marks payment as failed when settlement is unsuccessful', function (): void {
        Event::fake([PaymentCompletedEvent::class]);

        $payment = Payment::factory()->create([
            'method' => PaymentMethodEnum::MELLAT_GATEWAY->value,
            'status' => PaymentStatusEnum::PENDING->value,
            'data'   => [],
        ]);

        $verifyClient = Mockery::mock(SoapClient::class);
        $settleClient = Mockery::mock(SoapClient::class);
        $factory      = Mockery::mock(SoapClientFactory::class);
        $factory->shouldReceive('create')
            ->twice()
            ->with('https://mellat.test/wsdl')
            ->andReturn($verifyClient, $settleClient);

        $verifyClient->shouldReceive('bpVerifyRequest')
            ->once()
            ->andReturn((object) ['return' => '0']);
        $settleClient->shouldReceive('bpSettleRequest')
            ->once()
            ->andReturn((object) ['return' => '1']);

        $processor    = new MellatGatewayPaymentProcessor($factory);
        $callbackData = [
            'RefId'           => 'REF123',
            'ResCode'         => '0',
            'SaleOrderId'     => 'SALE-ORDER',
            'SaleReferenceId' => 'SALE-REF',
        ];

        $pendingPayment = Payment::query()->findOrFail($payment->id);
        $updatedPayment = $processor->verify($pendingPayment, $callbackData);

        expect($updatedPayment->status)->toBe(PaymentStatusEnum::FAILED)
            ->and($updatedPayment->data['settlement_failed'])->toBeTrue();

        Event::assertNothingDispatched();
    });

    it('marks payment as failed when verification result is negative', function (): void {
        Event::fake([PaymentCompletedEvent::class]);

        $payment = Payment::factory()->create([
            'method' => PaymentMethodEnum::MELLAT_GATEWAY->value,
            'status' => PaymentStatusEnum::PENDING->value,
            'data'   => [],
        ]);

        $verifyClient = Mockery::mock(SoapClient::class);
        $factory      = Mockery::mock(SoapClientFactory::class);
        $factory->shouldReceive('create')
            ->once()
            ->with('https://mellat.test/wsdl')
            ->andReturn($verifyClient);

        $verifyClient->shouldReceive('bpVerifyRequest')
            ->once()
            ->andReturn((object) ['return' => '1']);

        $processor    = new MellatGatewayPaymentProcessor($factory);
        $callbackData = [
            'RefId'           => 'REF123',
            'ResCode'         => '0',
            'SaleOrderId'     => 'ORDER-1',
            'SaleReferenceId' => 'SALE-REF',
        ];

        $pendingPayment = Payment::query()->findOrFail($payment->id);
        $updatedPayment = $processor->verify($pendingPayment, $callbackData);

        expect($updatedPayment->status)->toBe(PaymentStatusEnum::FAILED)
            ->and($updatedPayment->data['verification_failed'])->toBeTrue();

        Event::assertNothingDispatched();
    });

    it('rethrows Mellat exception when verification SOAP call fails', function (): void {
        $payment = Payment::factory()->create([
            'method' => PaymentMethodEnum::MELLAT_GATEWAY->value,
            'status' => PaymentStatusEnum::PENDING->value,
            'data'   => [],
        ]);

        $verifyClient = Mockery::mock(SoapClient::class);
        $factory      = Mockery::mock(SoapClientFactory::class);
        $factory->shouldReceive('create')
            ->once()
            ->with('https://mellat.test/wsdl')
            ->andReturn($verifyClient);

        $verifyClient->shouldReceive('bpVerifyRequest')
            ->once()
            ->andThrow(new SoapFault('Server', 'fail'));

        $processor = new MellatGatewayPaymentProcessor($factory);

        expect(fn () => $processor->verify($payment, [
            'RefId'           => 'REF123',
            'ResCode'         => '0',
            'SaleOrderId'     => 'ORDER',
            'SaleReferenceId' => 'SALE-REF',
        ]))->toThrow(MellatException::class);

        $payment->refresh();
        expect($payment->status)->toBe(PaymentStatusEnum::FAILED)
            ->and($payment->data)->toHaveKey('verification_error')
            ->and($payment->data['verification_error'])->toBeString();
    });

    it('rethrows Mellat exception when settlement SOAP call fails', function (): void {
        $payment = Payment::factory()->create([
            'method' => PaymentMethodEnum::MELLAT_GATEWAY->value,
            'status' => PaymentStatusEnum::PENDING->value,
            'data'   => [],
        ]);

        $verifyClient = Mockery::mock(SoapClient::class);
        $settleClient = Mockery::mock(SoapClient::class);
        $factory      = Mockery::mock(SoapClientFactory::class);
        $factory->shouldReceive('create')
            ->twice()
            ->with('https://mellat.test/wsdl')
            ->andReturn($verifyClient, $settleClient);

        $verifyClient->shouldReceive('bpVerifyRequest')
            ->once()
            ->andReturn((object) ['return' => '0']);
        $settleClient->shouldReceive('bpSettleRequest')
            ->once()
            ->andThrow(new SoapFault('Server', 'fail'));

        $processor = new MellatGatewayPaymentProcessor($factory);

        expect(fn () => $processor->verify($payment, [
            'RefId'           => 'REF123',
            'ResCode'         => '0',
            'SaleOrderId'     => 'ORDER',
            'SaleReferenceId' => 'SALE-REF',
        ]))->toThrow(MellatException::class);

        $payment->refresh();
        expect($payment->status)->toBe(PaymentStatusEnum::FAILED)
            ->and($payment->data)->toHaveKey('verification_error')
            ->and($payment->data['verification_error'])->toBeString();
    });
});
