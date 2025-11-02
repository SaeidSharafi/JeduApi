<?php

declare(strict_types=1);

use App\Enums\Content\PublicationStatusEnum;
use App\Enums\Order\OrderStatusEnum;
use App\Enums\System\MorphTypeEnum;
use App\Models\Course;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductDeliveryOption;
use App\Models\Term;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Support\Str;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\postJson;

beforeEach(function (): void {
    // Create required dependencies
    $vendor = Vendor::factory()->create();
    $term   = Term::factory()->create();
    $course = Course::factory()->create([
        'status' => PublicationStatusEnum::PUBLISHED,
    ]);

    $product = Product::factory()->create([
        'vendor_id'        => $vendor->id,
        'term_id'          => $term->id,
        'productable_id'   => $course->id,
        'productable_type' => MorphTypeEnum::COURSE->value,
        'status'           => PublicationStatusEnum::PUBLISHED,
        'is_visible'       => true,
        'name'             => 'Introduction to Python',
        'short_name'       => 'Python 101',
    ]);

    // Create delivery option with capacity
    $this->deliveryOption = ProductDeliveryOption::factory()->create([
        'product_id' => $product->id,
        'price'      => 500000, // 500,000 IRR
        'uuid'       => Str::uuid()->toString(),
        'capacity'   => 5, // Limited capacity
        'status'     => PublicationStatusEnum::PUBLISHED,
    ]);

    // Create delivery option without capacity
    $this->deliveryOptionNoCapacity = ProductDeliveryOption::factory()->create([
        'product_id' => $product->id,
        'price'      => 300000,
        'uuid'       => Str::uuid()->toString(),
        'capacity'   => null, // No capacity limit
        'status'     => PublicationStatusEnum::PUBLISHED,
    ]);

    // Create authenticated user
    $this->user = User::factory()->create();
});

describe('Checkout Success', function (): void {
    test('authenticated user can checkout and create order from cart', function (): void {
        // Arrange: Add items to cart
        $this->actingAs($this->user, 'user');

        postJson(route('api.v1.shop.cart.items.store'), [
            'product_delivery_option_uuid' => $this->deliveryOption->uuid,
            'quantity'                     => 1,
        ])->assertOk();

        postJson(route('api.v1.shop.cart.items.store'), [
            'product_delivery_option_uuid' => $this->deliveryOptionNoCapacity->uuid,
            'quantity'                     => 1,
        ])->assertOk();

        // Act: Checkout
        $response = postJson(route('api.v1.shop.checkout'), [
            'payment_method' => 'bank_transfer',
        ]);

        // Assert: Order created successfully
        $response->assertCreated()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'increment_id',
                    'status',
                    'total_qty_ordered',
                    'total_item_count',
                    'subtotal',
                    'discount_amount',
                    'grand_total',
                    'items',
                ],
            ]);

        // Assert: Order exists in database
        assertDatabaseHas(Order::class, [
            'customer_id'       => $this->user->id,
            'status'            => OrderStatusEnum::PENDING->value,
            'total_item_count'  => 2,
            'total_qty_ordered' => 2, // 1 + 1
        ]);

        // Assert: Order items created
        $orderId = $response->json('data.id');
        assertDatabaseHas(OrderItem::class, [
            'order_id'                   => $orderId,
            'product_delivery_option_id' => $this->deliveryOption->id,
            'qty_ordered'                => 1,
        ]);

        assertDatabaseHas(OrderItem::class, [
            'order_id'                   => $orderId,
            'product_delivery_option_id' => $this->deliveryOptionNoCapacity->id,
            'qty_ordered'                => 1,
        ]);

        // Assert: Enrollments created
        assertDatabaseHas('enrollments', [
            'order_id'                   => $orderId,
            'customer_id'                => $this->user->id,
            'product_delivery_option_id' => $this->deliveryOption->id,
        ]);
    });

    test('checkout with empty cart returns validation error', function (): void {
        // Arrange: Authenticated user with empty cart
        $this->actingAs($this->user, 'user');

        // Act: Attempt checkout
        $response = postJson(route('api.v1.shop.checkout'), [
            'payment_method' => 'bank_transfer',
        ]);

        // Assert: Validation error
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['cart']);
    });
});

describe('Checkout Validation', function (): void {
    test('checkout fails when delivery option has zero capacity', function (): void {
        // Arrange: Create delivery option with zero capacity
        $vendor = Vendor::factory()->create();
        $term   = Term::factory()->create();
        $course = Course::factory()->create([
            'status' => PublicationStatusEnum::PUBLISHED,
        ]);

        $product = Product::factory()->create([
            'vendor_id'        => $vendor->id,
            'term_id'          => $term->id,
            'productable_id'   => $course->id,
            'productable_type' => MorphTypeEnum::COURSE->value,
            'status'           => PublicationStatusEnum::PUBLISHED,
            'is_visible'       => true,
        ]);

        $soldOutOption = ProductDeliveryOption::factory()->create([
            'product_id' => $product->id,
            'price'      => 200000,
            'uuid'       => Str::uuid()->toString(),
            'capacity'   => 2, // Only 2 spots
            'status'     => PublicationStatusEnum::PUBLISHED,
        ]);

        // Create 2 enrollments to fill capacity using factory
        for ($i = 0; $i < 2; $i++) {
            App\Models\Enrollment::factory()->create([
                'product_delivery_option_id' => $soldOutOption->id,
                'customer_id'                => User::factory()->create()->id,
            ]);
        }

        // Add sold out item to cart
        $this->actingAs($this->user, 'user');

        postJson(route('api.v1.shop.cart.items.store'), [
            'product_delivery_option_uuid' => $soldOutOption->uuid,
            'quantity'                     => 1,
        ])->assertOk();

        // Act: Attempt checkout
        $response = postJson(route('api.v1.shop.checkout'), [
            'payment_method' => 'bank_transfer',
        ]);

        // Assert: Validation error
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items.0']);
    });

    test('checkout fails when quantity exceeds available capacity', function (): void {
        // Arrange: Delivery option with limited capacity
        $vendor = Vendor::factory()->create();
        $term   = Term::factory()->create();
        $course = Course::factory()->create([
            'status' => PublicationStatusEnum::PUBLISHED,
        ]);

        $product = Product::factory()->create([
            'vendor_id'        => $vendor->id,
            'term_id'          => $term->id,
            'productable_id'   => $course->id,
            'productable_type' => MorphTypeEnum::COURSE->value,
            'status'           => PublicationStatusEnum::PUBLISHED,
            'is_visible'       => true,
        ]);

        $limitedOption = ProductDeliveryOption::factory()->create([
            'product_id' => $product->id,
            'price'      => 200000,
            'uuid'       => Str::uuid()->toString(),
            'capacity'   => 5, // Only 5 spots
            'status'     => PublicationStatusEnum::PUBLISHED,
        ]);

        // Create 3 enrollments (2 spots remaining) using factory
        for ($i = 0; $i < 3; $i++) {
            App\Models\Enrollment::factory()->create([
                'product_delivery_option_id' => $limitedOption->id,
                'customer_id'                => User::factory()->create()->id,
            ]);
        }

        // Add item with quantity that exceeds available capacity
        $this->actingAs($this->user, 'user');

        postJson(route('api.v1.shop.cart.items.store'), [
            'product_delivery_option_uuid' => $limitedOption->uuid,
            'quantity'                     => 3, // Requesting 3 but only 2 available
        ])->assertOk();

        // Act: Attempt checkout
        $response = postJson(route('api.v1.shop.checkout'), [
            'payment_method' => 'bank_transfer',
        ]);

        // Assert: Validation error
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items.0']);
    });

    test('checkout fails when product is not published', function (): void {
        // Arrange: Create unpublished product
        $vendor = Vendor::factory()->create();
        $term   = Term::factory()->create();
        $course = Course::factory()->create([
            'status' => PublicationStatusEnum::PUBLISHED,
        ]);

        $product = Product::factory()->create([
            'vendor_id'        => $vendor->id,
            'term_id'          => $term->id,
            'productable_id'   => $course->id,
            'productable_type' => MorphTypeEnum::COURSE->value,
            'status'           => PublicationStatusEnum::DRAFT, // Not published
            'is_visible'       => false,
        ]);

        $unpublishedOption = ProductDeliveryOption::factory()->create([
            'product_id' => $product->id,
            'price'      => 200000,
            'uuid'       => Str::uuid()->toString(),
            'status'     => PublicationStatusEnum::PUBLISHED,
        ]);

        // Add unpublished product to cart
        $this->actingAs($this->user, 'user');

        postJson(route('api.v1.shop.cart.items.store'), [
            'product_delivery_option_uuid' => $unpublishedOption->uuid,
            'quantity'                     => 1,
        ])->assertOk();

        // Act: Attempt checkout
        $response = postJson(route('api.v1.shop.checkout'), [
            'payment_method' => 'bank_transfer',
        ]);

        // Assert: Validation error
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items.0']);
    });

    test('guest user cannot checkout without authentication', function (): void {
        // Arrange: Add item to guest cart
        $response = postJson(route('api.v1.shop.cart.items.store'), [
            'product_delivery_option_uuid' => $this->deliveryOption->uuid,
            'quantity'                     => 1,
        ]);

        $guestToken = $response->headers->get('X-Guest-Token');

        // Act: Attempt checkout as guest
        $response = postJson(route('api.v1.shop.checkout'), [
            'payment_method' => 'wallet',
        ], [
            'X-Guest-Token' => $guestToken,
        ]);

        // Assert: Validation error (user must be logged in)
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['user']);
    });
});

test('user can checkout with wallet payment and order is completed', function (): void {
    // Arrange: Update wallet with sufficient balance (wallet is auto-created with user)
    $wallet = $this->user->wallet;
    $wallet->update([
        'balance'      => 1000000, // 1,000,000 IRR
        'gift_balance' => 0,
    ]);

    // Add item to cart
    $this->actingAs($this->user, 'user');

    postJson(route('api.v1.shop.cart.items.store'), [
        'product_delivery_option_uuid' => $this->deliveryOption->uuid,
        'quantity'                     => 1,
    ])->assertOk();

    // Act: Checkout with wallet payment
    $response = postJson(route('api.v1.shop.checkout'), [
        'payment_method' => 'wallet',
    ]);

    // Assert: Order created successfully
    $response->assertCreated();

    $orderId = $response->json('data.id');

    // Assert: Order status is COMPLETED (not pending)
    assertDatabaseHas(Order::class, [
        'id'          => $orderId,
        'customer_id' => $this->user->id,
        'status'      => OrderStatusEnum::COMPLETED->value,
    ]);

    // Assert: Payment record created
    assertDatabaseHas('payments', [
        'order_id' => $orderId,
        'method'   => 'wallet',
        'amount'   => 500000, // Price of delivery option
        'status'   => 'completed',
    ]);

    // Assert: Wallet transaction recorded
    assertDatabaseHas('wallet_transactions', [
        'user_id'     => $this->user->id,
        'type'        => 'payment',
        'amount'      => -500000, // Negative for debit
        'source_type' => 'order',
        'source_id'   => $orderId,
    ]);

    // Assert: Wallet balance decreased
    $wallet->refresh();
    expect($wallet->balance)->toBe(500000); // 1,000,000 - 500,000

    // Assert: Cart is deleted after checkout
    $this->assertDatabaseMissing('carts', [
        'user_id' => $this->user->id,
    ]);
});

test('checkout fails with insufficient wallet balance', function (): void {
    // Arrange: Update wallet with insufficient balance
    $this->user->wallet->update([
        'balance'      => 100000, // Only 100,000 IRR
        'gift_balance' => 0,
    ]);

    // Add item to cart
    $this->actingAs($this->user, 'user');

    postJson(route('api.v1.shop.cart.items.store'), [
        'product_delivery_option_uuid' => $this->deliveryOption->uuid,
        'quantity'                     => 1, // Costs 500,000
    ])->assertOk();

    // Act: Attempt checkout with wallet payment
    $response = postJson(route('api.v1.shop.checkout'), [
        'payment_method' => 'wallet',
    ]);

    // Assert: Validation error
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['wallet']);

    // Assert: No order was created
    $this->assertDatabaseMissing(Order::class, [
        'customer_id' => $this->user->id,
    ]);
});

test('wallet payment uses only regular balance (not gift balance)', function (): void {
    // Arrange: Set wallet with sufficient regular balance
    // Note: Gift balance is separate and not used for order payments
    $this->user->wallet->update([
        'balance'      => 500000, // 500,000 IRR regular balance (sufficient)
        'gift_balance' => 300000, // 300,000 IRR gift balance (not used for payments)
    ]);

    // Add item to cart
    $this->actingAs($this->user, 'user');

    postJson(route('api.v1.shop.cart.items.store'), [
        'product_delivery_option_uuid' => $this->deliveryOption->uuid,
        'quantity'                     => 1, // Costs 500,000
    ])->assertOk();

    // Act: Checkout with wallet payment
    $response = postJson(route('api.v1.shop.checkout'), [
        'payment_method' => 'wallet',
    ]);

    // Assert: Order created successfully
    $response->assertCreated();

    // Assert: Payment completed
    $orderId = $response->json('data.id');
    assertDatabaseHas('payments', [
        'order_id' => $orderId,
        'status'   => 'completed',
    ]);

    // Assert: Only regular balance decreased, gift balance unchanged
    $this->user->wallet->refresh();
    expect($this->user->wallet->balance)->toBe(0); // 500,000 - 500,000
    expect($this->user->wallet->gift_balance)->toBe(300000); // Unchanged
});

test('checkout with bank_transfer creates pending order without payment', function (): void {
    // Arrange: Add item to cart
    $this->actingAs($this->user, 'user');

    postJson(route('api.v1.shop.cart.items.store'), [
        'product_delivery_option_uuid' => $this->deliveryOption->uuid,
        'quantity'                     => 1,
    ])->assertOk();

    // Act: Checkout with bank_transfer
    $response = postJson(route('api.v1.shop.checkout'), [
        'payment_method' => 'bank_transfer',
    ]);

    // Assert: Order created successfully
    $response->assertCreated();

    $orderId = $response->json('data.id');

    // Assert: Order status is PENDING (not completed)
    assertDatabaseHas(Order::class, [
        'id'     => $orderId,
        'status' => OrderStatusEnum::PENDING->value,
    ]);

    // Assert: No payment record created yet
    $this->assertDatabaseMissing('payments', [
        'order_id' => $orderId,
    ]);

    // Assert: Cart is still deleted after checkout
    $this->assertDatabaseMissing('carts', [
        'user_id' => $this->user->id,
    ]);
});

test('checkout endpoint enforces rate limit of 5 requests per minute', function (): void {
    // Arrange: Create test user
    $user = User::factory()->create();
    $this->actingAs($user, 'user');

    // Create delivery option
    $vendor = Vendor::factory()->create();
    $term   = Term::factory()->create();
    $course = Course::factory()->create([
        'status' => PublicationStatusEnum::PUBLISHED,
    ]);

    $product = Product::factory()->create([
        'vendor_id'        => $vendor->id,
        'term_id'          => $term->id,
        'productable_id'   => $course->id,
        'productable_type' => MorphTypeEnum::COURSE->value,
        'status'           => PublicationStatusEnum::PUBLISHED,
        'is_visible'       => true,
    ]);

    $deliveryOption = ProductDeliveryOption::factory()->create([
        'product_id' => $product->id,
        'price'      => 500000,
        'uuid'       => Str::uuid()->toString(),
        'status'     => PublicationStatusEnum::PUBLISHED,
    ]);

    // Add item to cart
    postJson(route('api.v1.shop.cart.items.store'), [
        'product_delivery_option_uuid' => $deliveryOption->uuid,
        'quantity'                     => 1,
    ])->assertOk();

    // Act: Hit the checkout endpoint 6 times
    $responses = [];
    for ($i = 0; $i < 6; $i++) {
        $responses[] = postJson(route('api.v1.shop.checkout'), [
            'payment_method' => 'bank_transfer',
        ]);
    }

    // Assert: The 6th request should be rate limited
    $lastResponse = end($responses);
    $lastResponse->assertStatus(429); // Too Many Requests
});

test('user cannot create more than 5 orders in one hour', function (): void {
    // Arrange: Create test user
    $user = User::factory()->create();

    // Create 5 orders for the user manually
    for ($i = 0; $i < 5; $i++) {
        Order::factory()->create([
            'customer_id' => $user->id,
            'created_at'  => now()->subMinutes(30), // Within the last hour
        ]);
    }

    // Create delivery option
    $vendor = Vendor::factory()->create();
    $term   = Term::factory()->create();
    $course = Course::factory()->create([
        'status' => PublicationStatusEnum::PUBLISHED,
    ]);

    $product = Product::factory()->create([
        'vendor_id'        => $vendor->id,
        'term_id'          => $term->id,
        'productable_id'   => $course->id,
        'productable_type' => MorphTypeEnum::COURSE->value,
        'status'           => PublicationStatusEnum::PUBLISHED,
        'is_visible'       => true,
    ]);

    $deliveryOption = ProductDeliveryOption::factory()->create([
        'product_id' => $product->id,
        'price'      => 500000,
        'uuid'       => Str::uuid()->toString(),
        'status'     => PublicationStatusEnum::PUBLISHED,
    ]);

    // Add item to cart
    $this->actingAs($user, 'user');

    postJson(route('api.v1.shop.cart.items.store'), [
        'product_delivery_option_uuid' => $deliveryOption->uuid,
        'quantity'                     => 1,
    ])->assertOk();

    // Act: Attempt to checkout (6th order)
    $response = postJson(route('api.v1.shop.checkout'), [
        'payment_method' => 'bank_transfer',
    ]);

    // Assert: Validation error due to velocity check
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['velocity']);

    // Assert: No new order was created
    $orderCount = Order::where('customer_id', $user->id)->count();
    expect($orderCount)->toBe(5); // Still only 5 orders
});

test('velocity check only counts orders from the last hour', function (): void {
    // Arrange: Create test user
    $user = User::factory()->create();

    // Create 5 orders for the user that are older than 1 hour
    for ($i = 0; $i < 5; $i++) {
        Order::factory()->create([
            'customer_id' => $user->id,
            'created_at'  => now()->subHours(2), // More than 1 hour ago
        ]);
    }

    // Create delivery option
    $vendor = Vendor::factory()->create();
    $term   = Term::factory()->create();
    $course = Course::factory()->create([
        'status' => PublicationStatusEnum::PUBLISHED,
    ]);

    $product = Product::factory()->create([
        'vendor_id'        => $vendor->id,
        'term_id'          => $term->id,
        'productable_id'   => $course->id,
        'productable_type' => MorphTypeEnum::COURSE->value,
        'status'           => PublicationStatusEnum::PUBLISHED,
        'is_visible'       => true,
    ]);

    $deliveryOption = ProductDeliveryOption::factory()->create([
        'product_id' => $product->id,
        'price'      => 500000,
        'uuid'       => Str::uuid()->toString(),
        'status'     => PublicationStatusEnum::PUBLISHED,
    ]);

    // Add item to cart
    $this->actingAs($user, 'user');

    postJson(route('api.v1.shop.cart.items.store'), [
        'product_delivery_option_uuid' => $deliveryOption->uuid,
        'quantity'                     => 1,
    ])->assertOk();

    // Act: Checkout (should succeed since old orders don't count)
    $response = postJson(route('api.v1.shop.checkout'), [
        'payment_method' => 'bank_transfer',
    ]);

    // Assert: Order created successfully
    $response->assertCreated();

    // Assert: New order exists
    $recentOrderCount = Order::where('customer_id', $user->id)
        ->where('created_at', '>=', now()->subHour())
        ->count();
    expect($recentOrderCount)->toBe(1); // Only the new order is recent
});
