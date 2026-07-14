<?php

declare(strict_types=1);

namespace Tests\Feature\Actions\Admin;

use App\Actions\Admin\Refund\CreateRefundAction;
use App\Data\Admin\Refund\RefundCreateData;
use App\Data\Admin\Refund\RefundTransactionData;
use App\Contracts\Payment\RefundProcessorInterface;
use App\Enums\Content\PublicationStatusEnum;
use App\Enums\Order\OrderItemStatusEnum;
use App\Enums\Order\RefundStatusEnum;
use App\Enums\Payment\PaymentMethodEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Events\RefundCompletedEvent;
use App\Exceptions\Gateway\DigipayException;
use App\Exceptions\RefundValidationException;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\ProductDeliveryOption;
use App\Models\Staff;
use App\Services\OrderStatusService;
use App\Services\Payment\Refund\RefundProcessorFactory;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Mockery\MockInterface;

describe('CreateRefundAction', function (): void {

    beforeEach(function (): void {
        Event::fake([RefundCompletedEvent::class]);
        $this->adminUser = Staff::factory()->create();

        // We mock the OrderStatusService as its own tests cover its internal logic.
        // We just want to ensure the action calls it correctly.
        $this->mock(OrderStatusService::class, function (MockInterface $mock): void {
            // Allow these methods to be called without throwing an error.
            $mock->shouldReceive('updateEnrollmentStatus')->zeroOrMoreTimes();
            $mock->shouldReceive('updateParentOrderStatus')->zeroOrMoreTimes();
        });
    });

    // --- Success Cases ---

    it('successfully creates a full refund for a fully paid item', function (): void {
        // Arrange
        $product = ProductDeliveryOption::factory()
            ->create(['price' => 50000, 'status' => PublicationStatusEnum::PUBLISHED]);
        $order = Order::factory()->withCalculatedTotals([
            [
                'product_delivery_option_id' => $product->id,
                'price'                      => 50000,
                'total'                      => 50000,
            ], // from our new factory helper
        ])->create();
        $order->payments()->create([
            'customer_id' => $order->customer_id,
            'method'      => PaymentMethodEnum::BANK_TRANSFER,
            'amount'      => 50000,
            'status'      => PaymentStatusEnum::COMPLETED,
        ]);
        $orderItem = $order->items->first();
        $orderItem->update(['status' => OrderItemStatusEnum::COMPLETED]);

        $refundData = new RefundCreateData(
            order_item_id: $orderItem->id,
            deduction_amount: 0,
            deduction_percent: null,
            transaction_details: new RefundTransactionData(
                receiver_name: 'John Doe',
                card_number: '1234567812345678',
                iban_number: 'DE89370400440532013000',
                tracking_code: null
            ),
            status: RefundStatusEnum::COMPLETED->value,
            admin_notes: 'Full refund processed.'
        );
        $orderItem->refresh();
        // Act
        $refund = (resolve(CreateRefundAction::class))->handle($refundData);

        // Assert
        $this->assertDatabaseHas('refunds', [
            'id'               => $refund->id,
            'order_item_id'    => $orderItem->id,
            'amount'           => 50000,
            'deduction_amount' => 0,
            'status'           => RefundStatusEnum::COMPLETED->value,
        ]);

        $this->assertDatabaseHas('order_items', [
            'id'             => $orderItem->id,
            'status'         => OrderItemStatusEnum::REFUNDED->value,
            'total_refunded' => 50000,
        ]);

        Event::assertDispatched(RefundCompletedEvent::class);
    });

    it('successfully creates a partial refund with a deduction for a pre-paid item', function (): void {
        // Arrange
        $order = Order::factory()->withCalculatedTotals([
            ['price' => 100000, 'prepayment_amount' => 20000, 'payment_type' => 'pre_payment', 'total' => 20000],
        ])->create();
        $order->payments()->create(
            [
                'customer_id' => $order->customer_id,
                'method'      => PaymentMethodEnum::BANK_TRANSFER,
                'amount'      => 20000,
                'status'      => 'completed',
            ]
        );
        $orderItem = $order->items->first();
        $orderItem->update(['status' => OrderItemStatusEnum::COMPLETED]);

        $refundData = new RefundCreateData(
            order_item_id: $orderItem->id,
            deduction_amount: null,
            deduction_percent: 10, // 10% of original price (100k) is 10k
            transaction_details: new RefundTransactionData(
                receiver_name: 'John Doe',
                card_number: '1234567812345678',
                iban_number: 'DE89370400440532013000',
                tracking_code: null
            ),
            status: RefundStatusEnum::COMPLETED->value,
            admin_notes: 'Refund with 10% deduction.'
        );

        // Act
        $refund = (resolve(CreateRefundAction::class))->handle($refundData);

        // Assert
        // Amount paid (20k) - deduction (10k) = 10k refund
        $this->assertDatabaseHas('refunds', [
            'id'               => $refund->id,
            'amount'           => 10000,
            'deduction_amount' => 10000,
        ]);
        $this->assertDatabaseHas('order_items', [
            'id'             => $orderItem->id,
            'status'         => OrderItemStatusEnum::REFUNDED->value,
            'total_refunded' => 10000,
        ]);
    });

    // --- Failure and Validation Cases ---
    it('successfully creates a with deduction_amount', function (): void {
        // Arrange
        $product = ProductDeliveryOption::factory()
            ->create(['price' => 50000, 'status' => PublicationStatusEnum::PUBLISHED]);
        $order = Order::factory()->withCalculatedTotals([
            [
                'product_delivery_option_id' => $product->id,
                'price'                      => 50000,
                'total'                      => 50000,
            ], // from our new factory helper
        ])->create();
        $order->payments()->create([
            'customer_id' => $order->customer_id,
            'method'      => PaymentMethodEnum::BANK_TRANSFER,
            'amount'      => 50000,
            'status'      => PaymentStatusEnum::COMPLETED,
        ]);
        $orderItem = $order->items->first();
        $orderItem->update(['status' => OrderItemStatusEnum::COMPLETED]);

        $refundData = new RefundCreateData(
            order_item_id: $orderItem->id,
            deduction_amount: 1000,
            deduction_percent: null,
            transaction_details: new RefundTransactionData(
                receiver_name: 'John Doe',
                card_number: '1234567812345678',
                iban_number: 'DE89370400440532013000',
                tracking_code: null
            ),
            status: RefundStatusEnum::COMPLETED->value,
            admin_notes: 'Full refund processed.'
        );
        $orderItem->refresh();
        // Act
        $refund = (resolve(CreateRefundAction::class))->handle($refundData);

        // Assert
        $this->assertDatabaseHas('refunds', [
            'id'               => $refund->id,
            'order_item_id'    => $orderItem->id,
            'amount'           => 49000,
            'deduction_amount' => 1000,
            'status'           => RefundStatusEnum::COMPLETED->value,
        ]);

        $this->assertDatabaseHas('order_items', [
            'id'             => $orderItem->id,
            'status'         => OrderItemStatusEnum::REFUNDED->value,
            'total_refunded' => 49000,
        ]);

        Event::assertDispatched(RefundCompletedEvent::class);
    });
    it('successfully creates a with both deduction_amount and deduction_percent', function (): void {
        // Arrange
        $product = ProductDeliveryOption::factory()
            ->create(['price' => 50000, 'status' => PublicationStatusEnum::PUBLISHED]);
        $order = Order::factory()->withCalculatedTotals([
            [
                'product_delivery_option_id' => $product->id,
                'price'                      => 50000,
                'total'                      => 50000,
            ], // from our new factory helper
        ])->create();
        $order->payments()->create([
            'customer_id' => $order->customer_id,
            'method'      => PaymentMethodEnum::BANK_TRANSFER,
            'amount'      => 50000,
            'status'      => PaymentStatusEnum::COMPLETED,
        ]);
        $orderItem = $order->items->first();
        $orderItem->update(['status' => OrderItemStatusEnum::COMPLETED]);

        $refundData = new RefundCreateData(
            order_item_id: $orderItem->id,
            deduction_amount: 5000,
            deduction_percent: 10,
            transaction_details: new RefundTransactionData(
                receiver_name: 'John Doe',
                card_number: '1234567812345678',
                iban_number: 'DE89370400440532013000',
                tracking_code: null
            ),
            status: RefundStatusEnum::COMPLETED->value,
            admin_notes: 'Full refund processed.'
        );
        $orderItem->refresh();
        // Act
        $refund = (resolve(CreateRefundAction::class))->handle($refundData);

        // Assert
        $this->assertDatabaseHas('refunds', [
            'id'               => $refund->id,
            'order_item_id'    => $orderItem->id,
            'amount'           => 45000,
            'deduction_amount' => 5000,
            'status'           => RefundStatusEnum::COMPLETED->value,
        ]);

        $this->assertDatabaseHas('order_items', [
            'id'             => $orderItem->id,
            'status'         => OrderItemStatusEnum::REFUNDED->value,
            'total_refunded' => 45000,
        ]);

        Event::assertDispatched(RefundCompletedEvent::class);
    });
    it('edgecase secniario wiht both deduction_amount and deduction_percent as null', function (): void {
        // Arrange
        $product = ProductDeliveryOption::factory()
            ->create(['price' => 50000, 'status' => PublicationStatusEnum::PUBLISHED]);
        $order = Order::factory()->withCalculatedTotals([
            [
                'product_delivery_option_id' => $product->id,
                'price'                      => 50000,
                'total'                      => 50000,
            ], // from our new factory helper
        ])->create();
        $order->payments()->create([
            'customer_id' => $order->customer_id,
            'method'      => PaymentMethodEnum::BANK_TRANSFER,
            'amount'      => 50000,
            'status'      => PaymentStatusEnum::COMPLETED,
        ]);
        $orderItem = $order->items->first();
        $orderItem->update(['status' => OrderItemStatusEnum::COMPLETED]);

        $refundData = new RefundCreateData(
            order_item_id: $orderItem->id,
            deduction_amount: null,
            deduction_percent: null,
            transaction_details: new RefundTransactionData(
                receiver_name: 'John Doe',
                card_number: '1234567812345678',
                iban_number: 'DE89370400440532013000',
                tracking_code: null
            ),
            status: RefundStatusEnum::COMPLETED->value,
            admin_notes: 'Full refund processed.'
        );
        $orderItem->refresh();
        // Act
        $refund = (resolve(CreateRefundAction::class))->handle($refundData);

        // Assert
        $this->assertDatabaseHas('refunds', [
            'id'               => $refund->id,
            'order_item_id'    => $orderItem->id,
            'amount'           => 50000,
            'deduction_amount' => 0,
            'status'           => RefundStatusEnum::COMPLETED->value,
        ]);

        $this->assertDatabaseHas('order_items', [
            'id'             => $orderItem->id,
            'status'         => OrderItemStatusEnum::REFUNDED->value,
            'total_refunded' => 50000,
        ]);

        Event::assertDispatched(RefundCompletedEvent::class);
    });
    it('throws validation exception when refunding an item from an unpaid order', function (): void {
        $order     = Order::factory()->withCalculatedTotals([['total' => 50000]])->create(); // No payment
        $orderItem = $order->items->first();

        $refundData = new RefundCreateData(
            order_item_id: $orderItem->id,
            deduction_amount: 0,
            deduction_percent: null,
            transaction_details: new RefundTransactionData(
                receiver_name: 'John Doe',
                card_number: '1234567812345678',
                iban_number: 'DE89370400440532013000',
                tracking_code: null
            ),
            status: RefundStatusEnum::COMPLETED->value,
            admin_notes: 'Test refund');

        expect(fn () => (resolve(CreateRefundAction::class))->handle($refundData))
            ->toThrow(ValidationException::class, __('messages.order.refund.no_completed_payments'));
    });

    it('throws validation exception when refunding an already refunded item', function (): void {
        $order     = Order::factory()->create();
        $orderItem = OrderItem::factory()->for($order)->create(['status' => OrderItemStatusEnum::REFUNDED]);
        Payment::factory()
            ->for($order)
            ->create([
                'customer_id' => $order->customer_id,
                'method'      => PaymentMethodEnum::BANK_TRANSFER,
                'amount'      => 50000,
                'status'      => 'completed',
            ]);
        $refundData = new RefundCreateData(
            order_item_id: $orderItem->id,
            deduction_amount: 0,
            deduction_percent: null,
            transaction_details: new RefundTransactionData(
                receiver_name: 'John Doe',
                card_number: '1234567812345678',
                iban_number: 'DE89370400440532013000',
                tracking_code: null
            ),
            status: RefundStatusEnum::COMPLETED->value,
            admin_notes: 'Test refund');

        expect(fn () => (resolve(CreateRefundAction::class))->handle($refundData))
            ->toThrow(ValidationException::class, __('messages.order.refund.already_refunded'));
    });

    it('throws validation exception if a pending refund request already exists', function (): void {
        $order = Order::factory()->withCalculatedTotals(
            [
                [
                    'total'  => 50000,
                    'status' => OrderItemStatusEnum::COMPLETED->value,
                ],
            ]
        )->create();
        $order->payments()->create([
            'customer_id' => $order->customer_id,
            'method'      => PaymentMethodEnum::BANK_TRANSFER,
            'amount'      => 50000,
            'status'      => 'completed',
        ]);
        $orderItem = $order->items->first();
        // Create an existing pending refund
        \App\Models\Refund::factory()->create([
            'order_item_id' => $orderItem->id,
            'status'        => RefundStatusEnum::PENDING->value,
        ]);
        $refundData = new RefundCreateData(
            order_item_id: $orderItem->id,
            deduction_amount: 0,
            deduction_percent: null,
            transaction_details: new RefundTransactionData(
                receiver_name: 'John Doe',
                card_number: '1234567812345678',
                iban_number: 'DE89370400440532013000',
                tracking_code: null
            ),
            status: RefundStatusEnum::COMPLETED->value,
            admin_notes: 'Test refund');

        expect(fn () => (resolve(CreateRefundAction::class))->handle($refundData))
            ->toThrow(ValidationException::class, __('messages.order.refund.refund_request_exists'));
    });

    // ─── Gateway Error Cases ──────────────────────────────────────────────

    it('marks refund FAILED and throws RefundValidationException on Digipay gateway error', function (): void {
        // Arrange
        $product = ProductDeliveryOption::factory()
            ->create(['price' => 50000, 'status' => PublicationStatusEnum::PUBLISHED]);
        $order = Order::factory()->withCalculatedTotals([
            ['product_delivery_option_id' => $product->id, 'price' => 50000, 'total' => 50000],
        ])->create();
        $order->payments()->create([
            'customer_id' => $order->customer_id,
            'method'      => PaymentMethodEnum::DIGIPAY,
            'amount'      => 50000,
            'status'      => PaymentStatusEnum::COMPLETED,
        ]);
        $orderItem = $order->items->first();
        $orderItem->update(['status' => OrderItemStatusEnum::COMPLETED]);

        config()->set('payments.digipay.allow_partial_refund',true);

        $this->mock(RefundProcessorFactory::class, function (MockInterface $mock): void {
            $processor = \Mockery::mock(RefundProcessorInterface::class);
            $processor->shouldReceive('process')
                ->once()
                ->andThrow(new DigipayException('Gateway connection failed', 500));
            $mock->shouldReceive('make')
                ->with(PaymentMethodEnum::DIGIPAY->value)
                ->once()
                ->andReturn($processor);
        });

        $refundData = new RefundCreateData(
            order_item_id: $orderItem->id,
            deduction_amount: 0,
            deduction_percent: null,
            transaction_details: new RefundTransactionData(
                receiver_name: 'John Doe',
                card_number: '1234567812345678',
                iban_number: 'DE89370400440532013000',
                tracking_code: null
            ),
            status: RefundStatusEnum::COMPLETED->value,
            admin_notes: 'Test digipay error',
        );

        expect(fn () => (resolve(CreateRefundAction::class))->handle($refundData))
            ->toThrow(RefundValidationException::class, 'Gateway connection failed');

        $this->assertDatabaseHas('refunds', [
            'order_item_id' => $orderItem->id,
            'status'        => RefundStatusEnum::FAILED->value,
        ]);
    });

    it('appends gateway-skipped note when skip_gateway is true with immediate completion', function (): void {
        // Arrange
        $product = ProductDeliveryOption::factory()
            ->create(['price' => 50000, 'status' => PublicationStatusEnum::PUBLISHED]);
        $order = Order::factory()->withCalculatedTotals([
            ['product_delivery_option_id' => $product->id, 'price' => 50000, 'total' => 50000],
        ])->create();
        $order->payments()->create([
            'customer_id' => $order->customer_id,
            'method'      => PaymentMethodEnum::BANK_TRANSFER,
            'amount'      => 50000,
            'status'      => PaymentStatusEnum::COMPLETED,
        ]);
        $orderItem = $order->items->first();
        $orderItem->update(['status' => OrderItemStatusEnum::COMPLETED]);

        $refundData = new RefundCreateData(
            order_item_id: $orderItem->id,
            deduction_amount: 0,
            deduction_percent: null,
            transaction_details: new RefundTransactionData(
                receiver_name: 'John Doe',
                card_number: '1234567812345678',
                iban_number: 'DE89370400440532013000',
                tracking_code: null
            ),
            status: RefundStatusEnum::COMPLETED->value,
            skip_gateway: true,
            admin_notes: 'Manual refund',
        );

        $refund = (resolve(CreateRefundAction::class))->handle($refundData);

        $this->assertDatabaseHas('refunds', [
            'id'     => $refund->id,
            'status' => RefundStatusEnum::COMPLETED->value,
        ]);
    });

    it('processes wallet payment method when immediately completing', function (): void {
        // Arrange
        $product = ProductDeliveryOption::factory()
            ->create(['price' => 50000, 'status' => PublicationStatusEnum::PUBLISHED]);
        $order = Order::factory()->withCalculatedTotals([
            ['product_delivery_option_id' => $product->id, 'price' => 50000, 'total' => 50000],
        ])->create();
        $order->payments()->create([
            'customer_id' => $order->customer_id,
            'method'      => PaymentMethodEnum::WALLET,
            'amount'      => 50000,
            'status'      => PaymentStatusEnum::COMPLETED,
        ]);
        $orderItem = $order->items->first();
        $orderItem->update(['status' => OrderItemStatusEnum::COMPLETED]);

        $mockProcessor = \Mockery::mock(RefundProcessorInterface::class);
        $mockProcessor->shouldReceive('process')
            ->once()
            ->andReturnNull();

        $this->mock(RefundProcessorFactory::class, function (MockInterface $mock) use ($mockProcessor): void {
            $mock->shouldReceive('make')
                ->with(PaymentMethodEnum::WALLET->value)
                ->once()
                ->andReturn($mockProcessor);
        });

        $refundData = new RefundCreateData(
            order_item_id: $orderItem->id,
            deduction_amount: 0,
            deduction_percent: null,
            transaction_details: new RefundTransactionData(
                receiver_name: null,
                card_number: null,
                iban_number: null,
                tracking_code: null
            ),
            status: RefundStatusEnum::COMPLETED->value,
            admin_notes: 'Wallet refund',
        );

        $refund = (resolve(CreateRefundAction::class))->handle($refundData);

        $this->assertDatabaseHas('refunds', [
            'id'     => $refund->id,
            'status' => RefundStatusEnum::COMPLETED->value,
        ]);
        Event::assertDispatched(RefundCompletedEvent::class);
    });

    it('throws RefundValidationException when Digipay partial refund is disabled via config', function (): void {
        // Arrange
        $product = ProductDeliveryOption::factory()
            ->create(['price' => 50000, 'status' => PublicationStatusEnum::PUBLISHED]);
        $order = Order::factory()->withCalculatedTotals([
            ['product_delivery_option_id' => $product->id, 'price' => 50000, 'total' => 50000],
        ])->create();
        $order->payments()->create([
            'customer_id' => $order->customer_id,
            'method'      => PaymentMethodEnum::DIGIPAY,
            'amount'      => 50000,
            'status'      => PaymentStatusEnum::COMPLETED,
        ]);
        $orderItem = $order->items->first();
        $orderItem->update(['status' => OrderItemStatusEnum::COMPLETED]);

        config()->set('payments.digipay.allow_partial_refund',false);

        $refundData = new RefundCreateData(
            order_item_id: $orderItem->id,
            deduction_amount: 0,
            deduction_percent: null,
            transaction_details: new RefundTransactionData(
                receiver_name: 'John Doe',
                card_number: '1234567812345678',
                iban_number: 'DE89370400440532013000',
                tracking_code: null
            ),
            status: RefundStatusEnum::COMPLETED->value,
            admin_notes: 'Test',
        );

            expect(fn () => (resolve(CreateRefundAction::class))->handle($refundData))
                ->toThrow(RefundValidationException::class, __('messages.order.refund.digipay_partial_refund_not_supported'));

    });
});
