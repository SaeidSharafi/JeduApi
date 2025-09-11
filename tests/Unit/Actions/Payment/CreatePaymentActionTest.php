<?php

declare(strict_types=1);

use App\Actions\Admin\Payment\CreatePaymentAction;
use App\Data\Admin\Payment\BankTransferPaymentData;
use App\Data\Admin\Payment\PaymentCreateData;
use App\Enums\Order\OrderItemPaymentTypeEnum;
use App\Enums\Payment\PaymentMethodEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Events\PaymentCompletedEvent;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

describe('CreatePaymentAction', function () {

    beforeEach(function () {
        Event::fake([PaymentCompletedEvent::class]);
        $this->adminUser = App\Models\Staff::factory()->create();
        $this->prodcut   = App\Models\Product::factory()
            ->create(['status' => App\Enums\PublicationStatusEnum::PUBLISHED]);
        $this->prodcutDeliveryOption = App\Models\ProductDeliveryOption::factory()->create([
            'product_id' => $this->prodcut->id,
            'price'      => 50000,
            'status'     => App\Enums\PublicationStatusEnum::PUBLISHED,
        ]);

    });

    // Test creating the first (initial) payment
    it('creates the initial payment for an order', function () {
        $items = [
            [
                'product_delivery_option_id' => App\Models\ProductDeliveryOption::factory()->create(),
                'qty_ordered'                => 1,
                'payment_type'               => OrderItemPaymentTypeEnum::FULL_PAYMENT->value,
                'price'                      => 50000,
                'total'                      => 50000,
                'name'                       => 'Workshop',
            ],
        ];
        $order = Order::factory()
            ->withCalculatedTotals($items)
            ->create();

        $paymentData = new PaymentCreateData(
            method: 'bank_transfer',
            status: PaymentStatusEnum::COMPLETED->value,
            data: new BankTransferPaymentData(
                transaction_id: '123',
                transaction_date: now()->format('Y-m-d'),
                sender_name: 'Test', notes: null),
            admin_notes: 'Initial payment'
        );

        $payment = (app(CreatePaymentAction::class))->handle($order, $paymentData, $this->adminUser);

        expect($payment->amount)->toBe(50000);
        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'order_id' => $order->id, 'amount' => 50000]);
        Event::assertDispatched(PaymentCompletedEvent::class);
    });

    // Test creating the final balance payment
    it('creates the final balance payment correctly', function () {
        $items = [
            [
                'product_delivery_option_id' => $this->prodcutDeliveryOption->id,
                'qty_ordered'                => 1,
                'payment_type'               => OrderItemPaymentTypeEnum::PRE_PAYMENT->value,
                'price'                      => 100000,
                'total'                      => 20000,
                'name'                       => 'Workshop',
            ],
        ];
        $order = Order::factory()
            ->withCalculatedTotals($items)
            ->create();

        $order->payments()->create([
            'customer_id' => $order->customer_id,
            'method'      => PaymentMethodEnum::BANK_TRANSFER->value,
            'amount'      => 20000,
            'status'      => 'completed',
        ]);

        $paymentData = new PaymentCreateData(
            method: PaymentMethodEnum::BANK_TRANSFER->value,
            status: PaymentStatusEnum::COMPLETED->value,
            data: new BankTransferPaymentData(
                transaction_id: '456',
                transaction_date: today()->format('Y-m-d'),
                sender_name: 'Test', notes: null
            ),
            admin_notes: 'Final payment'
        );

        $payment = (app(CreatePaymentAction::class))->handle($order->fresh(), $paymentData, $this->adminUser);

        expect($payment->amount)->toBe(80000);
        expect($payment->data['transaction_date'])->toBe(today()->format('Y-m-d'));
        Event::assertDispatched(PaymentCompletedEvent::class);
    });

    // Test creating a zero-dollar payment for free orders
    it('creates a zero-dollar payment for free orders to trigger fulfillment', function () {
        $order = Order::factory()->create(['grand_total' => 0]);

        $paymentData = new PaymentCreateData(method: 'bank_transfer', status: 'completed', data: null,
            admin_notes: 'Free');
        $payment = (app(CreatePaymentAction::class))->handle($order, $paymentData, $this->adminUser);

        expect($payment->amount)->toBe(0);
        expect($payment->method)->toBe(PaymentMethodEnum::NO_PAYMENT->value);
        Event::assertDispatched(PaymentCompletedEvent::class);
    });

    // Test overpayment protection
    it('throws validation exception when trying to pay a fully paid order', function () {
        $order = Order::factory()->create(['grand_total' => 10000]);
        $order->payments()->create([
            'customer_id' => $order->customer_id,
            'method'      => PaymentMethodEnum::BANK_TRANSFER,
            'amount'      => 10000,
            'status'      => 'completed',
        ]);
        $data = new BankTransferPaymentData(
            transaction_id: '789',
            transaction_date: verta()->format('Y-m-d'),
            sender_name: 'Test', notes: null
        );
        $paymentData = new PaymentCreateData(
            method: PaymentMethodEnum::BANK_TRANSFER->value,
            status: PaymentStatusEnum::COMPLETED->value,
            data: $data,
            admin_notes: 'Overpayment attempt'
        );

        expect(fn () => (app(CreatePaymentAction::class))->handle($order->fresh(), $paymentData, $this->adminUser))
            ->toThrow(ValidationException::class, __('messages.order.already_fully_paid', ['order_id' => $order->increment_id]));
    });

    // Test conditional validation
    it('throws validation exception if bank details are missing for a paid order', function () {
        $order = Order::factory()->create(['grand_total' => 10000]);

        OrderItem::factory()->create([
            'order_id'     => $order->id,
            'qty_ordered'  => 1,
            'payment_type' => OrderItemPaymentTypeEnum::FULL_PAYMENT->value,
            'total'        => 10000,
            'name'         => 'Workshop',
        ]);

        // BankTransferPaymentData is missing required fields
        $paymentData = new PaymentCreateData(method: 'bank_transfer', status: 'completed',
            data: new BankTransferPaymentData(transaction_id: null, transaction_date: null, sender_name: null,
                notes: null),
            admin_notes: null);

        expect(fn () => (app(CreatePaymentAction::class))->handle($order, $paymentData, $this->adminUser))
            ->toThrow(ValidationException::class);
    });

    it('returns null for a free order that already has a completion payment', function () {
        // Arrange: A free order that already has a zero-dollar completed payment
        $items = [
            [
                'product_delivery_option_id' => App\Models\ProductDeliveryOption::factory()->create(),
                'qty_ordered'                => 1,
                'payment_type'               => OrderItemPaymentTypeEnum::FULL_PAYMENT->value,
                'total'                      => 0,
                'name'                       => 'Free Workshop',
            ],
        ];
        $order = Order::factory()
            ->withCalculatedTotals($items)
            ->create();
        $order->payments()->create([
            'amount'      => 0,
            'status'      => 'completed',
            'method'      => PaymentMethodEnum::BANK_TRANSFER,
            'customer_id' => $order->customer_id,
        ]);

        $paymentData = new PaymentCreateData(method: 'bank_transfer', status: 'completed', data: null,
            admin_notes: null);

        // Act
        $result = (app(CreatePaymentAction::class))->handle($order, $paymentData, $this->adminUser);

        // Assert
        expect($result)->toBeNull();
        Event::assertNotDispatched(PaymentCompletedEvent::class);
    });

    it('throws validation exception if there is a pending payment', function () {
        $items = [
            [
                'product_delivery_option_id' => $this->prodcutDeliveryOption->id,
                'qty_ordered'                => 1,
                'payment_type'               => OrderItemPaymentTypeEnum::FULL_PAYMENT->value,
                'total'                      => 10000,
                'price'                      => 10000,
                'name'                       => 'Workshop',
            ],
        ];
        $order = Order::factory()
            ->withCalculatedTotals($items)
            ->create();
        App\Models\Payment::factory()->create([
            'order_id'    => $order->id,
            'status'      => PaymentStatusEnum::PENDING->value,
            'amount'      => 10000,
            'method'      => PaymentMethodEnum::BANK_TRANSFER->value,
            'customer_id' => $order->customer_id,
        ]);

        // BankTransferPaymentData is missing required fields
        $paymentData = new PaymentCreateData(method: 'bank_transfer', status: 'completed',
            data: new BankTransferPaymentData(transaction_id: null, transaction_date: null, sender_name: null,
                notes: null),
            admin_notes: null);

        expect(fn () => (app(CreatePaymentAction::class))->handle($order, $paymentData, $this->adminUser))
            ->toThrow(ValidationException::class,
                __('messages.order.payment_already_pending', ['order_id' => $order->increment_id]));
    });
});
