<?php

use App\Actions\Order\CreateOrderAction;
use App\Data\Order\OrderCreateData;
use App\Data\Order\OrderItemCreateData;
use App\Enums\OrderItemPaymentTypeEnum;
use App\Enums\OrderPaymentStatusEnum;
use App\Enums\OrderStatusEnum;
use App\Events\OrderCreatedEvent;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductDeliveryOption;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

describe('CreateOrderAction', function () {

    beforeEach(function () {
        Event::fake();
    });

    // TEST 1: The main success case with full payment
    test('it creates an order successfully with full payment', function () {
        $user = User::factory()->create();
        $deliveryOption = ProductDeliveryOption::factory()->create([
            'price' => 100000, // Price per unit
            'prepayment_amount' => 20000,
        ]);

        // Use the correct DTO for items
        $items = [
            new OrderItemCreateData(
                product_delivery_option_id: $deliveryOption->id,
                payment_type: OrderItemPaymentTypeEnum::FULL_PAYMENT->value,
                discount_amount: 5000, // Discount per unit
                qty_ordered: 2,
                tax_amount: 1000, // Tax per unit
            ),
        ];

        $data = new OrderCreateData(
            status: OrderStatusEnum::PROCESSING->value,
            customer_id: $user->id,
            items: $items,
            applied_coupon_code: null,
            admin_notes: 'Test notes',
        );

        $order = (new CreateOrderAction())->handle($data);

        // --- Assertions for the Order ---
        expect($order)->toBeInstanceOf(Order::class);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'customer_id' => $user->id,
            'status' => OrderStatusEnum::PROCESSING->value,
            'total_item_count' => 1,
            'total_qty_ordered' => 2,
            'subtotal' => 200000, // 100,000 * 2
            'discount_amount' => 10000, // 5,000 * 2
            'tax_amount' => 2000, // 1,000 * 2
            'grand_total' => 192000, // (200000 - 10000 + 2000)
            'amount_paid' => 192000, // Paid in full
            'amount_refunded' => 0,
            'balance_due' => 0,
            'payment_status' => OrderPaymentStatusEnum::PAID->value,
            'admin_notes' => 'Test notes',
        ]);

        // --- Assertions for the Order Item ---
        expect($order->items)->toHaveCount(1);
        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'product_delivery_option_id' => $deliveryOption->id,
            'price' => 100000, // Snapshot of original price
            'total' => 200000, // price * quantity
            'qty_ordered' => 2,
            'discount_amount' => 5000,
            'tax_amount' => 1000,
            'payment_type' => OrderItemPaymentTypeEnum::FULL_PAYMENT->value,
        ]);

        Event::assertDispatched(OrderCreatedEvent::class);
    });

    // TEST 2: Success case with pre-payment
    test('it creates an order with a correct partial payment status when using pre-payment', function () {
        $user = User::factory()->create();
        $deliveryOption = ProductDeliveryOption::factory()->create([
            'price' => 100000,
            'prepayment_amount' => 25000, // Required pre-payment
        ]);

        $items = [
            new OrderItemCreateData(
                product_delivery_option_id: $deliveryOption->id,
                payment_type: OrderItemPaymentTypeEnum::PRE_PAYMENT->value,
                discount_amount: 0,
                qty_ordered: 1,
                tax_amount: 0,
            ),
        ];

        $data = new OrderCreateData(
            status: OrderStatusEnum::PENDING->value,
            customer_id: $user->id,
            items: $items,
            applied_coupon_code: null,
            admin_notes: null,
        );

        $order = (new CreateOrderAction())->handle($data);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'grand_total' => 100000,
            'amount_paid' => 25000, // Correct pre-payment amount was paid
            'balance_due' => 75000, // Correct remaining balance
            'payment_status' => OrderPaymentStatusEnum::PARTIALLY_PAID->value, // Correct status
        ]);

        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'payment_type' => OrderItemPaymentTypeEnum::PRE_PAYMENT->value,
            'prepayment_amount' => 25000, // The rule is snapshotted
        ]);
    });

    // TEST 3: Validation failure for trying pre-payment when not allowed
    test('it throws a validation exception if pre-payment is selected for a product that does not allow it', function () {
        $user = User::factory()->create();
        $deliveryOption = ProductDeliveryOption::factory()->create([
            'price' => 100000,
            'prepayment_amount' => null,
        ]);

        $items = [
            new OrderItemCreateData(
                product_delivery_option_id: $deliveryOption->id,
                payment_type: OrderItemPaymentTypeEnum::PRE_PAYMENT->value,
                discount_amount: 0,
                qty_ordered: 1,
                tax_amount: 0,
            ),
        ];

        $data = new OrderCreateData(
            status: OrderStatusEnum::PENDING->value,
            customer_id: $user->id,
            items: $items,
            applied_coupon_code: null,
            admin_notes: null,
        );

        expect(fn() => (new CreateOrderAction())->handle($data))
            ->toThrow(ValidationException::class);
    });

    // TEST 4: Validation for duplicate purchase (no major changes needed)
    test('it throws ValidationException if customer already purchased an item', function () {
        $user = User::factory()->create();
        $deliveryOption = ProductDeliveryOption::factory()->create();
        $order = Order::factory()->create(['customer_id' => $user->id]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_delivery_option_id' => $deliveryOption->id,
        ]);

        $items = [
            new OrderItemCreateData(
                product_delivery_option_id: $deliveryOption->id,
                payment_type: OrderItemPaymentTypeEnum::FULL_PAYMENT->value,
                discount_amount: 0,
                qty_ordered: 1,
                tax_amount: 0,
            ),
        ];

        $data = new OrderCreateData(
            status: OrderStatusEnum::PENDING->value,
            customer_id: $user->id,
            items: $items,
            applied_coupon_code: null,
            admin_notes: null,
        );

        expect(fn() => (new CreateOrderAction())->handle($data))
            ->toThrow(ValidationException::class);
    });

    test('it throws InvalidArgumentException if delivery option does not exist', function () {
        $user = User::factory()->create();

        $items = [
            new OrderItemCreateData(
                product_delivery_option_id: 99999, // Non-existent ID
                payment_type: OrderItemPaymentTypeEnum::FULL_PAYMENT->value,
                discount_amount: 0,
                qty_ordered: 1,
                tax_amount: 0,
            ),
        ];

        $data = new OrderCreateData(
            status: OrderStatusEnum::PENDING->value,
            customer_id: $user->id,
            items: $items,
            applied_coupon_code: null,
            admin_notes: null,
        );

        expect(fn() => (new CreateOrderAction())->handle($data))
            ->toThrow(\InvalidArgumentException::class);
    });
});
