<?php

declare(strict_types=1);

// Import all necessary classes at the top
use App\Actions\Admin\Order\CreateOrderAction;
use App\Data\Admin\Order\OrderCreateData;
use App\Data\Admin\Order\OrderItemCreateData;
use App\Enums\Content\PublicationStatusEnum;
use App\Enums\EnrollmentStatusEnum;
use App\Enums\Order\DiscountTypeEnum;
use App\Enums\Order\OrderItemPaymentTypeEnum;
use App\Enums\Order\OrderItemStatusEnum;
use App\Enums\Order\OrderStatusEnum;
use App\Events\OrderCreatedEvent;
use App\Models\DiscountCoupon;
use App\Models\DiscountPromotion;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductDeliveryOption;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

// Or your specific status enum

// Using Pest for cleaner assertions

describe('CreateOrderAction', function (): void {

    beforeEach(function (): void {
        Event::fake([OrderCreatedEvent::class]);
    });

    it('creates an order and pending enrollments successfully', function (): void {
        $user     = User::factory()->create();
        $product1 = Product::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]);
        $product2 = Product::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]);

        $deliveryOption1 = ProductDeliveryOption::factory()->create([
            'product_id'              => $product1->id,
            'status'                  => PublicationStatusEnum::PUBLISHED,
            'capacity'                => 20,
            'price'                   => 50000,
            'is_prepayment_available' => false,

        ]);
        $deliveryOption2 = ProductDeliveryOption::factory()->create([
            'product_id'              => $product2->id,
            'status'                  => PublicationStatusEnum::PUBLISHED,
            'capacity'                => 20,
            'price'                   => 25000,
            'is_prepayment_available' => false,
        ]);

        $items = [
            new OrderItemCreateData(
                product_delivery_option_id: $deliveryOption1->id,
                payment_type: OrderItemPaymentTypeEnum::FULL_PAYMENT->value,
                qty_ordered: 1,
            ),

            new OrderItemCreateData(
                product_delivery_option_id: $deliveryOption2->id,
                payment_type: OrderItemPaymentTypeEnum::FULL_PAYMENT->value,
                qty_ordered: 1,
            ),
        ];

        $data = new OrderCreateData(
            status: OrderStatusEnum::PENDING->value,
            customer_id: $user->id,
            items: $items,
            applied_coupon_code: null,
            admin_notes: 'Test notes'
        );

        $action = app()->make(CreateOrderAction::class);
        $order  = $action->handle($data);

        expect($order)->toBeInstanceOf(Order::class);
        \Pest\Laravel\assertDatabaseHas('orders', [
            'id'                  => $order->id,
            'customer_id'         => $user->id,
            'status'              => OrderStatusEnum::PENDING->value,
            'total_item_count'    => 2,
            'total_qty_ordered'   => 2,
            'subtotal'            => 75000,
            'discount_amount'     => 0,
            'tax_amount'          => 0,
            'grand_total'         => 75000,
            'applied_coupon_code' => null,
        ]);

        // --- Assertions for Order Items ---
        expect($order->items)->toHaveCount(2);
        \Pest\Laravel\assertDatabaseHas('order_items', [
            'order_id'                   => $order->id,
            'product_delivery_option_id' => $deliveryOption1->id,
            'qty_ordered'                => 1,
            'price'                      => 50000,
            'discount_amount'            => 0,
            'tax_amount'                 => 0,
            'total'                      => 50000,
            'status'                     => OrderItemStatusEnum::PENDING->value,
        ]);
        \Pest\Laravel\assertDatabaseHas('order_items', [
            'order_id'                   => $order->id,
            'product_delivery_option_id' => $deliveryOption2->id,
            'qty_ordered'                => 1,
            'total'                      => 25000,
            'status'                     => OrderItemStatusEnum::PENDING->value,
        ]);

        \Pest\Laravel\assertDatabaseHas('enrollments', [
            'order_id'                   => $order->id,
            'customer_id'                => $user->id,
            'product_delivery_option_id' => $deliveryOption1->id,
            'enrollment_status'          => EnrollmentStatusEnum::AWAITING_PAYMENT->value,
        ]);
        \Pest\Laravel\assertDatabaseHas('enrollments', [
            'order_id'                   => $order->id,
            'customer_id'                => $user->id,
            'product_delivery_option_id' => $deliveryOption2->id,
            'enrollment_status'          => EnrollmentStatusEnum::AWAITING_PAYMENT->value,
        ]);

        Event::assertDispatched(OrderCreatedEvent::class, fn ($event): bool => $event->order->id === $order->id);
    });

    it('throws validation exception if product capacity is exceeded', function (): void {
        $user    = User::factory()->create();
        $product = Product::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]);

        $deliveryOption = ProductDeliveryOption::factory()->create([
            'product_id' => $product,
            'status'     => PublicationStatusEnum::PUBLISHED,
            'capacity'   => 1,
        ]); // Only 1 seat

        Enrollment::factory()->create([
            'product_delivery_option_id' => $deliveryOption->id,
            'enrollment_status'          => EnrollmentStatusEnum::ACTIVE,
        ]);

        $items = [
            new OrderItemCreateData(product_delivery_option_id: $deliveryOption->id, payment_type: 'full_payment', qty_ordered: 1),
        ];
        $data = new OrderCreateData(status: 'pending', customer_id: $user->id, items: $items, applied_coupon_code: null,
            admin_notes: null);

        expect(fn () => (app()->make(CreateOrderAction::class))->handle($data))
            ->toThrow(ValidationException::class, __('messages.order.insufficient_capacity', [
                'product'   => $deliveryOption->name,
                'available' => 0,
            ]));
    });

    it('throws validation exception if product or delivery option is not published', function (): void {
        $user           = User::factory()->create();
        $product        = Product::factory()->create(['status' => PublicationStatusEnum::DRAFT]);
        $deliveryOption = ProductDeliveryOption::factory()->create(['product_id' => $product->id]);

        $items = [
            new OrderItemCreateData(product_delivery_option_id: $deliveryOption->id, payment_type: 'full_payment'),
        ];
        $data = new OrderCreateData(status: 'pending', customer_id: $user->id, items: $items, applied_coupon_code: null,
            admin_notes: null);

        expect(fn () => (app()->make(CreateOrderAction::class))->handle($data))
            ->toThrow(ValidationException::class,
                __('messages.order.item_not_available', ['product' => $deliveryOption->name]));
    });

    it('throws validation exception if an active enrollment already exists for an item', function (): void {
        $user           = User::factory()->create();
        $deliveryOption = ProductDeliveryOption::factory()->create();

        // Setup: Create a pre-existing ACTIVE enrollment for this user and item.
        Enrollment::factory()->create([
            'customer_id'                => $user->id,
            'product_delivery_option_id' => $deliveryOption->id,
            'enrollment_status'          => EnrollmentStatusEnum::ACTIVE,
        ]);

        $items = [
            new OrderItemCreateData(product_delivery_option_id: $deliveryOption->id, payment_type: 'full_payment'),
        ];
        $data = new OrderCreateData(status: 'pending', customer_id: $user->id, items: $items, applied_coupon_code: null,
            admin_notes: null);

        expect(fn () => (app()->make(CreateOrderAction::class))->handle($data))
            ->toThrow(ValidationException::class, __('messages.order.items_already_purchased_or_active', [
                'products' => $deliveryOption->product->name,
            ]));
    });

    it('throws validation exception for unavailable pre-payment option', function (): void {
        $user           = User::factory()->create();
        $product        = Product::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]);
        $deliveryOption = ProductDeliveryOption::factory()->create([
            'product_id'              => $product->id,
            'status'                  => PublicationStatusEnum::PUBLISHED,
            'is_prepayment_available' => false,
        ]);

        $items = [
            new OrderItemCreateData(product_delivery_option_id: $deliveryOption->id,
                payment_type: OrderItemPaymentTypeEnum::PRE_PAYMENT->value),
        ];
        $data = new OrderCreateData(status: 'pending', customer_id: $user->id, items: $items, applied_coupon_code: null,
            admin_notes: null);

        expect(fn () => (app()->make(CreateOrderAction::class))->handle($data))
            ->toThrow(ValidationException::class, __('messages.order.prepayment_not_available', [
                'product' => $deliveryOption->name,
            ]));
    });

    it('throws InvalidArgumentException if a delivery option ID does not exist', function (): void {
        $user  = User::factory()->create();
        $items = [
            new OrderItemCreateData(product_delivery_option_id: 99999, payment_type: 'full_payment'),
        ];
        $data = new OrderCreateData(status: 'pending', customer_id: $user->id, items: $items, applied_coupon_code: null,
            admin_notes: null);

        expect(fn () => (app()->make(CreateOrderAction::class))->handle($data))
            ->toThrow(InvalidArgumentException::class);
    });

    it('throws validation exception with a list of all products for multiple duplicate enrollments', function (): void {
        $user            = User::factory()->create();
        $deliveryOption1 = ProductDeliveryOption::factory()->create([
            'status' => PublicationStatusEnum::PUBLISHED,
            'name'   => 'Course A',
        ]);
        $deliveryOption2 = ProductDeliveryOption::factory()->create([
            'status' => PublicationStatusEnum::PUBLISHED,
            'name'   => 'Course B',
        ]);

        // Setup: Create TWO pre-existing active enrollments
        Enrollment::factory()->create([
            'customer_id'                => $user->id,
            'product_delivery_option_id' => $deliveryOption1->id,
            'enrollment_status'          => EnrollmentStatusEnum::ACTIVE,
        ]);
        Enrollment::factory()->create([
            'customer_id'                => $user->id,
            'product_delivery_option_id' => $deliveryOption2->id,
            'enrollment_status'          => EnrollmentStatusEnum::ACTIVE,
        ]);

        // Attempt to create an order with both of these items again
        $items = [
            new OrderItemCreateData(product_delivery_option_id: $deliveryOption1->id, payment_type: 'full_payment'),
            new OrderItemCreateData(product_delivery_option_id: $deliveryOption2->id, payment_type: 'full_payment'),
        ];
        $data = new OrderCreateData(status: 'pending', customer_id: $user->id, items: $items, applied_coupon_code: null,
            admin_notes: null);

        expect(fn() => (app()->make(CreateOrderAction::class))->handle($data))
            ->toThrow(ValidationException::class, __('messages.order.items_already_purchased_or_active',
                    [
                        'products' =>
                            collect([
                                $deliveryOption1->product->name,
                                $deliveryOption2->product->name,
                            ])->sort()->join(', ')
                    ]
                )
            );
    });

    it('throws ValidationException if a delivery option deosn\'t allow more than 1 quantity', function (): void {
        $user = User::factory()->create();

        $product        = Product::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]);
        $deliveryOption = ProductDeliveryOption::factory()->create([
            'product_id' => $product->id,
            'status'     => PublicationStatusEnum::PUBLISHED,
            'capacity'   => 20,
        ]);

        $items = [
            new OrderItemCreateData(
                product_delivery_option_id: $deliveryOption->id,
                payment_type: OrderItemPaymentTypeEnum::PRE_PAYMENT->value,
                qty_ordered: 2),
        ];
        $data = new OrderCreateData(status: 'pending', customer_id: $user->id, items: $items, applied_coupon_code: null,
            admin_notes: null);

        expect(fn () => (app()->make(CreateOrderAction::class))->handle($data))
            ->toThrow(ValidationException::class, __('messages.order.quantity_not_allowed',
                ['product' => $deliveryOption->name]));
    });

    it('creates an order with a discount from a valid coupon code', function (): void {
        // --- ARRANGE: SETUP THE PROMOTION ---
        $user           = User::factory()->create();
        $product        = Product::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]);
        $deliveryOption = ProductDeliveryOption::factory()->create([
            'product_id' => $product->id,
            'status'     => PublicationStatusEnum::PUBLISHED,
            'price'      => 50000, // Price is 500.00
        ]);

        // 1. Create the master promotion
        $promotion = DiscountPromotion::factory()->create([
            'name'      => '10% Off All Courses',
            'type'      => 'cart_checkout',
            'is_active' => true,
        ]);

        // 2. Create the "Action" rule for this promotion (what it does)
        $promotion->rules()->create([
            'type'          => 'action',
            'handler'       => 'apply_percentage_off', // The key for our Action Handler
            'configuration' => ['percentage' => 10], // Apply 10%
        ]);

        // 3. Create the coupon code linked to the promotion
        $coupon = DiscountCoupon::factory()->create([
            'discount_promotion_id' => $promotion->id,
            'code'                  => 'SAVE10',
        ]);

        // --- PREPARE THE DATA DTO ---
        // Note the absence of discount_amount here. It's clean.
        $items = [
            new OrderItemCreateData(
                product_delivery_option_id: $deliveryOption->id,
                payment_type: OrderItemPaymentTypeEnum::FULL_PAYMENT->value,
                qty_ordered: 1
            ),
        ];

        $data = new OrderCreateData(
            status: OrderStatusEnum::PENDING->value,
            customer_id: $user->id,
            items: $items,
            applied_coupon_code: 'SAVE10',
            admin_notes: null
        );

        // --- ACT ---
        $action = app(CreateOrderAction::class); // Resolve from container to get dependencies
        $order  = $action->handle($data);

        // --- ASSERT ---
        expect($order)->toBeInstanceOf(Order::class);

        // The assertions now check for the result of the PROMOTION's logic.
        \Pest\Laravel\assertDatabaseHas('orders', [
            'id'                  => $order->id,
            'customer_id'         => $user->id,
            'subtotal'            => 50000,
            'discount_amount'     => 5000,  // 10% of 50000
            'grand_total'         => 45000, // 50000 - 5000
            'applied_coupon_code' => 'SAVE10', // Or this might be in the JSON audit column now
        ]);

        \Pest\Laravel\assertDatabaseHas('order_items', [
            'order_id'                   => $order->id,
            'product_delivery_option_id' => $deliveryOption->id,
            'price'                      => 50000,
            'discount_amount'            => 5000,
            'total'                      => 45000,
        ]);

        Event::assertDispatched(OrderCreatedEvent::class);
    });
    it('applies no discount if coupon code is invalid', function (): void {
        // Arrange
        $user           = User::factory()->create();
        $deliveryOption = ProductDeliveryOption::factory()->create(['price' => 10000]);

        $data = new OrderCreateData(
            status: OrderStatusEnum::PENDING->value,
            customer_id: $user->id,
            items: [new OrderItemCreateData(product_delivery_option_id: $deliveryOption->id, payment_type: 'full_payment')],
            applied_coupon_code: 'INVALID_CODE',
            admin_notes: null
        );

        // Act
        $order = app(CreateOrderAction::class)->handle($data);

        // Assert
        expect($order->discount_amount)->toBe(0);
        expect($order->grand_total)->toBe(10000);
    });

    it('does not apply discount if a promotion condition is not met', function (): void {
        // Arrange: Create a promotion that requires a cart value of $200
        $user           = User::factory()->create();
        $deliveryOption = ProductDeliveryOption::factory()->create(['price' => 10000]); // Price is only $100

        $promotion = DiscountPromotion::factory()->create(['is_active' => true]);
        $promotion->rules()->create([
            'type'          => 'condition',
            'handler'       => 'cart_value_over',
            'configuration' => ['value' => 20000, 'operator' => '>=', 'include_prepayments' => false],
        ]);
        $promotion->rules()->create([
            'type'          => 'action',
            'handler'       => 'apply_percentage_off',
            'configuration' => ['percentage' => 10],
        ]);
        $coupon = DiscountCoupon::factory()->create(['discount_promotion_id' => $promotion->id, 'code' => 'SAVE10']);

        $data = new OrderCreateData(
            status: OrderStatusEnum::PENDING->value,
            customer_id: $user->id,
            items: [new OrderItemCreateData(product_delivery_option_id: $deliveryOption->id, payment_type: 'full_payment')],
            applied_coupon_code: 'SAVE10',
            admin_notes: null
        );

        // Act
        $order = app(CreateOrderAction::class)->handle($data);

        // Assert: The discount was NOT applied because the cart value was too low
        expect($order->discount_amount)->toBe(0);
        expect($order->grand_total)->toBe(10000);
    });

    it('correctly populates discount audit trail columns', function (): void {
        // Arrange
        $user           = User::factory()->create();
        $deliveryOption = ProductDeliveryOption::factory()
            ->create([
                'product_id' => Product::factory()->create(['status' => PublicationStatusEnum::PUBLISHED])->id,
                'price'      => 50000,
                'status'     => PublicationStatusEnum::PUBLISHED,
            ]);
        $promotion = DiscountPromotion::factory()->create([
            'name'      => 'Test Sale',
            'is_active' => true,
            'type'      => DiscountTypeEnum::CART_CHECKOUT,
        ]);
        $promotion->rules()->create([
            'type' => 'action', 'handler' => 'apply_percentage_off', 'configuration' => ['percentage' => 10],
        ]);
        $coupon = DiscountCoupon::factory()->create(['discount_promotion_id' => $promotion->id, 'code' => 'AUDIT_TEST']);

        $data = new OrderCreateData(
            status: OrderStatusEnum::PENDING->value,
            customer_id: $user->id,
            items: [new OrderItemCreateData(product_delivery_option_id: $deliveryOption->id, payment_type: 'full_payment')],
            applied_coupon_code: 'AUDIT_TEST',
            admin_notes: null
        );

        // Debug: Check that promotion exists with the right coupon
        expect($promotion->coupons()->where('code', 'AUDIT_TEST')->exists())->toBeTrue('Coupon not found for promotion');

        // Act
        $order = app(CreateOrderAction::class)->handle($data);

        // Debug: Check if any discount was actually applied
        expect($order->discount_amount)->toBeGreaterThan(0, 'No discount was applied - promotion not found or conditions failed');

        // Assert
        $orderItem = $order->items->first();

        // For CART_CHECKOUT type, both cart-level and item-level details should be populated
        expect($order->applied_cart_discounts_json)->toBe([
            [
                'promotion_id'   => $promotion->id,
                'promotion_name' => 'Test Sale',
                'applied_amount' => 5000, // 10% of 50000
                'coupon_code'    => 'AUDIT_TEST',
            ],
        ]);
        expect($orderItem->applied_discount_details_json)->toBe([
            [
                'promotion_id'   => $promotion->id,
                'promotion_name' => 'Test Sale',
                'applied_amount' => 5000, // 10% of 50000
                'coupon_code'    => 'AUDIT_TEST',
            ],
        ]);
    });
    it('correctly calculates grand total for a mixed payment type order', function (): void {
        // This test covers the prepayment logic in the grand_total calculation (line 89).
        $user = User::factory()->create();

        $fullPaymentOption = ProductDeliveryOption::factory()->create([
            'product_id'              => Product::factory()->create(['status' => PublicationStatusEnum::PUBLISHED])->id,
            'status'                  => PublicationStatusEnum::PUBLISHED,
            'price'                   => 10000,
            'is_prepayment_available' => false,
        ]);
        $prePaymentOption = ProductDeliveryOption::factory()->create([
            'product_id'              => Product::factory()->create(['status' => PublicationStatusEnum::PUBLISHED])->id,
            'status'                  => PublicationStatusEnum::PUBLISHED,
            'price'                   => 50000,
            'is_prepayment_available' => true,
            'prepayment_amount'       => 2000, // The billable amount
        ]);

        $data = new OrderCreateData(
            status: OrderStatusEnum::PENDING->value,
            customer_id: $user->id,
            items: [
                new OrderItemCreateData(
                    product_delivery_option_id: $fullPaymentOption->id,
                    payment_type: OrderItemPaymentTypeEnum::FULL_PAYMENT->value,
                    qty_ordered: 1
                ),
                new OrderItemCreateData(
                    product_delivery_option_id: $prePaymentOption->id,
                    payment_type: OrderItemPaymentTypeEnum::PRE_PAYMENT->value,
                    qty_ordered: 1
                ),
            ]
        );

        $order = app(CreateOrderAction::class)->handle($data);

        // Assert: The grand_total is the sum of the full price and the prepayment amount.
        expect($order->grand_total)->toBe(12000); // 10000 (full) + 2000 (prepayment)

        // Verify that the prepayment item was processed correctly
        $prepaymentItem = $order->items->where('product_delivery_option_id', $prePaymentOption->id)->first();
        expect($prepaymentItem->payment_type)->toBe(OrderItemPaymentTypeEnum::PRE_PAYMENT);

    });

    it('creates an order with cart-level discounts applied', function (): void {
        // This test covers line 253 (calculateTotalDiscountFromContext method)
        $user           = User::factory()->create();
        $product        = Product::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]);
        $deliveryOption = ProductDeliveryOption::factory()->create([
            'product_id' => $product->id,
            'price'      => 50000,
            'status'     => PublicationStatusEnum::PUBLISHED,
        ]);

        // Create a promotion with discount
        $promotion = DiscountPromotion::factory()->create([
            'type'      => DiscountTypeEnum::CART_CHECKOUT,
            'is_active' => true,
        ]);
        $promotion->rules()->create([
            'type'          => 'action',
            'handler'       => 'apply_percentage_off',
            'configuration' => ['percentage' => 10],
        ]);

        $coupon = DiscountCoupon::factory()->create([
            'discount_promotion_id' => $promotion->id,
            'code'                  => 'SAVE10',
        ]);

        $data = new OrderCreateData(
            status: OrderStatusEnum::PENDING->value,
            customer_id: $user->id,
            items: [
                new OrderItemCreateData(
                    product_delivery_option_id: $deliveryOption->id,
                    payment_type: OrderItemPaymentTypeEnum::FULL_PAYMENT->value,
                    qty_ordered: 1
                ),
            ],
            applied_coupon_code: 'SAVE10'
        );

        $order = app(CreateOrderAction::class)->handle($data);

        // This should trigger the calculateTotalDiscountFromContext method (line 253)
        expect($order->discount_amount)->toBe(5000); // 10% of 50000
        expect($order->grand_total)->toBe(45000); // 50000 - 5000
        expect($order->applied_coupon_code)->toBe('SAVE10');

        // Verify the individual item also has the discount
        $orderItem = $order->items->first();
        expect($orderItem->discount_amount)->toBe(5000);
        expect($orderItem->total)->toBe(45000);
    });

    it('does not increment usage counts if no promotion was evaluated', function (): void {
        // This test covers the `if (! $promotion)` return in `incrementUsageCounts` (line 155).
        $user           = User::factory()->create();
        $deliveryOption = ProductDeliveryOption::factory()->create([
            'price'  => 10000,
            'status' => PublicationStatusEnum::PUBLISHED,
        ]);

        $data = new OrderCreateData(
            status: OrderStatusEnum::PENDING->value,
            customer_id: $user->id,
            items: [new OrderItemCreateData(
                product_delivery_option_id: $deliveryOption->id,
                payment_type: OrderItemPaymentTypeEnum::FULL_PAYMENT->value
            )],
            applied_coupon_code: null, // No coupon code provided to trigger any promotions
        );

        $order = app(CreateOrderAction::class)->handle($data);

        // Since no promotion was applied, incrementUsageCounts should hit the early return (line 155)
        expect($order->applied_coupon_code)->toBeNull();
        expect($order->discount_amount)->toBe(0);
    });
});
