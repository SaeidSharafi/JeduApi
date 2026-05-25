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
use Exception;
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
        $processor = new MellatGatewayPaymentProcessor($factory, app(\App\Services\PaymentTransactionReferenceService::class));

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
            ->with(Mockery::on(function (array $payload) use ($amount): bool {
                // orderId now uses generated transaction reference, so we only assert amount
                return $payload['amount'] === $amount && isset($payload['orderId']);
            }))
            ->andReturn((object) ['return' => $refId]);

        $processor   = new MellatGatewayPaymentProcessor($factory, app(\App\Services\PaymentTransactionReferenceService::class));
        $paymentData = new PaymentCreateData(
            method: PaymentMethodEnum::MELLAT_GATEWAY->value,
            data: null,
            admin_notes: 'Online payment',
        );

        $result = $processor->process($order, $paymentData, $user, $amount);

        expect($result->requiresRedirect())->toBeTrue()
            ->and($result->redirect_url)->toBe('https://mellat.test/redirect')
            ->and($result->redirect_data)->toBe(['RefId' => $refId])
            ->and($result->payment->status)->toBe(PaymentStatusEnum::PENDING)
            ->and($result->payment->transactions()->count())->toBe(1);
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

        $processor   = new MellatGatewayPaymentProcessor($factory, app(\App\Services\PaymentTransactionReferenceService::class));
        $paymentData = new PaymentCreateData(
            method: PaymentMethodEnum::MELLAT_GATEWAY->value,
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

        $processor   = new MellatGatewayPaymentProcessor($factory, app(\App\Services\PaymentTransactionReferenceService::class));
        $paymentData = new PaymentCreateData(
            method: PaymentMethodEnum::MELLAT_GATEWAY->value,
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

        $processor   = new MellatGatewayPaymentProcessor($factory, app(\App\Services\PaymentTransactionReferenceService::class));
        $paymentData = new PaymentCreateData(
            method: PaymentMethodEnum::MELLAT_GATEWAY->value,
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

        $transactionRef = 'TXN-SUCCESS-123';
        $payment        = Payment::factory()->create([
            'order_id'    => $order->id,
            'customer_id' => $order->customer_id,
            'amount'      => 520_000,
            'method'      => PaymentMethodEnum::MELLAT_GATEWAY->value,
            'status'      => PaymentStatusEnum::PENDING->value,
            'data'        => [],
        ]);
        $payment->transactions()->create([
            'transaction_reference' => $transactionRef,
            'attempt_number'        => 1,
            'status'                => \App\Enums\Payment\PaymentTransactionStatusEnum::INITIATED->value,
            'gateway_request'       => ['orderId' => $transactionRef],
            'gateway_response'      => ['RefId' => 'REF123456789'],
            'initiated_at'          => now(),
        ]);

        $verifyClient = Mockery::mock(SoapClient::class);
        $settleClient = Mockery::mock(SoapClient::class);
        $factory      = Mockery::mock(SoapClientFactory::class);
        $factory->shouldReceive('create')
            ->twice()
            ->with('https://mellat.test/wsdl')
            ->andReturn($verifyClient, $settleClient);

        // Verify request should use transaction reference as orderId
        $verifyClient->shouldReceive('bpVerifyRequest')
            ->once()
            ->with(Mockery::on(function ($params) use ($transactionRef) {
                return $params['orderId']         === $transactionRef
                    && $params['saleOrderId']     === $transactionRef
                    && $params['saleReferenceId'] === 'SALE-REF-123';
            }))
            ->andReturn((object) ['return' => '0']);

        $settleClient->shouldReceive('bpSettleRequest')
            ->once()
            ->with(Mockery::on(function ($params) use ($transactionRef) {
                return $params['orderId']         === $transactionRef
                    && $params['saleOrderId']     === $transactionRef
                    && $params['saleReferenceId'] === 'SALE-REF-123';
            }))
            ->andReturn((object) ['return' => '0']);

        $processor    = new MellatGatewayPaymentProcessor($factory, app(\App\Services\PaymentTransactionReferenceService::class));
        $callbackData = [
            'RefId'           => 'REF123456789',
            'ResCode'         => '0',
            'SaleOrderId'     => $transactionRef,
            'SaleReferenceId' => 'SALE-REF-123',
        ];

        $pendingPayment = Payment::query()->findOrFail($payment->id);
        $updatedPayment = $processor->verify($pendingPayment, $callbackData);

        expect($updatedPayment->status)->toBe(PaymentStatusEnum::COMPLETED)
            ->and($updatedPayment->last_gateway_reference)->toBe('SALE-REF-123');

        // Verify transaction was updated
        $transaction = $payment->transactions()->latest()->first();
        expect($transaction->status->value)->toBe(\App\Enums\Payment\PaymentTransactionStatusEnum::COMPLETED->value)
            ->and($transaction->completed_at)->not->toBeNull();

        Event::assertDispatched(PaymentCompletedEvent::class, function (PaymentCompletedEvent $event) use ($updatedPayment): bool {
            return $event->payment->is($updatedPayment);
        });
    });

    it('throws exception when no transaction found for payment', function (): void {
        $payment = Payment::factory()->create([
            'method' => PaymentMethodEnum::MELLAT_GATEWAY->value,
            'status' => PaymentStatusEnum::PENDING->value,
            'data'   => [],
        ]);
        // Don't create any transaction - this should trigger the error

        $processor = new MellatGatewayPaymentProcessor(
            Mockery::mock(SoapClientFactory::class),
            app(\App\Services\PaymentTransactionReferenceService::class)
        );

        expect(fn () => $processor->verify($payment, [
            'RefId'           => 'REF123',
            'ResCode'         => '0',
            'SaleOrderId'     => 'TXN-123',
            'SaleReferenceId' => 'SALE-REF',
        ]))->toThrow(Exception::class, 'No transaction found for payment');
    });

    it('treats settlement code 45 (already settled) as success', function (): void {
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

        $transactionRef = 'TXN-ALREADY-SETTLED';
        $payment        = Payment::factory()->create([
            'order_id'    => $order->id,
            'customer_id' => $order->customer_id,
            'amount'      => 520_000,
            'method'      => PaymentMethodEnum::MELLAT_GATEWAY->value,
            'status'      => PaymentStatusEnum::PENDING->value,
            'data'        => [],
        ]);
        $payment->transactions()->create([
            'transaction_reference' => $transactionRef,
            'attempt_number'        => 1,
            'status'                => \App\Enums\Payment\PaymentTransactionStatusEnum::INITIATED->value,
            'gateway_request'       => ['orderId' => $transactionRef],
            'gateway_response'      => [],
            'initiated_at'          => now(),
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
            ->andReturn((object) ['return' => '45']); // 45 = Transaction already settled

        $processor    = new MellatGatewayPaymentProcessor($factory, app(\App\Services\PaymentTransactionReferenceService::class));
        $callbackData = [
            'RefId'           => 'REF123456789',
            'ResCode'         => '0',
            'SaleOrderId'     => $transactionRef,
            'SaleReferenceId' => 'SALE-REF-456',
        ];

        $pendingPayment = Payment::query()->findOrFail($payment->id);
        $updatedPayment = $processor->verify($pendingPayment, $callbackData);

        expect($updatedPayment->status)->toBe(PaymentStatusEnum::COMPLETED)
            ->and($updatedPayment->last_gateway_reference)->toBe('SALE-REF-456');

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
        $payment->transactions()->create([
            'transaction_reference' => 'TXN-FAIL',
            'attempt_number'        => 1,
            'status'                => \App\Enums\Payment\PaymentTransactionStatusEnum::INITIATED->value,
            'gateway_request'       => ['dummy' => true],
            'gateway_response'      => [],
            'initiated_at'          => now(),
        ]);

        $factory = Mockery::mock(SoapClientFactory::class);
        $factory->shouldNotReceive('create');

        $processor    = new MellatGatewayPaymentProcessor($factory, app(\App\Services\PaymentTransactionReferenceService::class));
        $callbackData = [
            'RefId'   => 'REF-FAIL',
            'ResCode' => '12',
        ];

        $pendingPayment = Payment::query()->findOrFail($payment->id);
        $updatedPayment = $processor->verify($pendingPayment, $callbackData);

        expect($updatedPayment->status)->toBe(PaymentStatusEnum::FAILED);
        Event::assertNothingDispatched();
    });

    it('marks payment as failed when callback data is missing required fields', function (): void {
        $payment = Payment::factory()->create([
            'method' => PaymentMethodEnum::MELLAT_GATEWAY->value,
            'status' => PaymentStatusEnum::PENDING->value,
            'data'   => [],
        ]);
        $payment->transactions()->create([
            'transaction_reference' => 'TXN-MISSING',
            'attempt_number'        => 1,
            'status'                => \App\Enums\Payment\PaymentTransactionStatusEnum::INITIATED->value,
            'gateway_request'       => ['dummy' => true],
            'gateway_response'      => [],
            'initiated_at'          => now(),
        ]);

        $processor = new MellatGatewayPaymentProcessor(Mockery::mock(SoapClientFactory::class), app(\App\Services\PaymentTransactionReferenceService::class));

        // Missing RefId and ResCode
        expect(fn () => $processor->verify($payment, []))
            ->toThrow(MellatException::class);

        $payment->refresh();
        expect($payment->status)->toBe(PaymentStatusEnum::FAILED);

        $transaction = $payment->transactions()->latest()->first();
        expect($transaction->status->value)->toBe(\App\Enums\Payment\PaymentTransactionStatusEnum::FAILED->value)
            ->and($transaction->completed_at)->not->toBeNull()
            ->and($transaction->error_message)->not->toBeNull();
    });

    it('marks payment as failed when settlement is unsuccessful', function (): void {
        Event::fake([PaymentCompletedEvent::class]);

        $payment = Payment::factory()->create([
            'method' => PaymentMethodEnum::MELLAT_GATEWAY->value,
            'status' => PaymentStatusEnum::PENDING->value,
            'data'   => [],
        ]);
        $transactionRef = 'TXN-SETTLE-FAIL';
        $payment->transactions()->create([
            'transaction_reference' => $transactionRef,
            'attempt_number'        => 1,
            'status'                => \App\Enums\Payment\PaymentTransactionStatusEnum::INITIATED->value,
            'gateway_request'       => ['orderId' => $transactionRef],
            'gateway_response'      => [],
            'initiated_at'          => now(),
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
            ->andReturn((object) ['return' => '1']); // Error code 1

        $processor    = new MellatGatewayPaymentProcessor($factory, app(\App\Services\PaymentTransactionReferenceService::class));
        $callbackData = [
            'RefId'           => 'REF123',
            'ResCode'         => '0',
            'SaleOrderId'     => $transactionRef,
            'SaleReferenceId' => 'SALE-REF',
        ];

        $pendingPayment = Payment::query()->findOrFail($payment->id);
        $updatedPayment = $processor->verify($pendingPayment, $callbackData);

        expect($updatedPayment->status)->toBe(PaymentStatusEnum::FAILED);

        // Check transaction record for failure details
        $transaction = $payment->transactions()->latest()->first();
        expect($transaction->status->value)->toBe(\App\Enums\Payment\PaymentTransactionStatusEnum::FAILED->value)
            ->and($transaction->error_message)->toContain('Settlement failed');

        Event::assertNothingDispatched();
    });

    it('marks payment as failed when verification result is negative', function (): void {
        Event::fake([PaymentCompletedEvent::class]);

        $payment = Payment::factory()->create([
            'method' => PaymentMethodEnum::MELLAT_GATEWAY->value,
            'status' => PaymentStatusEnum::PENDING->value,
            'data'   => [],
        ]);
        $transactionRef = 'TXN-VERIFY-NEG';
        $payment->transactions()->create([
            'transaction_reference' => $transactionRef,
            'attempt_number'        => 1,
            'status'                => \App\Enums\Payment\PaymentTransactionStatusEnum::INITIATED->value,
            'gateway_request'       => ['orderId' => $transactionRef],
            'gateway_response'      => [],
            'initiated_at'          => now(),
        ]);

        $verifyClient = Mockery::mock(SoapClient::class);
        $factory      = Mockery::mock(SoapClientFactory::class);
        $factory->shouldReceive('create')
            ->once()
            ->with('https://mellat.test/wsdl')
            ->andReturn($verifyClient);

        $verifyClient->shouldReceive('bpVerifyRequest')
            ->once()
            ->andReturn((object) ['return' => '1']); // Verification failed

        $processor    = new MellatGatewayPaymentProcessor($factory, app(\App\Services\PaymentTransactionReferenceService::class));
        $callbackData = [
            'RefId'           => 'REF123',
            'ResCode'         => '0',
            'SaleOrderId'     => $transactionRef,
            'SaleReferenceId' => 'SALE-REF',
        ];

        $pendingPayment = Payment::query()->findOrFail($payment->id);
        $updatedPayment = $processor->verify($pendingPayment, $callbackData);

        expect($updatedPayment->status)->toBe(PaymentStatusEnum::FAILED);

        // Check transaction record for failure details
        $transaction = $payment->transactions()->latest()->first();
        expect($transaction->status->value)->toBe(\App\Enums\Payment\PaymentTransactionStatusEnum::FAILED->value)
            ->and($transaction->error_message)->toContain('Gateway verification failed');

        Event::assertNothingDispatched();
    });

    it('rethrows Mellat exception when verification SOAP call fails', function (): void {
        $payment = Payment::factory()->create([
            'method' => PaymentMethodEnum::MELLAT_GATEWAY->value,
            'status' => PaymentStatusEnum::PENDING->value,
            'data'   => [],
        ]);
        $transactionRef = 'TXN-VERIFY-ERR';
        $payment->transactions()->create([
            'transaction_reference' => $transactionRef,
            'attempt_number'        => 1,
            'status'                => \App\Enums\Payment\PaymentTransactionStatusEnum::INITIATED->value,
            'gateway_request'       => ['orderId' => $transactionRef],
            'gateway_response'      => [],
            'initiated_at'          => now(),
        ]);

        $verifyClient = Mockery::mock(SoapClient::class);
        $factory      = Mockery::mock(SoapClientFactory::class);
        $factory->shouldReceive('create')
            ->once()
            ->with('https://mellat.test/wsdl')
            ->andReturn($verifyClient);

        $verifyClient->shouldReceive('bpVerifyRequest')
            ->once()
            ->andThrow(new SoapFault('Server', 'Connection timeout'));

        $processor = new MellatGatewayPaymentProcessor($factory, app(\App\Services\PaymentTransactionReferenceService::class));

        expect(fn () => $processor->verify($payment, [
            'RefId'           => 'REF123',
            'ResCode'         => '0',
            'SaleOrderId'     => $transactionRef,
            'SaleReferenceId' => 'SALE-REF',
        ]))->toThrow(MellatException::class);

        $payment->refresh();
        expect($payment->status)->toBe(PaymentStatusEnum::FAILED);

        $transaction = $payment->transactions()->latest()->first();
        expect($transaction->status->value)->toBe(\App\Enums\Payment\PaymentTransactionStatusEnum::FAILED->value)
            ->and($transaction->error_message)->not->toBeNull();
    });

    it('rethrows Mellat exception when settlement SOAP call fails', function (): void {
        $payment = Payment::factory()->create([
            'method' => PaymentMethodEnum::MELLAT_GATEWAY->value,
            'status' => PaymentStatusEnum::PENDING->value,
            'data'   => [],
        ]);
        $transactionRef = 'TXN-SETTLE-ERR';
        $payment->transactions()->create([
            'transaction_reference' => $transactionRef,
            'attempt_number'        => 1,
            'status'                => \App\Enums\Payment\PaymentTransactionStatusEnum::INITIATED->value,
            'gateway_request'       => ['orderId' => $transactionRef],
            'gateway_response'      => [],
            'initiated_at'          => now(),
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
            ->andThrow(new SoapFault('Server', 'Settlement service unavailable'));

        $processor = new MellatGatewayPaymentProcessor($factory, app(\App\Services\PaymentTransactionReferenceService::class));

        expect(fn () => $processor->verify($payment, [
            'RefId'           => 'REF123',
            'ResCode'         => '0',
            'SaleOrderId'     => $transactionRef,
            'SaleReferenceId' => 'SALE-REF',
        ]))->toThrow(MellatException::class);

        $payment->refresh();
        expect($payment->status)->toBe(PaymentStatusEnum::FAILED);

        $transaction = $payment->transactions()->latest()->first();
        expect($transaction->status->value)->toBe(\App\Enums\Payment\PaymentTransactionStatusEnum::FAILED->value)
            ->and($transaction->error_message)->not->toBeNull();
    });
});
