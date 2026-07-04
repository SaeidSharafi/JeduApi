<?php

declare(strict_types=1);

use App\Enums\Content\PublicationStatusEnum;
use App\Enums\Order\OrderStatusEnum;
use App\Enums\Payment\PaymentMethodEnum;
use App\Enums\System\MorphTypeEnum;
use App\Jobs\Provisioning\ProvisionBbbEnrollmentJob;
use App\Jobs\Provisioning\ProvisionImsEnrollmentJob;
use App\Jobs\Provisioning\ProvisionMoodleEnrollmentJob;
use App\Jobs\Provisioning\ProvisionSpotPlayerEnrollmentJob;
use App\Models\Course;
use App\Models\DiscountCoupon;
use App\Models\DiscountPromotion;
use App\Models\DiscountPromotionRule;
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

uses(Tests\Support\Traits\AuthTestTrait::class);
beforeEach(function (): void {
    Queue::fake([
        ProvisionImsEnrollmentJob::class,
        ProvisionMoodleEnrollmentJob::class,
        ProvisionSpotPlayerEnrollmentJob::class,
        ProvisionBbbEnrollmentJob::class,
    ]);
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
                    'order' => [
                        'id',
                        'increment_id',
                        'status',
                        'total_qty_ordered',
                        'total_item_count',
                        'subtotal',
                        'discount_amount',
                        'grand_total',
                        'items',
                        'payments' => [
                            '*' => [
                                'id',
                                'amount',
                                'method',
                                'status',
                                'last_gateway_reference',
                                'attempt_count',
                                'transactions',
                            ],
                        ],
                    ],
                    'redirect_url',
                    'redirect_data',
                    'redirect_method',
                ],
            ]);

        assertDatabaseHas(Order::class, [
            'customer_id'       => $this->user->id,
            'status'            => OrderStatusEnum::COMPLETED->value,
            'total_item_count'  => 2,
            'total_qty_ordered' => 2,
        ]);

        $orderId = $response->json('data.order.id');
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
            'capacity'   => 2, 'status' => PublicationStatusEnum::PUBLISHED,
        ]);

        for ($i = 0; $i < 2; $i++) {
            App\Models\Enrollment::factory()->create([
                'product_delivery_option_id' => $soldOutOption->id,
                'customer_id'                => User::factory()->create()->id,
                'enrollment_status'          => App\Enums\EnrollmentStatusEnum::ACTIVE,
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
            'capacity'   => 5, 'status' => PublicationStatusEnum::PUBLISHED,
        ]);

        for ($i = 0; $i < 5; $i++) {
            App\Models\Enrollment::factory()->create([
                'product_delivery_option_id' => $limitedOption->id,
                'customer_id'                => User::factory()->create()->id,
                'enrollment_status'          => App\Enums\EnrollmentStatusEnum::ACTIVE,
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
        $term   = Term::factory()->create();
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
        $term   = Term::factory()->create();
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
        $term   = Term::factory()->create();
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
    $response->assertJsonStructure(
        [
            'message',
            'data' => [
                'order' => [
                    'id',
                    'increment_id',
                    'status',
                    'total_qty_ordered',
                    'total_item_count',
                    'subtotal',
                    'discount_amount',
                    'grand_total',
                    'items',
                    'payments' => [
                        '*' => [
                            'id',
                            'amount',
                            'method',
                            'status',
                            'last_gateway_reference',
                            'attempt_count',
                            'transactions' => [
                                '*' => [
                                    'transaction_reference',
                                    'attempt_number',
                                    'status',
                                    'gateway_request',
                                    'gateway_response',
                                    'initiated_at',
                                    'completed_at',
                                    'error_code',
                                    'error_message',
                                    'ip_address',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]
    );
    $orderId = $response->json('data.order.id');

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
        ->assertJsonPath('metadata.error_code', 'INSUFFICIENT_WALLET_BALANCE');

    $this->assertDatabaseHas(Order::class, [
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

    $orderId = $response->json('data.order.id');
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

    $orderId = $response->json('data.order.id');

    assertDatabaseHas(Order::class, [
        'id'     => $orderId,
        'status' => OrderStatusEnum::COMPLETED->value,
    ]);

    // With the new payment processor architecture, bank_transfer creates a COMPLETED payment immediately
    assertDatabaseHas('payments', [
        'order_id' => $orderId,
        'method'   => 'bank_transfer',
        'status'   => 'completed',
    ]);

    $this->assertDatabaseMissing('carts', [
        'user_id' => $this->user->id,
    ]);
});

test('checkout endpoint enforces rate limit of 5 requests per minute', function (): void {
    $user = User::factory()->create();
    $this->customer($user);

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

test('free order (with 100% discount) is auto-completed with NO_PAYMENT', function (): void {
    $this->customer();

    // Create a 100% discount promotion
    $promotion = DiscountPromotion::factory()->create([
        'name'      => '100% Off Everything',
        'type'      => App\Enums\Order\DiscountTypeEnum::CART_CHECKOUT,
        'is_active' => true,
        'starts_at' => now()->subDay(),
        'ends_at'   => now()->addDay(),
        'priority'  => 1,
    ]);

    DiscountPromotionRule::create([
        'discount_promotion_id' => $promotion->id,
        'type'                  => 'action',
        'handler'               => 'apply_percentage_off',
        'configuration'         => ['percentage' => 100],
    ]);

    $coupon = DiscountCoupon::factory()->create([
        'discount_promotion_id' => $promotion->id,
        'code'                  => 'FREE100',
        'is_active'             => true,
    ]);

    // Add item to cart
    postJson(route('api.v1.shop.cart.items.store'), [
        'product_delivery_option_uuid' => $this->deliveryOption->uuid,
        'quantity'                     => 1,
    ])->assertOk();

    // Apply 100% coupon
    postJson(route('api.v1.shop.cart.coupon.apply'), [
        'coupon_code' => 'FREE100',
    ])->assertOk();

    // Checkout without payment_method (optional for free orders)
    $response = postJson(route('api.v1.shop.checkout'));

    $response->assertCreated();

    $orderId = $response->json('data.order.id');

    // Order should be completed
    assertDatabaseHas(Order::class, [
        'id'          => $orderId,
        'customer_id' => $this->user->id,
        'status'      => OrderStatusEnum::COMPLETED->value,
        'grand_total' => 0,
    ]);

    // Payment should use NO_PAYMENT method
    assertDatabaseHas('payments', [
        'order_id' => $orderId,
        'method'   => 'no_payment',
        'amount'   => 0,
        'status'   => 'completed',
    ]);

    // Enrollment should be created
    assertDatabaseHas('enrollments', [
        'order_id'                   => $orderId,
        'customer_id'                => $this->user->id,
        'product_delivery_option_id' => $this->deliveryOption->id,
    ]);
});
test('checkout returns multi-step payment data for external processors', function (): void {
    $this->customer();

    postJson(route('api.v1.shop.cart.items.store'), [
        'product_delivery_option_uuid' => $this->deliveryOption->uuid,
        'quantity'                     => 1,
    ])->assertOk();
    $this->instance(App\Services\Payment\MellatGatewayPaymentProcessor::class,
        new Tests\Support\Fakes\Payment\MockMultiStepProcessor());
    $response = postJson(route('api.v1.shop.checkout'), [
        'payment_method' => PaymentMethodEnum::MELLAT_GATEWAY->value,
    ]);

    $response->assertCreated()
        ->assertJsonStructure([
            'message',
            'data' => [
                'order' => [
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
                'redirect_url',
                'redirect_data',
                'redirect_method',
            ],
        ]);

    $data = $response->json('data');
    expect($data['redirect_url'])->toBeString()
        ->and($data['redirect_method'])->toBe('POST')
        ->and($data['redirect_data'])->toBeArray();
});
describe('Duplicate Purchase Prevention', function (): void {
    test('checkout fails when user already has active enrollment for product', function (): void {
        $user = User::factory()->create();
        $this->customer($user);

        // Create an active enrollment for the user
        App\Models\Enrollment::factory()->create([
            'customer_id'                => $user->id,
            'product_delivery_option_id' => $this->deliveryOption->id,
            'enrollment_status'          => App\Enums\EnrollmentStatusEnum::ACTIVE,
        ]);

        // Try to add the same product to cart and checkout
        postJson(route('api.v1.shop.cart.items.store'), [
            'product_delivery_option_uuid' => $this->deliveryOption->uuid,
            'quantity'                     => 1,
        ])->assertOk();

        $response = postJson(route('api.v1.shop.checkout'), [
            'payment_method' => 'bank_transfer',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items']);

        expect($response->json('errors.items.0'))->toContain('already purchased');
    });

    test('checkout fails when user has pending provisioning enrollment', function (): void {
        $user = User::factory()->create();
        $this->customer($user);

        // Create a pending enrollment
        App\Models\Enrollment::factory()->create([
            'customer_id'                => $user->id,
            'product_delivery_option_id' => $this->deliveryOption->id,
            'enrollment_status'          => App\Enums\EnrollmentStatusEnum::PENDING_PROVISIONING,
        ]);

        postJson(route('api.v1.shop.cart.items.store'), [
            'product_delivery_option_uuid' => $this->deliveryOption->uuid,
            'quantity'                     => 1,
        ])->assertOk();

        $response = postJson(route('api.v1.shop.checkout'), [
            'payment_method' => 'bank_transfer',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items']);
    });

    test('checkout succeeds when user has cancelled enrollment (refunded)', function (): void {
        $user = User::factory()->create();
        $this->customer($user);

        // User previously had access but got a refund
        App\Models\Enrollment::factory()->create([
            'customer_id'                => $user->id,
            'product_delivery_option_id' => $this->deliveryOption->id,
            'enrollment_status'          => App\Enums\EnrollmentStatusEnum::CANCELLED,
        ]);

        postJson(route('api.v1.shop.cart.items.store'), [
            'product_delivery_option_uuid' => $this->deliveryOption->uuid,
            'quantity'                     => 1,
        ])->assertOk();

        $response = postJson(route('api.v1.shop.checkout'), [
            'payment_method' => 'bank_transfer',
        ]);

        $response->assertCreated();
    });

    test('checkout succeeds when user has expired enrollment', function (): void {
        $user = User::factory()->create();
        $this->customer($user);
        // User's access has expired
        App\Models\Enrollment::factory()->create([
            'customer_id'                => $user->id,
            'product_delivery_option_id' => $this->deliveryOption->id,
            'enrollment_status'          => App\Enums\EnrollmentStatusEnum::EXPIRED,
        ]);

        postJson(route('api.v1.shop.cart.items.store'), [
            'product_delivery_option_uuid' => $this->deliveryOption->uuid,
            'quantity'                     => 1,
        ])->assertOk();

        $response = postJson(route('api.v1.shop.checkout'), [
            'payment_method' => 'bank_transfer',
        ]);

        $response->assertCreated();
    });

    test('checkout fails with multiple duplicate products in cart', function (): void {
        $user   = User::factory()->create();
        $vendor = Vendor::factory()->create();
        $term   = Term::factory()->create();
        $this->customer($user);

        // Create two different products that the user already owns
        $course1  = Course::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]);
        $product1 = Product::factory()->create([
            'vendor_id'        => $vendor->id,
            'term_id'          => $term->id,
            'productable_id'   => $course1->id,
            'productable_type' => MorphTypeEnum::COURSE->value,
            'status'           => PublicationStatusEnum::PUBLISHED,
            'is_visible'       => true,
            'name'             => 'Course A',
        ]);
        $deliveryOption1 = ProductDeliveryOption::factory()->create([
            'product_id' => $product1->id,
            'status'     => PublicationStatusEnum::PUBLISHED,
            'name'       => 'Course A - Online',
        ]);

        $course2  = Course::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]);
        $product2 = Product::factory()->create([
            'vendor_id'        => $vendor->id,
            'term_id'          => $term->id,
            'productable_id'   => $course2->id,
            'productable_type' => MorphTypeEnum::COURSE->value,
            'status'           => PublicationStatusEnum::PUBLISHED,
            'is_visible'       => true,
            'name'             => 'Course B',
        ]);
        $deliveryOption2 = ProductDeliveryOption::factory()->create([
            'product_id' => $product2->id,
            'status'     => PublicationStatusEnum::PUBLISHED,
            'name'       => 'Course B - Online',
        ]);

        // User already owns both
        App\Models\Enrollment::factory()->create([
            'customer_id'                => $user->id,
            'product_delivery_option_id' => $deliveryOption1->id,
            'enrollment_status'          => App\Enums\EnrollmentStatusEnum::ACTIVE,
        ]);
        App\Models\Enrollment::factory()->create([
            'customer_id'                => $user->id,
            'product_delivery_option_id' => $deliveryOption2->id,
            'enrollment_status'          => App\Enums\EnrollmentStatusEnum::ACTIVE,
        ]);

        // Add both to cart
        postJson(route('api.v1.shop.cart.items.store'), [
            'product_delivery_option_uuid' => $deliveryOption1->uuid,
            'quantity'                     => 1,
        ])->assertOk();

        postJson(route('api.v1.shop.cart.items.store'), [
            'product_delivery_option_uuid' => $deliveryOption2->uuid,
            'quantity'                     => 1,
        ])->assertOk();

        $response = postJson(route('api.v1.shop.checkout'), [
            'payment_method' => 'bank_transfer',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items']);

        // Error message should mention both products
        $errorMessage = $response->json('errors.items.0');
        expect($errorMessage)->toContain('Course A');
        expect($errorMessage)->toContain('Course B');
    });
});
