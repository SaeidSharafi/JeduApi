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

uses(\Tests\AuthTestTrait::class);
beforeEach(function (): void {
    $vendor = Vendor::factory()->create();
    $term = Term::factory()->create();
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

    $this->deliveryOption = ProductDeliveryOption::factory()->create([
        'product_id' => $product->id,
        'price'      => 500000, 'uuid' => Str::uuid()->toString(),
        'capacity'   => 5, 'status' => PublicationStatusEnum::PUBLISHED,
    ]);

    $this->deliveryOptionNoCapacity = ProductDeliveryOption::factory()->create([
        'product_id' => $product->id,
        'price'      => 300000,
        'uuid'       => Str::uuid()->toString(),
        'capacity'   => null, 'status' => PublicationStatusEnum::PUBLISHED,
    ]);
});

describe('Checkout Success', function (): void {
    test('authenticated user can checkout and create order from cart', function (): void {
        $this->customer();

        postJson(route('api.v1.shop.cart.items.store'), [
            'product_delivery_option_uuid' => $this->deliveryOption->uuid,
            'quantity'                     => 1,
        ])->assertOk();

        postJson(route('api.v1.shop.cart.items.store'), [
            'product_delivery_option_uuid' => $this->deliveryOptionNoCapacity->uuid,
            'quantity'                     => 1,
        ])->assertOk();

        $response = postJson(route('api.v1.shop.checkout'), [
            'payment_method' => 'bank_transfer',
        ]);

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

        assertDatabaseHas(Order::class, [
            'customer_id'       => $this->user->id,
            'status'            => OrderStatusEnum::PENDING->value,
            'total_item_count'  => 2,
            'total_qty_ordered' => 2,
        ]);

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

        assertDatabaseHas('enrollments', [
            'order_id'                   => $orderId,
            'customer_id'                => $this->user->id,
            'product_delivery_option_id' => $this->deliveryOption->id,
        ]);
    });

    test('checkout with empty cart returns validation error', function (): void {
        $this->customer();

        $response = postJson(route('api.v1.shop.checkout'), [
            'payment_method' => 'bank_transfer',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['cart']);
    });
});

describe('Checkout Validation', function (): void {
    test('checkout fails when delivery option has zero capacity', function (): void {
        $vendor = Vendor::factory()->create();
        $term = Term::factory()->create();
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
            'capacity'   => 2, 'status' => PublicationStatusEnum::PUBLISHED,
        ]);

        for ($i = 0; $i < 2; $i++) {
            App\Models\Enrollment::factory()->create([
                'product_delivery_option_id' => $soldOutOption->id,
                'customer_id'                => User::factory()->create()->id,
            ]);
        }

        $this->customer();

        postJson(route('api.v1.shop.cart.items.store'), [
            'product_delivery_option_uuid' => $soldOutOption->uuid,
            'quantity'                     => 1,
        ])->assertOk();

        $response = postJson(route('api.v1.shop.checkout'), [
            'payment_method' => 'bank_transfer',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items.0']);
    });

    test('checkout fails when quantity exceeds available capacity', function (): void {
        $vendor = Vendor::factory()->create();
        $term = Term::factory()->create();
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
            'capacity'   => 5, 'status' => PublicationStatusEnum::PUBLISHED,
        ]);

        for ($i = 0; $i < 5; $i++) {
            App\Models\Enrollment::factory()->create([
                'product_delivery_option_id' => $limitedOption->id,
                'customer_id'                => User::factory()->create()->id,
            ]);
        }

        $this->customer();

        postJson(route('api.v1.shop.cart.items.store'), [
            'product_delivery_option_uuid' => $limitedOption->uuid,
            'quantity'                     => 1,
        ])->assertOk();

        $response = postJson(route('api.v1.shop.checkout'), [
            'payment_method' => 'bank_transfer',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items.0']);
    });

    test('checkout fails when product is not published', function (): void {
        $vendor = Vendor::factory()->create();
        $term = Term::factory()->create();
        $course = Course::factory()->create([
            'status' => PublicationStatusEnum::PUBLISHED,
        ]);

        $product = Product::factory()->create([
            'vendor_id'        => $vendor->id,
            'term_id'          => $term->id,
            'productable_id'   => $course->id,
            'productable_type' => MorphTypeEnum::COURSE->value,
            'status'           => PublicationStatusEnum::DRAFT, 'is_visible' => true,
        ]);

        $unpublishedOption = ProductDeliveryOption::factory()->create([
            'product_id' => $product->id,
            'price'      => 200000,
            'uuid'       => Str::uuid()->toString(),
            'status'     => PublicationStatusEnum::PUBLISHED,
        ]);

        $this->customer();

        postJson(route('api.v1.shop.cart.items.store'), [
            'product_delivery_option_uuid' => $unpublishedOption->uuid,
            'quantity'                     => 1,
        ])->assertOk();

        $response = postJson(route('api.v1.shop.checkout'), [
            'payment_method' => 'bank_transfer',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items.0']);
    });
    test('checkout fails when product is not visible', function (): void {
        $vendor = Vendor::factory()->create();
        $term = Term::factory()->create();
        $course = Course::factory()->create([
            'status' => PublicationStatusEnum::PUBLISHED,
        ]);

        $product = Product::factory()->create([
            'vendor_id'        => $vendor->id,
            'term_id'          => $term->id,
            'productable_id'   => $course->id,
            'productable_type' => MorphTypeEnum::COURSE->value,
            'status'           => PublicationStatusEnum::PUBLISHED, 'is_visible' => false,
        ]);

        $unpublishedOption = ProductDeliveryOption::factory()->create([
            'product_id' => $product->id,
            'price'      => 200000,
            'uuid'       => Str::uuid()->toString(),
            'status'     => PublicationStatusEnum::PUBLISHED,
        ]);

        $this->customer();

        postJson(route('api.v1.shop.cart.items.store'), [
            'product_delivery_option_uuid' => $unpublishedOption->uuid,
            'quantity'                     => 1,
        ])->assertOk();

        $response = postJson(route('api.v1.shop.checkout'), [
            'payment_method' => 'bank_transfer',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items.0']);
    });
    test('checkout fails when product delivery option is not published', function (): void {
        $vendor = Vendor::factory()->create();
        $term = Term::factory()->create();
        $course = Course::factory()->create([
            'status' => PublicationStatusEnum::PUBLISHED,
        ]);

        $product = Product::factory()->create([
            'vendor_id'        => $vendor->id,
            'term_id'          => $term->id,
            'productable_id'   => $course->id,
            'productable_type' => MorphTypeEnum::COURSE->value,
            'status'           => PublicationStatusEnum::PUBLISHED, 'is_visible' => true,
        ]);

        $unpublishedOption = ProductDeliveryOption::factory()->create([
            'product_id' => $product->id,
            'price'      => 200000,
            'uuid'       => Str::uuid()->toString(),
            'status'     => PublicationStatusEnum::DRAFT,
        ]);

        $this->customer();

        postJson(route('api.v1.shop.cart.items.store'), [
            'product_delivery_option_uuid' => $unpublishedOption->uuid,
            'quantity'                     => 1,
        ])->assertOk();

        $response = postJson(route('api.v1.shop.checkout'), [
            'payment_method' => 'bank_transfer',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items.0']);
    });
    test('guest user cannot checkout without authentication', function (): void {
        $response = postJson(route('api.v1.shop.cart.items.store'), [
            'product_delivery_option_uuid' => $this->deliveryOption->uuid,
            'quantity'                     => 1,
        ]);

        $guestToken = $response->headers->get('X-Guest-Token');

        $response = postJson(route('api.v1.shop.checkout'), [
            'payment_method' => 'wallet',
        ], [
            'X-Guest-Token' => $guestToken,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['auth']);
    });
});

test('user can checkout with wallet payment and order is completed', function (): void {
    $this->customer();
    $wallet = $this->user->wallet;
    $wallet->update([
        'balance' => 1000000, 'gift_balance' => 0,
    ]);

    postJson(route('api.v1.shop.cart.items.store'), [
        'product_delivery_option_uuid' => $this->deliveryOption->uuid,
        'quantity'                     => 1,
    ])->assertOk();

    $response = postJson(route('api.v1.shop.checkout'), [
        'payment_method' => 'wallet',
    ]);

    $response->assertCreated();

    $orderId = $response->json('data.id');

    assertDatabaseHas(Order::class, [
        'id'          => $orderId,
        'customer_id' => $this->user->id,
        'status'      => OrderStatusEnum::COMPLETED->value,
    ]);

    assertDatabaseHas('payments', [
        'order_id' => $orderId,
        'method'   => 'wallet',
        'amount'   => 500000, 'status' => 'completed',
    ]);

    assertDatabaseHas('wallet_transactions', [
        'user_id'   => $this->user->id,
        'type'      => 'payment',
        'amount'    => -500000, 'source_type' => 'order',
        'source_id' => $orderId,
    ]);

    $wallet->refresh();
    expect($wallet->balance)->toBe(500000);
    $this->assertDatabaseMissing('carts', [
        'user_id' => $this->user->id,
    ]);
});

test('checkout fails with insufficient wallet balance', function (): void {
    $this->customer();

    $this->user->wallet->update([
        'balance' => 100000, 'gift_balance' => 0,
    ]);

    postJson(route('api.v1.shop.cart.items.store'), [
        'product_delivery_option_uuid' => $this->deliveryOption->uuid,
        'quantity'                     => 1,
    ])->assertOk();

    $response = postJson(route('api.v1.shop.checkout'), [
        'payment_method' => 'wallet',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['wallet']);

    $this->assertDatabaseMissing(Order::class, [
        'customer_id' => $this->user->id,
    ]);
});

test('wallet payment uses only regular balance (not gift balance)', function (): void {
    $this->customer();
    $this->user->wallet->update([
        'balance' => 500000, 'gift_balance' => 300000,
    ]);

    postJson(route('api.v1.shop.cart.items.store'), [
        'product_delivery_option_uuid' => $this->deliveryOption->uuid,
        'quantity'                     => 1,
    ])->assertOk();

    $response = postJson(route('api.v1.shop.checkout'), [
        'payment_method' => 'wallet',
    ]);

    $response->assertCreated();

    $orderId = $response->json('data.id');
    assertDatabaseHas('payments', [
        'order_id' => $orderId,
        'status'   => 'completed',
    ]);

    $this->user->wallet->refresh();
    expect($this->user->wallet->balance)->toBe(0);
    expect($this->user->wallet->gift_balance)->toBe(300000);
});

test('checkout with bank_transfer creates pending order without payment', function (): void {
    $this->customer();

    postJson(route('api.v1.shop.cart.items.store'), [
        'product_delivery_option_uuid' => $this->deliveryOption->uuid,
        'quantity'                     => 1,
    ])->assertOk();

    $response = postJson(route('api.v1.shop.checkout'), [
        'payment_method' => 'bank_transfer',
    ]);

    $response->assertCreated();

    $orderId = $response->json('data.id');

    assertDatabaseHas(Order::class, [
        'id'     => $orderId,
        'status' => OrderStatusEnum::PENDING->value,
    ]);

    $this->assertDatabaseMissing('payments', [
        'order_id' => $orderId,
    ]);

    $this->assertDatabaseMissing('carts', [
        'user_id' => $this->user->id,
    ]);
});

test('checkout endpoint enforces rate limit of 5 requests per minute', function (): void {
    $user = User::factory()->create();
    $this->customer($user);

    $vendor = Vendor::factory()->create();
    $term = Term::factory()->create();
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

    postJson(route('api.v1.shop.cart.items.store'), [
        'product_delivery_option_uuid' => $deliveryOption->uuid,
        'quantity'                     => 1,
    ])->assertOk();

    $responses = [];
    for ($i = 0; $i < 6; $i++) {
        $responses[] = postJson(route('api.v1.shop.checkout'), [
            'payment_method' => 'bank_transfer',
        ]);
    }

    $lastResponse = end($responses);
    $lastResponse->assertStatus(429);
});

test('user cannot create more than 5 orders in one hour', function (): void {
    $user = User::factory()->create();

    for ($i = 0; $i < 5; $i++) {
        Order::factory()->create([
            'customer_id' => $user->id,
            'created_at'  => now()->subMinutes(30),
        ]);
    }

    $vendor = Vendor::factory()->create();
    $term = Term::factory()->create();
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

    $this->customer($user);

    postJson(route('api.v1.shop.cart.items.store'), [
        'product_delivery_option_uuid' => $deliveryOption->uuid,
        'quantity'                     => 1,
    ])->assertOk();

    $response = postJson(route('api.v1.shop.checkout'), [
        'payment_method' => 'bank_transfer',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['velocity']);

    $orderCount = Order::where('customer_id', $user->id)->count();
    expect($orderCount)->toBe(5);
});

test('velocity check only counts orders from the last hour', function (): void {
    $user = User::factory()->create();

    for ($i = 0; $i < 5; $i++) {
        Order::factory()->create([
            'customer_id' => $user->id,
            'created_at'  => now()->subHours(2),
        ]);
    }

    $vendor = Vendor::factory()->create();
    $term = Term::factory()->create();
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

    $this->customer($user);

    postJson(route('api.v1.shop.cart.items.store'), [
        'product_delivery_option_uuid' => $deliveryOption->uuid,
        'quantity'                     => 1,
    ])->assertOk();

    $response = postJson(route('api.v1.shop.checkout'), [
        'payment_method' => 'bank_transfer',
    ]);

    $response->assertCreated();

    $recentOrderCount = Order::where('customer_id', $user->id)
        ->where('created_at', '>=', now()->subHour())
        ->count();
    expect($recentOrderCount)->toBe(1);
});
