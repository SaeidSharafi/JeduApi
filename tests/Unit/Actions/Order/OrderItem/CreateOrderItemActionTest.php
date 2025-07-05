<?php

use App\Actions\Order\OrderItem\CreateOrderItemAction;
use App\Data\Order\OrderItemCreateData;
use App\Enums\OrderItemPaymentTypeEnum;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductDeliveryOption;
use App\Models\Vendor;
use Illuminate\Validation\ValidationException;
use function Pest\Laravel\assertDatabaseHas;

describe('CreateOrderItemAction', function () {
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
        $this->action = app(CreateOrderItemAction::class);
    });

    it('creates an order item successfully', function () {
        $data = new OrderItemCreateData(
            product_delivery_option_id: $this->deliveryOption->id,
            payment_type: OrderItemPaymentTypeEnum::FULL_PAYMENT->value,
            discount_amount: 100,
            qty_ordered: 2,
            tax_amount: 50,
        );
        $orderItem = $this->action->handle($data, $this->order);
        expect($orderItem)->toBeInstanceOf(OrderItem::class)
            ->and($orderItem->qty_ordered)->toBe(2)
            ->and($orderItem->total)->toBe(2000)
            ->and($orderItem->discount_amount)->toBe(100)
            ->and($orderItem->tax_amount)->toBe(50)
            ->and($orderItem->payment_type->value)->toBe(OrderItemPaymentTypeEnum::FULL_PAYMENT->value);
        assertDatabaseHas('order_items', [
            'id' => $orderItem->id,
            'order_id' => $this->order->id,
        ]);
    });

    it('throws validation exception if duplicate purchase', function () {
        $data = new OrderItemCreateData(
            product_delivery_option_id: $this->deliveryOption->id,
            payment_type: OrderItemPaymentTypeEnum::FULL_PAYMENT->value,
            discount_amount: 0,
            qty_ordered: 1,
            tax_amount: 0,
        );
        $this->action->handle($data, $this->order);
        $this->expectException(ValidationException::class);
        $this->action->handle($data, $this->order);
    });

    it('throws exception if delivery option does not exist', function () {
        $data = new OrderItemCreateData(
            product_delivery_option_id: 999999,
            payment_type: OrderItemPaymentTypeEnum::FULL_PAYMENT->value,
            discount_amount: 0,
            qty_ordered: 1,
            tax_amount: 0,
        );
        $this->expectException(InvalidArgumentException::class);
        $this->action->handle($data, $this->order);
    });

});

