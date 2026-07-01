<?php

declare(strict_types=1);

use App\Enums\Content\PublicationStatusEnum;
use App\Enums\Order\OrderItemPaymentTypeEnum;
use App\Enums\Order\OrderStatusEnum;
use App\Enums\Payment\PaymentMethodEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Enums\PermissionEnum;
use App\Models\Order;
use App\Models\ProductDeliveryOption;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Tests\Support\Traits\AuthTestTrait;

uses(AuthTestTrait::class);

describe('OrderController', function (): void {
    // 1. Index filters and sorts
    it('can filter and sort orders', function (): void {
        $this->authorized_user([PermissionEnum::ORDER_VIEW_ANY->value]);

        $customer = User::factory()->create();

        // Create a fully paid order
        Order::factory()->create([
            'customer_id'         => $customer->id,
            'status'              => OrderStatusEnum::COMPLETED->value,
            'increment_id'        => 1001,
            'customer_first_name' => 'John',
            'customer_email'      => 'john@example.com',
        ]);

        // Create a partially paid order
        $partialOrder = Order::factory()->create([
            'customer_id' => $customer->id,
            'status'      => OrderStatusEnum::PROCESSING->value,
        ]);

        $product = ProductDeliveryOption::factory()->create(['name' => 'Widget', 'sku' => 'SKU123']);
        $partialOrder->items()->create([
            'product_delivery_option_id' => $product->id,
            'qty_ordered'                => 1,
            'name'                       => 'Widget',
            'sku'                        => 'SKU123',
            'vendor_id'                  => App\Models\Vendor::factory()->create()->id,
            'product_data_snapshot_json' => [],
            'price'                      => 1000, 'total' => 1000, 'discount_amount' => 0, 'tax_amount' => 0,
            'payment_type'               => OrderItemPaymentTypeEnum::PRE_PAYMENT->value,
        ]);
        $partialOrder->payments()->create([
            'amount'      => 20000,
            'method'      => PaymentMethodEnum::MELLAT_GATEWAY->value,
            'status'      => PaymentStatusEnum::FAILED->value,
            'admin_notes' => 'payment failed',
            'created_by'  => null,
            'customer_id' => $customer->id,
        ]);
        // Filter by status
        $this->getJson(route('api.v1.admin.orders.index',
            ['filter[payment_status]' => PaymentStatusEnum::FAILED->value]))
            ->assertOk()->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.payments.0.status.value', PaymentStatusEnum::FAILED->value);

        $this->getJson(route('api.v1.admin.orders.index', ['filter[status]' => OrderStatusEnum::COMPLETED->value]))
            ->assertOk()->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.status.value', OrderStatusEnum::COMPLETED->value);

        // Filter by increment_id
        $this->getJson(route('api.v1.admin.orders.index', ['filter[increment_id]' => '1001']))
            ->assertOk()->assertJsonCount(1, 'data.data')->assertJsonPath('data.data.0.increment_id', '1001');

        // Filter by product_name (partial)
        $this->getJson(route('api.v1.admin.orders.index', ['filter[product_name]' => 'Wid']))
            ->assertOk()->assertJsonCount(1, 'data.data')->assertJsonPath('data.data.0.items.0.name', 'Widget');

        // Filter by product_sku (partial)
        $this->getJson(route('api.v1.admin.orders.index', ['filter[product_sku]' => 'SKU']))
            ->assertOk()->assertJsonCount(1, 'data.data')->assertJsonPath('data.data.0.items.0.sku', 'SKU123');
    });

    // 2. CRUD and permissions
    describe('CRUD operations with permissions', function (): void {
        beforeEach(function (): void {
            $this->product = App\Models\Product::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]);
        }
        );
        it('can create an order with permissions (full payment option)', function (): void {
            $this->authorized_user([PermissionEnum::ORDER_CREATE->value]);
            $user = User::factory()->create();

            $product = ProductDeliveryOption::factory()->create([
                'product_id' => $this->product->id,
                'status'     => PublicationStatusEnum::PUBLISHED,
                'price'      => 50000,
            ]);

            $orderData = [
                'status'      => OrderStatusEnum::PENDING->value,
                'customer_id' => $user->id,
                'items'       => [
                    [
                        'product_delivery_option_id' => $product->id,
                        'payment_type'               => OrderItemPaymentTypeEnum::FULL_PAYMENT->value, // Pay in full
                        'discount_amount'            => 0,
                        'qty_ordered'                => 1,
                        'tax_amount'                 => 0,
                    ],
                ],
                'admin_notes' => 'Test order creation',
            ];

            $response = $this->postJson(route('api.v1.admin.orders.store'), $orderData);

            $response->assertStatus(201)
                ->assertJsonStructure([
                    'message',
                    'data' => [
                        'id', 'increment_id', 'status', 'payment_status', 'customer_id',
                        'total_item_count', 'total_qty_ordered', 'grand_total', 'total_paid',
                        'balance_due', 'items',
                    ],
                ])
                ->assertJsonPath('data.grand_total', 50000)
                ->assertJsonPath('data.total_item_count', 1)
                ->assertJsonPath('data.total_qty_ordered', 1);

            $this->assertDatabaseHas('orders', [
                'customer_id' => $user->id,
                'status'      => OrderStatusEnum::PENDING->value,
                'grand_total' => 50000,
            ]);

            $this->assertDatabaseHas('order_items', [
                'order_id'                   => $response->json('data.id'),
                'product_delivery_option_id' => $product->id,
                'qty_ordered'                => 1,
                'price'                      => 50000,
                'total'                      => 50000,
                'payment_type'               => OrderItemPaymentTypeEnum::FULL_PAYMENT->value,
            ]);

            $this->assertDatabaseHas('enrollments', [
                'order_id'          => $response->json('data.id'),
                'customer_id'       => $user->id,
                'enrollment_status' => App\Enums\EnrollmentStatusEnum::AWAITING_PAYMENT,
            ]);

        });

        it('can create a partially paid order with permissions', function (): void {
            $this->authorized_user([PermissionEnum::ORDER_CREATE->value]);
            $user    = User::factory()->create();
            $product = ProductDeliveryOption::factory()->create([
                'product_id'              => $this->product->id,
                'status'                  => PublicationStatusEnum::PUBLISHED,
                'price'                   => 100000,
                'prepayment_amount'       => 20000,
                'is_prepayment_available' => true,
            ]);

            $orderData = [
                'status'      => OrderStatusEnum::PROCESSING->value,
                'customer_id' => $user->id,
                'items'       => [
                    [
                        'product_delivery_option_id' => $product->id,
                        'payment_type'               => OrderItemPaymentTypeEnum::PRE_PAYMENT->value, // Pay deposit
                        'discount_amount'            => 0, 'qty_ordered' => 1,
                        'tax_amount'                 => 0,
                    ],
                ],
            ];

            $response = $this->postJson(route('api.v1.admin.orders.store'), $orderData);

            $response->assertStatus(201)
                ->assertJsonPath('data.grand_total', 20000)
                ->assertJsonPath('data.balance_due', 100000);
        });

        it('returns OrderData computed fields correctly for pre-payment order', function (): void {
            $this->authorized_user([PermissionEnum::ORDER_CREATE->value]);
            $user    = User::factory()->create();
            
            // Featured price: 85,000 (15% discount from 100,000)
            $product = ProductDeliveryOption::factory()->create([
                'product_id'              => $this->product->id,
                'status'                  => PublicationStatusEnum::PUBLISHED,
                'price'                   => 100000,
                'featured_price'          => 85000,
                'prepayment_amount'       => 20000,
                'is_prepayment_available' => true,
            ]);

            $orderData = [
                'status'      => OrderStatusEnum::PROCESSING->value,
                'customer_id' => $user->id,
                'items'       => [
                    [
                        'product_delivery_option_id' => $product->id,
                        'payment_type'               => OrderItemPaymentTypeEnum::PRE_PAYMENT->value,
                        'discount_amount'            => 0,
                        'qty_ordered'                => 1,
                        'tax_amount'                 => 0,
                    ],
                ],
            ];

            $response = $this->postJson(route('api.v1.admin.orders.store'), $orderData);

            $response->assertStatus(201)
                ->assertJsonPath('data.grand_total', 20000)
                ->assertJsonPath('data.full_value_grand_total', 100000) // Original price
                ->assertJsonPath('data.total_product_discount', 0) // NO product discount for pre-payment
                ->assertJsonPath('data.total_cart_discount', 0)
                ->assertJsonPath('data.total_discount', 0);
        });

        it('returns OrderItemData computed fields correctly for featured price and coupon', function (): void {
            $this->authorized_user([PermissionEnum::ORDER_CREATE->value]);
            $user = User::factory()->create();
            
            // Featured price: 85,000 (15,000 product discount)
            $product = ProductDeliveryOption::factory()->create([
                'product_id'     => $this->product->id,
                'status'         => PublicationStatusEnum::PUBLISHED,
                'price'          => 100000,
                'is_featured'    => true,
                'featured_price' => 85000,
                'featured_price_start_date' => now()->subDay(),
                'featured_price_end_date' => now()->addDay(),
            ]);

            // Create coupon: 10% off cart
            $couponPromotion = App\Models\DiscountPromotion::factory()->create([
                'type'      => 'cart_checkout',
                'is_active' => true,
            ]);
            
            $couponPromotion->rules()->create([
                'type'          => 'action',
                'handler'       => 'apply_percentage_off',
                'configuration' => ['percentage' => 10],
            ]);
            
            $coupon = App\Models\DiscountCoupon::factory()->create([
                'discount_promotion_id' => $couponPromotion->id,
                'code'                  => 'TEST10',
            ]);

            $orderData = [
                'status'              => OrderStatusEnum::PENDING->value,
                'customer_id'         => $user->id,
                'applied_coupon_code' => 'TEST10',
                'items'               => [
                    [
                        'product_delivery_option_id' => $product->id,
                        'payment_type'               => OrderItemPaymentTypeEnum::FULL_PAYMENT->value,
                        'qty_ordered'                => 1,
                    ],
                ],
            ];

            $response = $this->postJson(route('api.v1.admin.orders.store'), $orderData);

            $response->assertStatus(201);
            
            $item = $response->json('data.items.0');
            
            // Product discount: 100,000 - 85,000 = 15,000
            // Cart discount (10% of 85,000): 8,500
            // Final price: 85,000 - 8,500 = 76,500
            expect($item['original_price'])->toBe(100000)
                ->and($item['price'])->toBe(100000) // ALWAYS base price
                ->and($item['product_discount_amount'])->toBe(15000)
                ->and($item['discount_amount'])->toBe(8500) // Cart discount
                ->and($item['total_discount_amount'])->toBe(23500) // 15,000 + 8,500
                ->and($item['total'])->toBe(76500); // 100,000 - 23,500
        });

        it('can show an order with permissions', function (): void {
            $this->authorized_user([PermissionEnum::ORDER_VIEW->value]);
            $order = Order::factory()->create([
                'grand_total' => 5000,
            ]);

            $response = $this->getJson(route('api.v1.admin.orders.show', ['order' => $order->id]));
            $response->assertStatus(200)
                ->assertJsonStructure([
                    'message',
                    'data' => ['id', 'payment_status', 'grand_total', 'balance_due'],
                ])
                ->assertJsonPath('data.id', $order->id);
        });

        it('can update an order with permissions', function (): void {
            $this->authorized_user([
                PermissionEnum::ORDER_UPDATE->value,
            ]);
            $user  = User::factory()->create();
            $order = Order::factory()->create([
                'customer_id'            => $user->id,
                'status'                 => OrderStatusEnum::PENDING->value,
                'applied_coupon_code'    => 'OLD_COUPON',
                'admin_notes'            => 'Old notes',
                'grand_total'            => 1000,
                'full_value_grand_total' => 1000,
            ]);
            $prdouct_delivery_option = ProductDeliveryOption::factory()->create([
                'price' => 1000,
            ]);
            $order->items()->create([
                'product_delivery_option_id' => $prdouct_delivery_option->id,
                'qty_ordered'                => 1,
                'name'                       => 'Test Product',
                'sku'                        => 'TEST_SKU',
                'vendor_id'                  => App\Models\Vendor::factory()->create()->id,
                'product_data_snapshot_json' => [],
                'price'                      => 1000,
                'total'                      => 1000,
                'discount_amount'            => 0,
                'tax_amount'                 => 0,
                'payment_type'               => OrderItemPaymentTypeEnum::FULL_PAYMENT->value,
            ]);
            $order->items->each(function ($item) use ($user): void {
                $item->enrollment()->create([
                    'customer_id'                => $user->id,
                    'order_id'                   => $item->order_id,
                    'product_delivery_option_id' => $item->product_delivery_option_id,
                    'enrollment_status'          => App\Enums\EnrollmentStatusEnum::PENDING_PROVISIONING,
                ]);
            });

            $updateData = [
                'status' => OrderStatusEnum::CANCELLED->value,
            ];
            Event::fake([
                App\Events\OrderStatusUpdatedEvent::class,
            ]);
            $response = $this->putJson(route('api.v1.admin.orders.update', ['order' => $order->id]), $updateData);
            $response->assertStatus(200)
                ->assertJsonStructure([
                    'message',
                    'data' => [
                        'id', 'increment_id', 'status', 'customer_id', 'customer_email', 'customer_phone',
                        'customer_first_name', 'customer_last_name', 'customer_snapshot', 'subtotal',
                        'discount_amount', 'tax_amount', 'grand_total', 'applied_coupon_code', 'admin_notes',
                        'items', 'created_at', 'updated_at',
                    ],
                    'metadata',
                ])
                ->assertJsonPath('data.status.value', OrderStatusEnum::CANCELLED->value)
                ->assertJsonPath('data.payment_status.value', PaymentStatusEnum::PENDING->value)
                ->assertJsonPath('data.id', $order->id);
            Event::assertDispatched(App\Events\OrderStatusUpdatedEvent::class);
            $this->assertDatabaseHas('orders', [
                'id'     => $order->id,
                'status' => OrderStatusEnum::CANCELLED->value,
            ]);
            $order->items->each(function ($item) use ($user): void {
                $this->assertDatabaseHas('enrollments', [
                    'customer_id'                => $user->id,
                    'order_id'                   => $item->order_id,
                    'product_delivery_option_id' => $item->product_delivery_option_id,
                    'enrollment_status'          => App\Enums\EnrollmentStatusEnum::CANCELLED,
                ]);
            });

        });

        it('can delete an order with permissions', function (): void {
            $this->authorized_user([
                PermissionEnum::ORDER_DELETE->value,
            ]);
            $user    = User::factory()->create();
            $order   = Order::factory()->create(['customer_id' => $user->id, 'status' => OrderStatusEnum::PENDING]);
            $product = ProductDeliveryOption::factory()->create();
            $item    = App\Models\OrderItem::factory()->create(
                [
                    'order_id'                   => $order->id,
                    'product_delivery_option_id' => $product->id,
                    'qty_ordered'                => 1,
                    'name'                       => 'Delete Product',
                    'sku'                        => 'SKU_DELETE',
                    'vendor_id'                  => App\Models\Vendor::factory()->create()->id,
                    'product_data_snapshot_json' => [],
                    'price'                      => 1000,
                    'discount_amount'            => 0,
                    'tax_amount'                 => 0,
                    'total'                      => 1000,
                    'status'                     => App\Enums\Order\OrderItemStatusEnum::PENDING,
                ]
            );
            $item->enrollment()->create([
                'customer_id'                => $user->id,
                'order_id'                   => $item->order_id,
                'product_delivery_option_id' => $item->product_delivery_option_id,
                'enrollment_status'          => App\Enums\EnrollmentStatusEnum::PENDING_PROVISIONING,
            ]);
            $response = $this->deleteJson(route('api.v1.admin.orders.destroy', ['order' => $order->id]));
            $response->assertStatus(204);
            $this->assertDatabaseMissing('orders', [
                'id' => $order->id,
            ]);
            $this->assertDatabaseMissing('order_items', [
                'order_id' => $order->id,
            ]);

            $this->assertDatabaseMissing('enrollments', ['order_id' => $order->id]);

        });
        it('can not delete an order with payments', function (): void {
            $this->authorized_user([
                PermissionEnum::ORDER_DELETE->value,
            ]);
            $user  = User::factory()->create();
            $order = Order::factory()->create(['customer_id' => $user->id, 'status' => OrderStatusEnum::PENDING])
                ->fresh();
            $product = ProductDeliveryOption::factory()->create()->fresh();
            $item    = App\Models\OrderItem::factory()->create(
                [
                    'order_id'                   => $order->id,
                    'product_delivery_option_id' => $product->id,
                    'qty_ordered'                => 1,
                    'name'                       => 'Delete Product',
                    'sku'                        => 'SKU_DELETE',
                    'vendor_id'                  => App\Models\Vendor::factory()->create()->id,
                    'product_data_snapshot_json' => [],
                    'price'                      => 1000,
                    'discount_amount'            => 0,
                    'tax_amount'                 => 0,
                    'total'                      => 1000,
                    'status'                     => App\Enums\Order\OrderItemStatusEnum::COMPLETED,
                ]
            );
            $order->payments()->create([
                'amount'      => 1000,
                'method'      => PaymentMethodEnum::MELLAT_GATEWAY->value,
                'status'      => PaymentStatusEnum::COMPLETED->value,
                'admin_notes' => 'Test payment',
                'created_by'  => null,
                'customer_id' => $user->id,
            ]);
            $item->enrollment()->create([
                'customer_id'                => $user->id,
                'order_id'                   => $item->order_id,
                'product_delivery_option_id' => $item->product_delivery_option_id,
                'enrollment_status'          => App\Enums\EnrollmentStatusEnum::PENDING_PROVISIONING,
            ]);
            $response = $this->deleteJson(route('api.v1.admin.orders.destroy', ['order' => $order->id]));
            $response->assertStatus(422);
            $response->assertJsonValidationErrors(
                [
                    'order' => __('messages.order.cannot_delete_order_with_payments',
                        ['order_id' => $order->increment_id]),
                ]
            );
            $this->assertDatabaseHas('orders', [
                'id' => $order->id,
            ]);
            $this->assertDatabaseHas('order_items', [
                'order_id' => $order->id,
            ]);

            $this->assertDatabaseHas('enrollments', ['order_id' => $order->id]);
            $this->assertDatabaseHas('payments', ['order_id' => $order->id]);

        });
        it('can not delete a non PENDING order', function (): void {
            $this->authorized_user([
                PermissionEnum::ORDER_DELETE->value,
            ]);
            $user  = User::factory()->create();
            $order = Order::factory()->create(['customer_id' => $user->id, 'status' => OrderStatusEnum::COMPLETED])
                ->fresh();
            $product = ProductDeliveryOption::factory()->create()->fresh();
            $item    = App\Models\OrderItem::factory()->create(
                [
                    'order_id'                   => $order->id,
                    'product_delivery_option_id' => $product->id,
                    'qty_ordered'                => 1,
                    'name'                       => 'Delete Product',
                    'sku'                        => 'SKU_DELETE',
                    'vendor_id'                  => App\Models\Vendor::factory()->create()->id,
                    'product_data_snapshot_json' => [],
                    'price'                      => 1000,
                    'discount_amount'            => 0,
                    'tax_amount'                 => 0,
                    'total'                      => 1000,
                    'status'                     => App\Enums\Order\OrderItemStatusEnum::COMPLETED,
                ]
            );
            $response = $this->deleteJson(route('api.v1.admin.orders.destroy', ['order' => $order->id]));
            $response->assertStatus(422);
            $response->assertJsonValidationErrors(
                ['order' => __('messages.order.cannot_delete_non_pending_order')]
            );
            $this->assertDatabaseHas('orders', [
                'id' => $order->id,
            ]);
            $this->assertDatabaseHas('order_items', [
                'order_id' => $order->id,
            ]);

        });
    });

    it('cannot access CRUD routes without permissions', function (): void {
        $this->unauthorized_user();
        $order = Order::factory()->create();

        $this->getJson(route('api.v1.admin.orders.index'))->assertStatus(403);
        $this->getJson(route('api.v1.admin.orders.show', ['order' => $order->id]))->assertStatus(403);
        $this->deleteJson(route('api.v1.admin.orders.destroy', ['order' => $order->id]))->assertStatus(403);
        $this->putJson(route('api.v1.admin.orders.update', ['order' => $order->id]),
            [
                'status' => OrderStatusEnum::PENDING->value,
            ]
        )->assertStatus(403);
        $this->postJson(route('api.v1.admin.orders.store'), [
            'status'      => OrderStatusEnum::PENDING->value,
            'customer_id' => User::factory()->create()->id,
            'items'       => [
                [
                    'product_delivery_option_id' => ProductDeliveryOption::factory()->create()->id,
                    'payment_type'               => OrderItemPaymentTypeEnum::FULL_PAYMENT->value,
                    'discount_amount'            => 0,
                    'qty_ordered'                => 1,
                    'tax_amount'                 => 0,
                ],
            ],
        ])->assertStatus(403);
    });

    it('validates top-level required fields on create', function (): void {
        $this->authorized_user([PermissionEnum::ORDER_CREATE->value]);
        $response = $this->postJson(route('api.v1.admin.orders.store'), []);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status', 'customer_id', 'items']);
    });

    it('validates required item fields on create', function (): void {
        $this->authorized_user([PermissionEnum::ORDER_CREATE->value]);
        $user = User::factory()->create();
        $data = [
            'status'      => OrderStatusEnum::PENDING->value,
            'customer_id' => $user->id,
            'items'       => [
                [/* item missing all required fields */],
            ],
        ];
        $response = $this->postJson(route('api.v1.admin.orders.store'), $data);
        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'items.0.product_delivery_option_id',
                'items.0.payment_type',
            ]);
    });
});
