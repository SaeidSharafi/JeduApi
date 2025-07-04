<?php

declare(strict_types=1);

use App\Data\ProductDeliveryOption\ProductDeliveryOptionShowData;
use App\Enums\PermissionEnum;
use App\Enums\OrderStatusEnum;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Testing\Fluent\AssertableJson;
use Tests\AuthTestTrait;

uses(AuthTestTrait::class);

describe('OrderController', function () {
    // 1. Index filters and sorts
    it('can filter and sort orders', function () {
        $this->authorized_user([
            PermissionEnum::ORDER_VIEW_ANY->value,
        ]);
        $user = User::factory()->create();
        $orders = Order::factory()->count(3)
            ->sequence(
                ['increment_id' => 1001],
                ['increment_id' => 1002],
                ['increment_id' => 1003]
            )
            ->create([
                'customer_first_name' => 'John',
                'customer_last_name'  => 'Doe',
                'customer_email'      => 'john@example.com',
                'customer_phone'      => '+1234567890',
                'status'              => OrderStatusEnum::COMPLETED,
                'customer_id'         => $user->id,
            ]);
        $order = $orders->first();
        $product = \App\Models\ProductDeliveryOption::factory()->create([
            'name' => 'Widget',
            'sku'  => 'SKU123',
        ]);
        $order->items()->create([
            'product_delivery_option_id' => $product->id,
            'quantity'                   => 1,
            'name'                       => $product->name,
            'sku'                        => $product->sku,
            'vendor_id'                  => \App\Models\Vendor::factory()->create()->id,
            'product_data_snapshot_json' => [],
            'price'                      => 1000,
            'discount_amount'            => 0,
            'tax_amount'                 => 0,
            'total'                      => 1000,
            'status'                     => \App\Enums\OrderItemStatusEnum::ACTIVE,
        ]);
        // Filter by status
        $response = $this->getJson(route('api.v1.admin.order.index',
            ['filter[status]' => OrderStatusEnum::COMPLETED->value]));
        $response->assertStatus(200)->assertJsonPath('data.data.0.status.value', OrderStatusEnum::COMPLETED->value);
        // Filter by customer_first_name
        $response = $this->getJson(route('api.v1.admin.order.index', ['filter[customer_first_name]' => 'John']));
        $response->assertStatus(200)->assertJsonPath('data.data.0.customer_first_name', 'John');
        // Filter by customer_last_name
        $response = $this->getJson(route('api.v1.admin.order.index', ['filter[customer_last_name]' => 'Doe']));
        $response->assertStatus(200)->assertJsonPath('data.data.0.customer_last_name', 'Doe');
        // Filter by customer_email
        $response = $this->getJson(route('api.v1.admin.order.index', ['filter[customer_email]' => 'john@example.com']));
        $response->assertStatus(200)->assertJsonPath('data.data.0.customer_email', 'john@example.com');
        // Filter by customer_phone
        $response = $this->getJson(route('api.v1.admin.order.index', ['filter[customer_phone]' => '+1234567890']));
        $response->assertStatus(200)->assertJsonPath('data.data.0.customer_phone', '+1234567890');
        // Filter by increment_id
        $response = $this->getJson(route('api.v1.admin.order.index', ['filter[increment_id]' => '1001']));
        $response->assertStatus(200)->assertJsonPath('data.data.0.increment_id', '1001');
        // Filter by product_name (partial)
        $response = $this->getJson(route('api.v1.admin.order.index', ['filter[product_name]' => 'Wid']));
        $response->assertStatus(200)
            ->assertJson(
                fn(AssertableJson $json) => $json->where('data.data.0.items.0.name', 'Widget')->etc()
            );
        // Filter by product_sku (partial)
        $response = $this->getJson(route('api.v1.admin.order.index', ['filter[product_sku]' => 'SKU']));
        $response->assertStatus(200)->assertJson(fn(AssertableJson $json) => $json->where('data.data.0.items.0.sku',
            'SKU123')->etc());
        // Sort by created_at desc
        $response = $this->getJson(route('api.v1.admin.order.index', ['sort' => '-created_at']));
        $response->assertStatus(200);
    });

    // 2. CRUD and permissions
    describe('CRUD operations with permissions', function () {
        it('can create an order with permissions', function () {
            $this->authorized_user([
                PermissionEnum::ORDER_CREATE->value,
            ]);
            $user = User::factory()->create();
            $product = \App\Models\ProductDeliveryOption::factory()->create();

            $orderData = [
                'status'              => OrderStatusEnum::PENDING->value,
                'customer_id'         => $user->id,
                'items'               => [
                    [
                        'product_delivery_option_id' => $product->id,
                        'discount_amount'            => 0,
                        'quantity'                   => 2,
                        'tax_amount'                 => 0,
                    ]
                ],
                'applied_coupon_code' => 'TEST_COUPON',
                'admin_notes'         => 'Test order creation',
            ];
            $product->load('product.productable', 'product.vendor', 'product.term');
            $response = $this->postJson(route('api.v1.admin.order.store'), $orderData);
            $response->assertStatus(201)
                ->assertJsonStructure([
                    'message',
                    'data' => [
                        'id', 'increment_id', 'status', 'customer_id', 'customer_email', 'customer_phone',
                        'customer_first_name', 'customer_last_name', 'customer_snapshot', 'subtotal',
                        'discount_amount', 'tax_amount', 'grand_total', 'applied_coupon_code', 'admin_notes',
                        'items', 'created_at', 'updated_at'
                    ],
                    'metadata'
                ])
                ->assertJsonPath('data.status.value', OrderStatusEnum::PENDING->value)
                ->assertJsonPath('data.customer_id', $user->id)
                ->assertJsonPath('data.applied_coupon_code', 'TEST_COUPON')
                ->assertJsonPath('data.admin_notes', 'Test order creation')
                ->assertJsonPath('data.items.0.product_delivery_option_id', $product->id)
                ->assertJson(function (AssertableJson $json) use ($product) {
                    $json->where('data.items.0.product_data_snapshot_json.id', $product->id)
                        ->where('data.items.0.product_data_snapshot_json.name', $product->name)
                        ->where('data.items.0.product_data_snapshot_json.sku', $product->sku)
                        ->where('data.items.0.product_data_snapshot_json.price', $product->price)
                        ->etc();
                });
            $orderId = $response->json('data.id');
            $this->assertDatabaseHas('orders', [
                'id'                  => $orderId,
                'customer_id'         => $user->id,
                'applied_coupon_code' => 'TEST_COUPON',
                'admin_notes'         => 'Test order creation',
            ]);
            $this->assertDatabaseHas('order_items', [
                'order_id'                   => $orderId,
                'product_delivery_option_id' => $product->id,
                'quantity'                   => 2,
            ]);
        });

        it('can show an order with permissions', function () {
            $this->authorized_user([
                PermissionEnum::ORDER_VIEW->value,
            ]);
            $user = User::factory()->create();
            $order = Order::factory()->create([
                'customer_id'         => $user->id,
                'applied_coupon_code' => 'SHOW_COUPON',
                'admin_notes'         => 'Show order',
            ]);
            $product = \App\Models\ProductDeliveryOption::factory()->create();

            $item = $order->items()->create([
                'product_delivery_option_id' => $product->id,
                'quantity'                   => 1,
                'name'                       => 'Test Product',
                'sku'                        => 'SKU_SHOW',
                'vendor_id'                  => \App\Models\Vendor::factory()->create()->id,
                'product_data_snapshot_json' => [],
                'price'                      => 1000,
                'discount_amount'            => 100,
                'tax_amount'                 => 0,
                'total'                      => 900,
                'status'                     => \App\Enums\OrderItemStatusEnum::ACTIVE,
            ]);
            $response = $this->getJson(route('api.v1.admin.order.show', ['order' => $order->id]));
            $response->assertStatus(200)
                ->assertJsonStructure([
                    'message',
                    'data' => [
                        'id', 'increment_id', 'status', 'customer_id', 'customer_email', 'customer_phone',
                        'customer_first_name', 'customer_last_name', 'customer_snapshot', 'subtotal',
                        'discount_amount', 'tax_amount', 'grand_total', 'applied_coupon_code', 'admin_notes',
                        'items', 'created_at', 'updated_at'
                    ],
                    'metadata'
                ])
                ->assertJsonPath('data.id', $order->id)
                ->assertJsonPath('data.applied_coupon_code', 'SHOW_COUPON')
                ->assertJsonPath('data.admin_notes', 'Show order')
                ->assertJsonPath('data.items.0.name', 'Test Product')
                ->assertJsonPath('data.items.0.sku', 'SKU_SHOW');
        });

        it('can update an order with permissions', function () {
            $this->authorized_user([
                PermissionEnum::ORDER_UPDATE->value,
            ]);
            $user = User::factory()->create();
            $order = Order::factory()->create([
                'customer_id'         => $user->id, 'status' => OrderStatusEnum::PENDING->value,
                'applied_coupon_code' => 'OLD_COUPON', 'admin_notes' => 'Old notes',
            ]);
            $updateData = [
                'status' => OrderStatusEnum::COMPLETED->value,
            ];
            $response = $this->putJson(route('api.v1.admin.order.update', ['order' => $order->id]), $updateData);
            $response->assertStatus(200)
                ->assertJsonStructure([
                    'message',
                    'data' => [
                        'id', 'increment_id', 'status', 'customer_id', 'customer_email', 'customer_phone',
                        'customer_first_name', 'customer_last_name', 'customer_snapshot', 'subtotal',
                        'discount_amount', 'tax_amount', 'grand_total', 'applied_coupon_code', 'admin_notes',
                        'items', 'created_at', 'updated_at'
                    ],
                    'metadata'
                ])
                ->assertJsonPath('data.status.value', OrderStatusEnum::COMPLETED->value)
                ->assertJsonPath('data.id', $order->id);
            $this->assertDatabaseHas('orders', [
                'id'     => $order->id,
                'status' => OrderStatusEnum::COMPLETED->value,
            ]);
        });

        it('can delete an order with permissions', function () {
            $this->authorized_user([
                PermissionEnum::ORDER_DELETE->value,
            ]);
            $user = User::factory()->create();
            $order = Order::factory()->create(['customer_id' => $user->id]);
            $product = \App\Models\ProductDeliveryOption::factory()->create();
            $item = $order->items()->create([
                'product_delivery_option_id' => $product->id,
                'quantity'                   => 1,
                'name'                       => 'Delete Product',
                'sku'                        => 'SKU_DELETE',
                'vendor_id'                  => \App\Models\Vendor::factory()->create()->id,
                'product_data_snapshot_json' => [],
                'price'                      => 1000,
                'discount_amount'            => 0,
                'tax_amount'                 => 0,
                'total'                      => 1000,
                'status'                     => \App\Enums\OrderItemStatusEnum::ACTIVE,
            ]);
            $response = $this->deleteJson(route('api.v1.admin.order.destroy', ['order' => $order->id]));
            $response->assertStatus(204);
            $this->assertDatabaseMissing('orders', [
                'id' => $order->id,
            ]);
            $this->assertDatabaseMissing('order_items', [
                'order_id' => $order->id,
            ]);
        });
    });

    it('cannot access CRUD routes without permissions', function () {
        $this->unauthorized_user();
        $user = User::factory()->create();
        $product = \App\Models\ProductDeliveryOption::factory()->create();
        $order = Order::factory()->create(['customer_id' => $user->id]);
        // Index
        $response = $this->getJson(route('api.v1.admin.order.index'));
        $response->assertStatus(403);
        // Show
        $response = $this->getJson(route('api.v1.admin.order.show', ['order' => $order->id]));
        $response->assertStatus(403);
        // Store
        $response = $this->postJson(route('api.v1.admin.order.store'), [
            'status'      => OrderStatusEnum::PENDING->value,
            'customer_id' => $user->id,
            'items'       => [
                [
                    'product_delivery_option_id' => $product->id,
                    'discount_amount'            => 0,
                    'quantity'                   => 1,
                    'tax_amount'                 => 0,
                ]
            ],
        ]);
        $response->assertStatus(403);
        // Update
        $response = $this->putJson(route('api.v1.admin.order.update', ['order' => $order->id]),
            ['status' => OrderStatusEnum::COMPLETED->value]);
        $response->assertStatus(403);
        // Delete
        $response = $this->deleteJson(route('api.v1.admin.order.destroy', ['order' => $order->id]));
        $response->assertStatus(403);
    });

    // 3. Validation rules
    it('validates required fields on create', function () {
        $this->authorized_user([
            PermissionEnum::ORDER_CREATE->value,
        ]);
        $response = $this->postJson(route('api.v1.admin.order.store'), []);
        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'status',
                'customer_id',
                'items',
            ]);
    });

    it('validates item fields on create', function () {
        $this->authorized_user([
            PermissionEnum::ORDER_CREATE->value,
        ]);
        $user = User::factory()->create();
        $data = [
            'status'      => OrderStatusEnum::PENDING->value,
            'customer_id' => $user->id,
            'items'       => [
                [
                    // missing product_delivery_option_id, discount_amount
                ]
            ],
        ];
        $response = $this->postJson(route('api.v1.admin.order.store'), $data);
        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'items.0.product_delivery_option_id',
                'items.0.discount_amount',
            ]);
    });
});

