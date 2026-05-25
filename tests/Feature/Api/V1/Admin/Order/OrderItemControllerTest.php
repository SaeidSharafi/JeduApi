<?php

declare(strict_types=1);

use App\Enums\Order\OrderItemPaymentTypeEnum;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductDeliveryOption;
use App\Models\Vendor;

use function Pest\Laravel\getJson;

uses(Tests\Support\Traits\AuthTestTrait::class);

describe('Admin OrderItemController', function (): void {
    beforeEach(function (): void {
        $this->vendor         = Vendor::factory()->create();
        $this->deliveryOption = ProductDeliveryOption::factory()->create([
            'price'             => 1000,
            'prepayment_amount' => 200,
            'sku'               => 'SKU-123',
        ]);
        $this->order = Order::factory()->create([
            'subtotal'        => 0,
            'discount_amount' => 0,
            'tax_amount'      => 0,
        ]);
        $this->orderItem = OrderItem::factory()->create([
            'order_id'                   => $this->order->id,
            'product_delivery_option_id' => $this->deliveryOption->id,
            'vendor_id'                  => $this->vendor->id,
            'name'                       => 'Test Product',
            'sku'                        => 'SKU-123',
            'price'                      => 1000,
            'prepayment_amount'          => 200,
            'discount_amount'            => 0,
            'tax_amount'                 => 0,
            'total'                      => 1000,
            'payment_type'               => OrderItemPaymentTypeEnum::PRE_PAYMENT,
        ]);
    });

    it('returns order item details (show)', function (): void {
        $this->authorized_user([App\Enums\PermissionEnum::ORDER_VIEW->value]);
        $response = getJson("/api/v1/admin/order/{$this->order->id}/order-item/{$this->orderItem->id}");
        $response->assertOk();
        $response->assertJsonStructure([
            'message',
            'data' => [
                'id', 'Order_id', 'product_delivery_option_id', 'discount_amount', 'qty_ordered', 'tax_amount',
                'name', 'sku', 'price', 'total', 'payment_type' => ['value', 'label'], 'prepayment_amount',
                'qty_refunded', 'total_refunded', 'status'      => ['value', 'label'], 'vendor', 'product_snapshot',
            ],
            'metadata',
        ]);
        $response->assertJson([
            'data' => [
                'id'                         => $this->orderItem->id,
                'Order_id'                   => $this->order->id,
                'product_delivery_option_id' => $this->deliveryOption->id,
                'name'                       => 'Test Product',
                'sku'                        => 'SKU-123',
                'price'                      => 1000,
                'total'                      => 1000,
                'payment_type'               => [
                    'value' => OrderItemPaymentTypeEnum::PRE_PAYMENT->value,
                ],
                'prepayment_amount' => 200,
            ],
        ]);
    });

    it('returns all order items for an order (index)', function (): void {
        $this->authorized_user([App\Enums\PermissionEnum::ORDER_VIEW_ANY->value]);
        $response = getJson("/api/v1/admin/order/{$this->order->id}/order-item");
        $response->assertOk();
        $response->assertJsonStructure([
            'message',
            'data' => [
                ['id', 'Order_id', 'product_delivery_option_id', 'discount_amount', 'qty_ordered', 'tax_amount',
                    'name', 'sku', 'price', 'total', 'payment_type' => ['value', 'label'], 'prepayment_amount',
                    'qty_refunded', 'total_refunded', 'status'      => ['value', 'label'], 'vendor', 'product_snapshot'],
            ],
            'metadata',
        ]);
        $response->assertJsonFragment([
            'id'                         => $this->orderItem->id,
            'Order_id'                   => $this->order->id,
            'product_delivery_option_id' => $this->deliveryOption->id,
            'name'                       => 'Test Product',
            'sku'                        => 'SKU-123',
            'price'                      => 1000,
            'total'                      => 1000,
            'prepayment_amount'          => 200,
        ]);
    });
});
