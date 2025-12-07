<?php

declare(strict_types=1);

use App\Data\Admin\Payment\PaymentCreateData;
use App\Enums\Order\OrderStatusEnum;
use App\Enums\Payment\PaymentMethodEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Enums\Payment\PaymentTransactionStatusEnum;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\Staff;
use App\Models\User;
use App\Services\Payment\MellatGatewayPaymentProcessor;
use App\Services\Payment\SoapClientFactory;
use App\Services\PaymentTransactionReferenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->referenceService = app(PaymentTransactionReferenceService::class);

    // Mock SOAP client to prevent real gateway calls
    $this->soapClientMock  = Mockery::mock('SoapClient');
    $this->soapFactoryMock = Mockery::mock(SoapClientFactory::class);
    $this->soapFactoryMock->shouldReceive('create')
        ->andReturn($this->soapClientMock);

    $this->processor = new MellatGatewayPaymentProcessor(
        $this->soapFactoryMock,
        $this->referenceService
    );
});

afterEach(function (): void {
    Mockery::close();
});

it('creates payment transaction record when initiating Mellat gateway payment', function (): void {
    // Arrange
    $customer = User::factory()->create();
    $admin    = Staff::factory()->create();

    $order = Order::factory()->create([
        'customer_id'            => $customer->id,
        'status'                 => OrderStatusEnum::PENDING,
        'grand_total'            => 1000000,
        'full_value_grand_total' => 1000000,
    ]);

    $paymentData = new PaymentCreateData(
        method: PaymentMethodEnum::MELLAT_GATEWAY->value,
        data: null,
        admin_notes: 'Test payment'
    );

    // Mock successful gateway response
    $this->soapClientMock->shouldReceive('bpPayRequest')
        ->once()
        ->andReturn((object) ['return' => '1234567890']);

    // Act
    $result = $this->processor->process($paymentData, $admin, 1000000, $order);

    // Assert
    expect($result->payment)->toBeInstanceOf(Payment::class);
    expect($result->payment->status)->toBe(PaymentStatusEnum::PENDING);
    expect($result->requiresRedirect())->toBeTrue();

    // Verify payment transaction was created
    $transaction = PaymentTransaction::where('payment_id', $result->payment->id)->first();
    expect($transaction)->not->toBeNull();
    expect($transaction->status)->toBe(PaymentTransactionStatusEnum::INITIATED);
    expect($transaction->transaction_reference)->toMatch('/^\d{9,}$/'); // Numeric reference
    expect($transaction->attempt_number)->toBe(1);
    expect($transaction->gateway_request)->toBeArray();
    expect($transaction->gateway_request['orderId'])->toBe($transaction->transaction_reference);
    expect($transaction->gateway_response)->toBeArray();
    expect($transaction->gateway_response['RefId'])->toBe('1234567890');
    expect($transaction->initiated_at)->not->toBeNull();
    expect($transaction->completed_at)->toBeNull();
});

it('increments attempt number for subsequent Mellat payment attempts', function (): void {
    // Arrange
    $customer = User::factory()->create();
    $admin    = Staff::factory()->create();

    $order = Order::factory()->create([
        'customer_id'            => $customer->id,
        'status'                 => OrderStatusEnum::PENDING,
        'grand_total'            => 1000000,
        'full_value_grand_total' => 1000000,
    ]);

    // Create first payment attempt
    Payment::factory()->create([
        'order_id'      => $order->id,
        'customer_id'   => $customer->id,
        'method'        => PaymentMethodEnum::MELLAT_GATEWAY,
        'amount'        => 500000,
        'status'        => PaymentStatusEnum::FAILED,
        'attempt_count' => 1,
    ]);

    $paymentData = new PaymentCreateData(
        method: PaymentMethodEnum::MELLAT_GATEWAY->value,
        data: null,
        admin_notes: 'Retry payment'
    );

    // Mock successful gateway response
    $this->soapClientMock->shouldReceive('bpPayRequest')
        ->once()
        ->andReturn((object) ['return' => '9876543210']);

    // Act
    $result = $this->processor->process($paymentData, $admin, 500000, $order);

    // Assert
    $transaction = PaymentTransaction::where('payment_id', $result->payment->id)->first();
    expect($transaction->attempt_number)->toBe(2);
    expect($result->payment->attempt_count)->toBe(2);
});

it('updates transaction to COMPLETED when Mellat verification succeeds', function (): void {
    // Arrange
    $customer = User::factory()->create();

    $order = Order::factory()->create([
        'customer_id'            => $customer->id,
        'status'                 => OrderStatusEnum::PENDING,
        'grand_total'            => 1000000,
        'full_value_grand_total' => 1000000,
    ]);

    $payment = Payment::factory()->create([
        'order_id'    => $order->id,
        'customer_id' => $customer->id,
        'amount'      => 1000000,
        'method'      => PaymentMethodEnum::MELLAT_GATEWAY,
        'status'      => PaymentStatusEnum::PENDING,
    ]);

    $transaction = PaymentTransaction::factory()->create([
        'payment_id'            => $payment->id,
        'transaction_reference' => '200000001',
        'attempt_number'        => 1,
        'status'                => PaymentTransactionStatusEnum::INITIATED,
        'gateway_request'       => ['orderId' => '200000001'],
        'gateway_response'      => ['RefId' => '1234567890'],
        'initiated_at'          => now()->subMinutes(5),
    ]);

    $callbackData = [
        'RefId'           => '1234567890',
        'ResCode'         => '0',
        'SaleOrderId'     => '200000001',
        'SaleReferenceId' => 'ABC123456',
    ];

    // Mock successful verification and settlement
    $this->soapClientMock->shouldReceive('bpVerifyRequest')
        ->once()
        ->andReturn((object) ['return' => '0']);

    $this->soapClientMock->shouldReceive('bpSettleRequest')
        ->once()
        ->andReturn((object) ['return' => '0']);

    // Act
    $verifiedPayment = $this->processor->verify($payment, $callbackData);

    // Assert
    $transaction->refresh();
    expect($transaction->status)->toBe(PaymentTransactionStatusEnum::COMPLETED);
    expect($transaction->completed_at)->not->toBeNull();
    expect($transaction->gateway_response)->toBeArray();
    expect($transaction->gateway_response['ResCode'])->toBe('0');
    expect($transaction->gateway_response['SaleReferenceId'])->toBe('ABC123456');

    expect($verifiedPayment->status)->toBe(PaymentStatusEnum::COMPLETED);
    expect($verifiedPayment->last_gateway_reference)->toBe('ABC123456');
});

it('updates transaction to FAILED when Mellat verification fails', function (): void {
    // Arrange
    $customer = User::factory()->create();

    $order = Order::factory()->create([
        'customer_id'            => $customer->id,
        'status'                 => OrderStatusEnum::PENDING,
        'grand_total'            => 1000000,
        'full_value_grand_total' => 1000000,
    ]);

    $payment = Payment::factory()->create([
        'order_id'    => $order->id,
        'customer_id' => $customer->id,
        'amount'      => 1000000,
        'method'      => PaymentMethodEnum::MELLAT_GATEWAY,
        'status'      => PaymentStatusEnum::PENDING,
    ]);

    $transaction = PaymentTransaction::factory()->create([
        'payment_id'            => $payment->id,
        'transaction_reference' => '200000002',
        'attempt_number'        => 1,
        'status'                => PaymentTransactionStatusEnum::INITIATED,
        'gateway_request'       => ['orderId' => '200000002'],
        'gateway_response'      => ['RefId' => '9876543210'],
        'initiated_at'          => now()->subMinutes(5),
    ]);

    // Callback indicates failure
    $callbackData = [
        'RefId'           => '9876543210',
        'ResCode'         => '17', // User canceled
        'SaleOrderId'     => '200000002',
        'SaleReferenceId' => '',
    ];

    // Act
    $failedPayment = $this->processor->verify($payment, $callbackData);

    // Assert
    $transaction->refresh();
    expect($transaction->status)->toBe(PaymentTransactionStatusEnum::FAILED);
    expect($transaction->completed_at)->not->toBeNull();
    expect($transaction->error_code)->toBe('17');
    expect($transaction->error_message)->toContain('canceled');

    expect($failedPayment->status)->toBe(PaymentStatusEnum::FAILED);
});

it('updates transaction to FAILED when Mellat settlement fails after successful verification', function (): void {
    // Arrange
    $customer = User::factory()->create();

    $order = Order::factory()->create([
        'customer_id'            => $customer->id,
        'status'                 => OrderStatusEnum::PENDING,
        'grand_total'            => 1000000,
        'full_value_grand_total' => 1000000,
    ]);

    $payment = Payment::factory()->create([
        'order_id'    => $order->id,
        'customer_id' => $customer->id,
        'amount'      => 1000000,
        'method'      => PaymentMethodEnum::MELLAT_GATEWAY,
        'status'      => PaymentStatusEnum::PENDING,
    ]);

    $transaction = PaymentTransaction::factory()->create([
        'payment_id'            => $payment->id,
        'transaction_reference' => '200000003',
        'attempt_number'        => 1,
        'status'                => PaymentTransactionStatusEnum::INITIATED,
        'gateway_request'       => ['orderId' => '200000003'],
        'gateway_response'      => ['RefId' => '5555555555'],
        'initiated_at'          => now()->subMinutes(5),
    ]);

    $callbackData = [
        'RefId'           => '5555555555',
        'ResCode'         => '0',
        'SaleOrderId'     => '200000003',
        'SaleReferenceId' => 'DEF789012',
    ];

    // Mock verification succeeds but settlement fails
    $this->soapClientMock->shouldReceive('bpVerifyRequest')
        ->once()
        ->andReturn((object) ['return' => '0']);

    $this->soapClientMock->shouldReceive('bpSettleRequest')
        ->once()
        ->andReturn((object) ['return' => '61']); // Verification error

    // Act
    $failedPayment = $this->processor->verify($payment, $callbackData);

    // Assert
    $transaction->refresh();
    expect($transaction->status)->toBe(PaymentTransactionStatusEnum::FAILED);
    expect($transaction->completed_at)->not->toBeNull();
    expect($transaction->error_message)->toContain('Settlement failed');
    expect($transaction->gateway_response['verification_success'])->toBeTrue();
    expect($transaction->gateway_response['settlement_failed'])->toBeTrue();

    expect($failedPayment->status)->toBe(PaymentStatusEnum::FAILED);
});

it('generates unique transaction references for multiple Mellat payments', function (): void {
    // Arrange
    $customer = User::factory()->create();
    $admin    = Staff::factory()->create();

    $order = Order::factory()->create([
        'customer_id'            => $customer->id,
        'status'                 => OrderStatusEnum::PENDING,
        'grand_total'            => 2000000,
        'full_value_grand_total' => 2000000,
    ]);

    $paymentData = new PaymentCreateData(
        method: PaymentMethodEnum::MELLAT_GATEWAY->value,
        data: null,
        admin_notes: null,
    );

    // Mock gateway responses
    $this->soapClientMock->shouldReceive('bpPayRequest')
        ->twice()
        ->andReturn(
            (object) ['return' => '1111111111'],
            (object) ['return' => '2222222222']
        );

    // Act
    $result1 = $this->processor->process($paymentData, $admin, 1000000, $order);
    $result2 = $this->processor->process($paymentData, $admin, 1000000, $order);

    // Assert
    $transaction1 = PaymentTransaction::where('payment_id', $result1->payment->id)->first();
    $transaction2 = PaymentTransaction::where('payment_id', $result2->payment->id)->first();

    expect($transaction1->transaction_reference)->not->toBe($transaction2->transaction_reference);
    expect($transaction1->transaction_reference)->toMatch('/^\d{9,}$/');
    expect($transaction2->transaction_reference)->toMatch('/^\d{9,}$/');
    expect((int) $transaction2->transaction_reference)->toBeGreaterThan((int) $transaction1->transaction_reference);
});
