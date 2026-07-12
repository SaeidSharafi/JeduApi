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

describe('CreatePaymentAction', function (): void {

    beforeEach(function (): void {
        Event::fake([PaymentCompletedEvent::class]);
        $this->adminUser = App\Models\Staff::factory()->create();
        $this->prodcut   = App\Models\Product::factory()
            ->create(['status' => App\Enums\Content\PublicationStatusEnum::PUBLISHED]);
        $this->prodcutDeliveryOption = App\Models\ProductDeliveryOption::factory()->create([
            'product_id' => $this->prodcut->id,
            'price'      => 50000,
            'status'     => App\Enums\Content\PublicationStatusEnum::PUBLISHED,
        ]);

    });

    // Test creating the first (initial) payment
    it('creates the initial payment for an order', function (): void {
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
            data: new BankTransferPaymentData(
                transaction_id: '123',
                transaction_date: now()->format('Y-m-d'),
                sender_name: 'Test', notes: null),
            admin_notes: 'Initial payment'
        );

        $result = (app(CreatePaymentAction::class))->handle($order, $paymentData, $this->adminUser);

        expect($result)->not->toBeNull();
        expect($result->payment->amount)->toBe(50000);
        expect($result->requiresRedirect())->toBeFalse();
        $this->assertDatabaseHas('payments', ['id' => $result->payment->id, 'order_id' => $order->id, 'amount' => 50000]);
        Event::assertDispatched(PaymentCompletedEvent::class);
    });

    // Test creating the final balance payment
    it('creates the final balance payment correctly', function (): void {
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
            data: new BankTransferPaymentData(
                transaction_id: '456',
                transaction_date: today()->format('Y-m-d'),
                sender_name: 'Test', notes: null
            ),
            admin_notes: 'Final payment'
        );

        $result = (app(CreatePaymentAction::class))->handle($order->fresh(), $paymentData, $this->adminUser);

        expect($result)->not->toBeNull();
        expect($result->payment->amount)->toBe(80000);
        expect($result->payment->data['transaction_date'])->toBe(today()->format('Y-m-d'));
        expect($result->requiresRedirect())->toBeFalse();
        Event::assertDispatched(PaymentCompletedEvent::class);
    });

    // Test creating a zero-dollar payment for free orders
    it('creates a zero-dollar payment for free orders to trigger fulfillment', function (): void {
        $order = Order::factory()->create(['grand_total' => 0]);

        $paymentData = new PaymentCreateData(
            data: null,
            admin_notes: 'Free'
        );
        $result = (app(CreatePaymentAction::class))->handle($order, $paymentData, $this->adminUser);

        expect($result)->not->toBeNull();
        expect($result->payment->amount)->toBe(0);
        expect($result->payment->method)->toBe(PaymentMethodEnum::NO_PAYMENT);
        expect($result->requiresRedirect())->toBeFalse();
        Event::assertDispatched(PaymentCompletedEvent::class);
    });

    // Test overpayment protection
    it('throws validation exception when trying to pay a fully paid order', function (): void {
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
            data: $data,
            admin_notes: 'Overpayment attempt'
        );

        expect(fn () => (app(CreatePaymentAction::class))->handle($order->fresh(), $paymentData, $this->adminUser))
            ->toThrow(ValidationException::class, __('messages.order.already_fully_paid', ['order_id' => $order->increment_id]));
    });

    // Test conditional validation
    it('throws validation exception if bank details are missing for a paid order', function (): void {
        $order = Order::factory()->create(['grand_total' => 10000]);

        OrderItem::factory()->create([
            'order_id'     => $order->id,
            'qty_ordered'  => 1,
            'payment_type' => OrderItemPaymentTypeEnum::FULL_PAYMENT->value,
            'total'        => 10000,
            'name'         => 'Workshop',
        ]);

        // BankTransferPaymentData is missing required fields
        $paymentData = new PaymentCreateData(
            data: new BankTransferPaymentData(transaction_id: null, transaction_date: null, sender_name: null,
                notes: null),
            admin_notes: null);

        expect(fn () => (app(CreatePaymentAction::class))->handle($order, $paymentData, $this->adminUser))
            ->toThrow(ValidationException::class);
    });

    it('returns the existing payment for a free order that already has a completion payment', function (): void {
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
        $existingPayment = $order->payments()->create([
            'amount'      => 0,
            'status'      => 'completed',
            'method'      => PaymentMethodEnum::BANK_TRANSFER,
            'customer_id' => $order->customer_id,
        ]);

        $paymentData = new PaymentCreateData(data: null, admin_notes: null);

        // Act
        $result = (app(CreatePaymentAction::class))->handle($order, $paymentData, $this->adminUser);

        // Assert: Returns the existing completed payment, doesn't create a duplicate
        expect($result)->not->toBeNull();
        expect($result->payment->id)->toBe($existingPayment->id);
        expect($result->requiresRedirect())->toBeFalse();
        Event::assertNotDispatched(PaymentCompletedEvent::class);
    });

    it('throws validation exception if there is a pending payment', function (): void {
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
        $paymentData = new PaymentCreateData(
            data: new BankTransferPaymentData(transaction_id: "123", transaction_date: verta()->formatDate(), sender_name: "John Doe",
                notes: null),
            admin_notes: null);

        expect(fn () => (app(CreatePaymentAction::class))->handle($order, $paymentData, $this->adminUser))
            ->toThrow(ValidationException::class,
                __('messages.order.payment_already_pending', ['order_id' => $order->increment_id]));
    });

    it('regression: admin creates bank transfer payment successfully', function (): void {
        $items = [
            [
                'product_delivery_option_id' => App\Models\ProductDeliveryOption::factory()->create(),
                'qty_ordered'                => 1,
                'payment_type'               => OrderItemPaymentTypeEnum::FULL_PAYMENT->value,
                'price'                      => 75000,
                'total'                      => 75000,
                'name'                       => 'Regression Test Product',
            ],
        ];
        $order = Order::factory()
            ->withCalculatedTotals($items)
            ->create(['grand_total' => 75000]);

        $paymentData = new PaymentCreateData(
            data: new BankTransferPaymentData(
                transaction_id: 'reg-123',
                transaction_date: now()->format('Y-m-d'),
                sender_name: 'Regression Tester',
                notes: null,
            ),
            admin_notes: 'Regression test payment',
        );

        $result = (app(CreatePaymentAction::class))->handle($order, $paymentData, $this->adminUser);

        expect($result)->not->toBeNull();
        expect($result->payment->amount)->toBe(75000);
        expect($result->payment->method)->toBe(PaymentMethodEnum::BANK_TRANSFER);
        expect($result->requiresRedirect())->toBeFalse();
        $this->assertDatabaseHas('payments', [
            'id'     => $result->payment->id,
            'amount' => 75000,
        ]);
    })->group('payment');
});
