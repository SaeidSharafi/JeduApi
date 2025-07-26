<?php

declare(strict_types=1);

// Import all necessary classes at the top
use App\Actions\Admin\Order\CreateOrderAction;
use App\Data\Admin\Order\OrderCreateData;
use App\Data\Admin\Order\OrderItemCreateData;
use App\Enums\EnrolmentStatusEnum;
use App\Enums\Order\OrderItemPaymentTypeEnum;
use App\Enums\Order\OrderItemStatusEnum;
use App\Enums\Order\OrderStatusEnum;
use App\Enums\PublicationStatusEnum;

// Or your specific status enum
use App\Events\OrderCreatedEvent;
use App\Models\Enrolment;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductDeliveryOption;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

// Using Pest for cleaner assertions

describe('CreateOrderAction', function () {

    beforeEach(function () {
        Event::fake([OrderCreatedEvent::class]);
    });

    it('creates an order and pending enrollments successfully', function () {
        $user = User::factory()->create();
        $product1 = Product::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]);
        $product2 = Product::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]);

        $deliveryOption1 = ProductDeliveryOption::factory()->create([
            'product_id' => $product1->id,
            'status'     => PublicationStatusEnum::PUBLISHED,
            'capacity'   => 20,
            'price'      => 50000
        ]);
        $deliveryOption2 = ProductDeliveryOption::factory()->create([
            'product_id' => $product2->id,
            'status'     => PublicationStatusEnum::PUBLISHED,
            'capacity'   => 20,
            'price'      => 25000
        ]);

        $items = [
            new OrderItemCreateData(
                product_delivery_option_id: $deliveryOption1->id,
                payment_type: OrderItemPaymentTypeEnum::FULL_PAYMENT->value,
                discount_amount: 5000,
                qty_ordered: 1,
                tax_amount: 1000
            ),

            new OrderItemCreateData(
                product_delivery_option_id: $deliveryOption2->id,
                payment_type: OrderItemPaymentTypeEnum::FULL_PAYMENT->value,
                discount_amount: 0,
                qty_ordered: 1,
                tax_amount: 0
            ),
        ];

        $data = new OrderCreateData(
            status: OrderStatusEnum::PENDING->value,
            customer_id: $user->id,
            items: $items,
            applied_coupon_code: 'TEST2025',
            admin_notes: 'Test notes'
        );

        $action = new CreateOrderAction();
        $order = $action->handle($data);

        expect($order)->toBeInstanceOf(Order::class);
        \Pest\Laravel\assertDatabaseHas('orders', [
            'id'                  => $order->id,
            'customer_id'         => $user->id,
            'status'              => OrderStatusEnum::PENDING->value,
            'total_item_count'    => 2,
            'total_qty_ordered'   => 2,
            'subtotal'            => 75000, // (50000*2) + 25000
            'discount_amount'     => 5000, // (5000*2) + 0
            'tax_amount'          => 1000, // (1000*2) + 0
            'grand_total'         => 71000, // 92000 + 25000
            'applied_coupon_code' => 'TEST2025',
        ]);

        // --- Assertions for Order Items ---
        expect($order->items)->toHaveCount(2);
        \Pest\Laravel\assertDatabaseHas('order_items', [
            'order_id'                   => $order->id,
            'product_delivery_option_id' => $deliveryOption1->id,
            'qty_ordered'                => 1,
            'price'                      => 50000,
            'discount_amount'            => 5000,
            'tax_amount'                 => 1000,
            'total'                      => 46000,
            'status'                     => OrderItemStatusEnum::PENDING->value,
        ]);
        \Pest\Laravel\assertDatabaseHas('order_items', [
            'order_id'                   => $order->id,
            'product_delivery_option_id' => $deliveryOption2->id,
            'qty_ordered'                => 1,
            'total'                      => 25000,
            'status'                     => OrderItemStatusEnum::PENDING->value,
        ]);

        \Pest\Laravel\assertDatabaseHas('enrolments', [
            'order_id'                   => $order->id,
            'customer_id'                => $user->id,
            'product_delivery_option_id' => $deliveryOption1->id,
            'enrollment_status'          => EnrolmentStatusEnum::PENDING_PROVISIONING->value,
        ]);
        \Pest\Laravel\assertDatabaseHas('enrolments', [
            'order_id'                   => $order->id,
            'customer_id'                => $user->id,
            'product_delivery_option_id' => $deliveryOption2->id,
            'enrollment_status'          => EnrolmentStatusEnum::PENDING_PROVISIONING->value,
        ]);

        Event::assertDispatched(OrderCreatedEvent::class, fn($event) => $event->order->id === $order->id);
    });

    it('throws validation exception if product capacity is exceeded', function () {
        $user = User::factory()->create();
        $product = Product::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]);

        $deliveryOption = ProductDeliveryOption::factory()->create([
            'product_id' => $product,
            'status'     => PublicationStatusEnum::PUBLISHED,
            'capacity'   => 1
        ]); // Only 1 seat

        Enrolment::factory()->create([
            'product_delivery_option_id' => $deliveryOption->id,
            'enrollment_status'          => EnrolmentStatusEnum::ACTIVE,
        ]);

        $items = [
            new OrderItemCreateData(product_delivery_option_id: $deliveryOption->id, payment_type: 'full_payment',
                discount_amount: 0, qty_ordered: 1)
        ];
        $data = new OrderCreateData(status: 'pending', customer_id: $user->id, items: $items, applied_coupon_code: null,
            admin_notes: null);

        expect(fn() => (new CreateOrderAction())->handle($data))
            ->toThrow(ValidationException::class, __('messages.order.insufficient_capacity', [
                'product'   => $deliveryOption->name,
                'available' => 0,
            ]));
    });

    it('throws validation exception if discount is greater than item price', function () {
        $user = User::factory()->create();
        $product = Product::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]);
        $deliveryOption = ProductDeliveryOption::factory()->create([
            'product_id' => $product,
            'status'     => PublicationStatusEnum::PUBLISHED,
            'price'      => 10000,
            'capacity'   => 10
        ]);

        $items = [
            new OrderItemCreateData(
                product_delivery_option_id: $deliveryOption->id,
                payment_type: 'full_payment',
                discount_amount: 11000,
                qty_ordered: 1
            )
        ];
        $data = new OrderCreateData(status: 'pending', customer_id: $user->id, items: $items, applied_coupon_code: null,
            admin_notes: null);

        expect(fn() => (new CreateOrderAction())->handle($data))
            ->toThrow(ValidationException::class,
                __('messages.order.discount_exceeds_price', ['product' => $deliveryOption->name]));
    });

    it('throws validation exception if product or delivery option is not published', function () {
        $user = User::factory()->create();
        $product = Product::factory()->create(['status' => PublicationStatusEnum::DRAFT]);
        $deliveryOption = ProductDeliveryOption::factory()->create(['product_id' => $product->id]);

        $items = [
            new OrderItemCreateData(product_delivery_option_id: $deliveryOption->id, payment_type: 'full_payment',
                discount_amount: 0)
        ];
        $data = new OrderCreateData(status: 'pending', customer_id: $user->id, items: $items, applied_coupon_code: null,
            admin_notes: null);

        expect(fn() => (new CreateOrderAction())->handle($data))
            ->toThrow(ValidationException::class,
                __('messages.order.item_not_available', ['product' => $deliveryOption->name]));
    });

    it('throws validation exception if an active enrollment already exists for an item', function () {
        $user = User::factory()->create();
        $deliveryOption = ProductDeliveryOption::factory()->create();

        // Setup: Create a pre-existing ACTIVE enrollment for this user and item.
        Enrolment::factory()->create([
            'customer_id'                => $user->id,
            'product_delivery_option_id' => $deliveryOption->id,
            'enrollment_status'          => EnrolmentStatusEnum::ACTIVE,
        ]);

        $items = [
            new OrderItemCreateData(product_delivery_option_id: $deliveryOption->id, payment_type: 'full_payment',
                discount_amount: 0)
        ];
        $data = new OrderCreateData(status: 'pending', customer_id: $user->id, items: $items, applied_coupon_code: null,
            admin_notes: null);

        expect(fn() => (new CreateOrderAction())->handle($data))
            ->toThrow(ValidationException::class, __('messages.order.items_already_purchased_or_active', [
                'products' => $deliveryOption->name,
            ]));
    });

    it('throws validation exception for unavailable pre-payment option', function () {
        $user = User::factory()->create();
        $product = Product::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]);
        $deliveryOption = ProductDeliveryOption::factory()->create([
            'product_id'              => $product->id,
            'status'                  => PublicationStatusEnum::PUBLISHED,
            'is_prepayment_available' => false,
        ]);

        $items = [
            new OrderItemCreateData(product_delivery_option_id: $deliveryOption->id,
                payment_type: OrderItemPaymentTypeEnum::PRE_PAYMENT->value, discount_amount: 0)
        ];
        $data = new OrderCreateData(status: 'pending', customer_id: $user->id, items: $items, applied_coupon_code: null,
            admin_notes: null);

        expect(fn() => (new CreateOrderAction())->handle($data))
            ->toThrow(ValidationException::class, __('messages.order.prepayment_not_available', [
                'product' => $deliveryOption->name,
            ]));
    });

    it('throws InvalidArgumentException if a delivery option ID does not exist', function () {
        $user = User::factory()->create();
        $items = [
            new OrderItemCreateData(product_delivery_option_id: 99999, payment_type: 'full_payment', discount_amount: 0)
        ];
        $data = new OrderCreateData(status: 'pending', customer_id: $user->id, items: $items, applied_coupon_code: null,
            admin_notes: null);

        expect(fn() => (new CreateOrderAction())->handle($data))
            ->toThrow(InvalidArgumentException::class);
    });

    it('throws validation exception with a list of all products for multiple duplicate enrollments', function () {
        $user = User::factory()->create();
        $deliveryOption1 = ProductDeliveryOption::factory()->create([
            'status' => PublicationStatusEnum::PUBLISHED,
            'name'   => 'Course A'
        ]);
        $deliveryOption2 = ProductDeliveryOption::factory()->create([
            'status' => PublicationStatusEnum::PUBLISHED,
            'name'   => 'Course B'
        ]);

        // Setup: Create TWO pre-existing active enrollments
        Enrolment::factory()->create([
            'customer_id'                => $user->id,
            'product_delivery_option_id' => $deliveryOption1->id,
            'enrollment_status'          => EnrolmentStatusEnum::ACTIVE,
        ]);
        Enrolment::factory()->create([
            'customer_id'                => $user->id,
            'product_delivery_option_id' => $deliveryOption2->id,
            'enrollment_status'          => EnrolmentStatusEnum::ACTIVE,
        ]);

        // Attempt to create an order with both of these items again
        $items = [
            new OrderItemCreateData(product_delivery_option_id: $deliveryOption1->id, payment_type: 'full_payment',
                discount_amount: 0),
            new OrderItemCreateData(product_delivery_option_id: $deliveryOption2->id, payment_type: 'full_payment',
                discount_amount: 0),
        ];
        $data = new OrderCreateData(status: 'pending', customer_id: $user->id, items: $items, applied_coupon_code: null,
            admin_notes: null);

        // Assert that the exception message contains both product names
        expect(fn() => (new CreateOrderAction())->handle($data))
            ->toThrow(ValidationException::class, 'Course A, Course B');
    });

    it('throws ValidationException if a delivery option deosn\'t allow more than 1 quantity', function () {
        $user = User::factory()->create();

        $product = Product::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]);
        $deliveryOption = ProductDeliveryOption::factory()->create([
            'product_id' => $product->id,
            'status'     => PublicationStatusEnum::PUBLISHED,
        ]);

        $items = [
            new OrderItemCreateData(
                product_delivery_option_id: $deliveryOption->id,
                payment_type: OrderItemPaymentTypeEnum::PRE_PAYMENT->value,
                discount_amount: 0,
                qty_ordered: 2)
        ];
        $data = new OrderCreateData(status: 'pending', customer_id: $user->id, items: $items, applied_coupon_code: null,
            admin_notes: null);

        expect(fn() => (new CreateOrderAction())->handle($data))
            ->toThrow(ValidationException::class, __('messages.order.quantity_not_allowed',
                ['product' => $deliveryOption->name]));
    });

});
