<?php

use App\Enums\OrderItemPaymentTypeEnum;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductDeliveryOption;
use App\Models\Vendor;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

uses(\Tests\AuthTestTrait::class);

describe('Admin OrderItemController', function () {
    beforeEach(function () {
        $this->vendor = Vendor::factory()->create();
        $this->deliveryOption = ProductDeliveryOption::factory()->create([
            'price' => 1000,
            'prepayment_amount' => 200,
            'sku' => 'SKU-123',
        ]);
        $this->order = Order::factory()->create([
            'subtotal' => 0,
            'discount_amount' => 0,
            'tax_amount' => 0,
        ]);
        $this->orderItem = OrderItem::factory()->create([
            'order_id' => $this->order->id,
            'product_delivery_option_id' => $this->deliveryOption->id,
            'vendor_id' => $this->vendor->id,
            'name' => 'Test Product',
            'sku' => 'SKU-123',
            'price' => 1000,
            'prepayment_amount' => 200,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total' => 1000,
            'payment_type' => OrderItemPaymentTypeEnum::PRE_PAYMENT,
        ]);
    });

    it('returns order item details (show)', function () {
        $this->authorized_user([\App\Enums\PermissionEnum::ORDER_VIEW->value]);
        $response = getJson("/api/v1/admin/order/{$this->order->id}/order-item/{$this->orderItem->id}");
        $response->assertOk();
        $response->assertJsonStructure([
            'message',
            'data' => [
                'id', 'Order_id', 'product_delivery_option_id', 'discount_amount', 'qty_ordered', 'tax_amount',
                'name', 'sku', 'price', 'total', 'payment_type' => ['value', 'label'], 'prepayment_amount',
                'qty_refunded', 'total_refunded', 'status' => ['value', 'label'], 'vendor', 'product_snapshot'
            ],
            'metadata'
        ]);
        $response->assertJson([
            'data' => [
                'id' => $this->orderItem->id,
                'Order_id' => $this->order->id,
                'product_delivery_option_id' => $this->deliveryOption->id,
                'name' => 'Test Product',
                'sku' => 'SKU-123',
                'price' => 1000,
                'total' => 1000,
                'payment_type' => [
                    'value' => OrderItemPaymentTypeEnum::PRE_PAYMENT->value,
                ],
                'prepayment_amount' => 200,
            ]
        ]);
    });

    it('returns all order items for an order (index)', function () {
        $this->authorized_user([\App\Enums\PermissionEnum::ORDER_VIEW_ANY->value]);
        $response = getJson("/api/v1/admin/order/{$this->order->id}/order-item");
        $response->assertOk();
        $response->assertJsonStructure([
            'message',
            'data' => [
                ['id', 'Order_id', 'product_delivery_option_id', 'discount_amount', 'qty_ordered', 'tax_amount',
                'name', 'sku', 'price', 'total', 'payment_type' => ['value', 'label'], 'prepayment_amount',
                'qty_refunded', 'total_refunded', 'status' => ['value', 'label'], 'vendor', 'product_snapshot']
            ],
            'metadata'
        ]);
        $response->assertJsonFragment([
            'id' => $this->orderItem->id,
            'Order_id' => $this->order->id,
            'product_delivery_option_id' => $this->deliveryOption->id,
            'name' => 'Test Product',
            'sku' => 'SKU-123',
            'price' => 1000,
            'total' => 1000,
            'prepayment_amount' => 200,
        ]);
    });

    it('can create a new order item (store)', function () {
        $this->authorized_user([\App\Enums\PermissionEnum::ORDER_CREATE->value]);
        $product = ProductDeliveryOption::factory()->create()->fresh();
        $data = [
            'product_delivery_option_id' => $product->id,
            'payment_type' => OrderItemPaymentTypeEnum::FULL_PAYMENT->value,
            'discount_amount' => 0,
            'qty_ordered' => 2,
            'tax_amount' => 0,
        ];
        $response = postJson("/api/v1/admin/order/{$this->order->id}/order-item", $data);
        $response->assertCreated();
        $response->assertJsonStructure([
            'message',
            'data' => [
                'id', 'Order_id', 'product_delivery_option_id', 'discount_amount', 'qty_ordered', 'tax_amount',
                'name', 'sku', 'price', 'total', 'payment_type' => ['value', 'label'], 'prepayment_amount',
                'qty_refunded', 'total_refunded', 'status' => ['value', 'label'], 'vendor', 'product_snapshot'
            ],
            'metadata'
        ]);
        $response->assertJsonFragment([
            'Order_id' => $this->order->id,
            'product_delivery_option_id' => $product->id,
            'qty_ordered' => 2,
            'price' => $product->price,
            'total' => $product->price * 2,
            'payment_type' => [
                'label' => OrderItemPaymentTypeEnum::FULL_PAYMENT->translate(),
                'value' => OrderItemPaymentTypeEnum::FULL_PAYMENT->value,
            ],
        ]);
    });
});

