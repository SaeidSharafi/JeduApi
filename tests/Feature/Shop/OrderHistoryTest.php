<?php

declare(strict_types=1);

use App\Enums\Content\PublicationStatusEnum;
use App\Enums\Order\OrderStatusEnum;
use App\Enums\System\MorphTypeEnum;
use App\Models\Course;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductDeliveryOption;
use App\Models\Term;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Support\Str;

use function Pest\Laravel\getJson;

uses(Tests\AuthTestTrait::class);
beforeEach(function (): void {
    $this->userA = User::factory()->create(['email' => 'user-a@example.com']);
    $this->userB = User::factory()->create(['email' => 'user-b@example.com']);
});
describe('Order History - Authorization', function (): void {
    test('user can only view their own orders via show endpoint', function (): void {
        $orderForUserA = Order::factory()->create([
            'customer_id'    => $this->userA->id,
            'increment_id'   => '100000001',
            'status'         => OrderStatusEnum::COMPLETED->value,
            'customer_email' => $this->userA->email,
            'customer_phone' => '09123456789',
            'grand_total'    => 500000,
        ]);

        $this->customer($this->userB);
        $response = getJson(route('api.v1.shop.orders.show', ['order' => $orderForUserA->increment_id]));

        $response->assertForbidden();
    });

    test('user can view their own order via show endpoint', function (): void {
        $orderForUserA = Order::factory()->create([
            'customer_id'    => $this->userA->id,
            'increment_id'   => '100000002',
            'status'         => OrderStatusEnum::COMPLETED->value,
            'customer_email' => $this->userA->email,
            'customer_phone' => '09123456789',
            'grand_total'    => 500000,
        ]);

        $this->customer($this->userA);
        $response = getJson(route('api.v1.shop.orders.show', ['order' => $orderForUserA->increment_id]));

        $response->assertOk()
            ->assertJsonPath('data.increment_id', '100000002')
            ->assertJsonPath('data.customer_email', $this->userA->email);
    });

    test('unauthenticated user cannot access order history', function (): void {
        $order = Order::factory()->create([
            'customer_id'  => $this->userA->id,
            'increment_id' => '100000001',
        ]);

        $response = getJson(route('api.v1.shop.orders.show', ['order' => $order->increment_id]));

        $response->assertUnauthorized();
    });
});

describe('Order History - Index Endpoint', function (): void {
    test('user can retrieve list of their own orders', function (): void {

        Order::factory()->count(3)->create([
            'customer_id'    => $this->userA->id,
            'customer_email' => $this->userA->email,
            'status'         => OrderStatusEnum::COMPLETED->value,
        ]);

        Order::factory()->count(2)->create([
            'customer_id'    => $this->userB->id,
            'customer_email' => $this->userB->email,
        ]);

        $this->customer($this->userA);
        $response = getJson(route('api.v1.shop.orders.index'));

        $response->assertOk()
            ->assertJsonCount(3, 'data.orders')
            ->assertJsonPath('data.meta.total', 3);

        $orders = $response->json('data.orders');
        foreach ($orders as $order) {
            expect($order['customer_email'])->toBe($this->userA->email);
        }
    });

    test('orders are sorted by creation date (newest first)', function (): void {
        $oldOrder = Order::factory()->create([
            'customer_id'  => $this->userA->id,
            'increment_id' => '100000001',
            'created_at'   => now()->subDays(5),
        ]);

        $recentOrder = Order::factory()->create([
            'customer_id'  => $this->userA->id,
            'increment_id' => '100000002',
            'created_at'   => now()->subDay(),
        ]);

        $newestOrder = Order::factory()->create([
            'customer_id'  => $this->userA->id,
            'increment_id' => '100000003',
            'created_at'   => now(),
        ]);

        $this->customer($this->userA);
        $response = getJson(route('api.v1.shop.orders.index'));

        $response->assertOk();
        $orders = $response->json('data.orders');

        expect($orders[0]['increment_id'])->toBe('100000003')
            ->and($orders[1]['increment_id'])->toBe('100000002')
            ->and($orders[2]['increment_id'])->toBe('100000001');
    });

    test('empty order list returns correct response', function (): void {
        $this->customer($this->userA);
        $response = getJson(route('api.v1.shop.orders.index'));

        $response->assertOk()
            ->assertJsonCount(0, 'data.orders')
            ->assertJsonPath('data.meta.total', 0);
    });
});

describe('Order History - Show Endpoint', function (): void {
    test('show endpoint returns complete order details with items', function (): void {
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
            'name'             => 'Introduction to Python',
        ]);

        $deliveryOption = ProductDeliveryOption::factory()->create([
            'product_id' => $product->id,
            'price'      => 500000,
            'uuid'       => Str::uuid()->toString(),
            'status'     => PublicationStatusEnum::PUBLISHED,
        ]);

        $order = Order::factory()->create([
            'customer_id'     => $this->userA->id,
            'increment_id'    => '100000001',
            'status'          => OrderStatusEnum::COMPLETED->value,
            'customer_email'  => $this->userA->email,
            'customer_phone'  => '09123456789',
            'grand_total'     => 500000,
            'subtotal'        => 500000,
            'discount_amount' => 0,
        ]);

        App\Models\OrderItem::factory()->create([
            'order_id'                   => $order->id,
            'product_delivery_option_id' => $deliveryOption->id,
            'qty_ordered'                => 1,
            'price'                      => 500000,
            'total'                      => 500000,
            'name'                       => 'Introduction to Python - Online',
            'sku'                        => 'PYT-001',
            'payment_type'               => 'full_payment',
            'status'                     => OrderStatusEnum::COMPLETED->value,
        ]);

        $this->customer($this->userA);
        $response = getJson(route('api.v1.shop.orders.show', ['order' => $order->increment_id]));

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'increment_id',
                    'status',
                    'customer_email',
                    'customer_phone',
                    'customer_first_name',
                    'customer_last_name',
                    'total_qty_ordered',
                    'total_item_count',
                    'subtotal',
                    'discount_amount',
                    'tax_amount',
                    'grand_total',
                    'total_paid',
                    'balance_due',
                    'currency_code',
                    'payment_status',
                    'applied_coupon_code',
                    'created_at',
                    'updated_at',
                    'items' => [
                        '*' => [
                            'id',
                            'order_id',
                            'name',
                            'sku',
                            'price',
                            'qty_ordered',
                            'total',
                            'discount_amount',
                            'tax_amount',
                            'payment_type',
                            'status',
                        ],
                    ],
                ],
            ])
            ->assertJsonPath('data.increment_id', '100000001')
            ->assertJsonPath('data.grand_total', 500000)
            ->assertJsonCount(1, 'data.items');
    });

    test('accessing non-existent order returns 404', function (): void {
        $this->customer($this->userA);
        $response = getJson(route('api.v1.shop.orders.show', ['order' => '999999999']));

        $response->assertNotFound();
    });
});
