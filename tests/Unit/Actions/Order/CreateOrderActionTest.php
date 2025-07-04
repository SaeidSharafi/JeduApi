<?php

use App\Actions\Order\CreateOrderAction;
use App\Data\Order\OrderCreateData;
use App\Enums\OrderItemStatusEnum;
use App\Enums\OrderStatusEnum;
use App\Events\OrderCreatedEvent;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductDeliveryOption;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

describe('CreateOrderAction', function () {
    beforeEach(function () {
        Event::fake();
    });

    it('creates an order successfully', function () {
        $user = User::factory()->create();
        $deliveryOption = ProductDeliveryOption::factory()->create();

        $data = new OrderCreateData(
            status: OrderStatusEnum::PENDING->value,
            customer_id: $user->id,
            items: [
                (object) [
                    'product_delivery_option_id' => $deliveryOption->id,
                    'quantity'                   => 2,
                    'discount_amount'            => 100,
                    'tax_amount'                 => 50,
                ],
            ],
            applied_coupon_code: null,
            admin_notes: null,
        );

        $action = new CreateOrderAction();
        $order = $action->handle($data);

        expect($order)->toBeInstanceOf(Order::class)
            ->and($order->items)->toHaveCount(1)
            ->and($order->items->first()->product_delivery_option_id)->toBe($deliveryOption->id)
            ->and($order->items->first()->status)->toBe(OrderItemStatusEnum::ACTIVE);
        \Pest\Laravel\assertDatabaseHas('orders', [
            'customer_id' => $user->id,
            'status'      => OrderStatusEnum::PENDING->value,
        ]);
        \Pest\Laravel\assertDatabaseHas('order_items', [
            'order_id'                   => $order->id,
            'product_delivery_option_id' => $deliveryOption->id,
            'quantity'                   => 2,
            'discount_amount'            => 100,
            'tax_amount'                 => 50,
        ]);
        \Pest\Laravel\assertDatabaseCount('orders', 1);
        \Pest\Laravel\assertDatabaseCount('order_items', 1);
        Event::assertDispatched(OrderCreatedEvent::class);
    });

    it('throws ValidationException if customer already purchased an item', function () {
        $user = User::factory()->create();
        $deliveryOption = ProductDeliveryOption::factory()->create();
        $order = Order::factory()->create(['customer_id' => $user->id]);
        OrderItem::factory()->create([
            'order_id'                   => $order->id,
            'product_delivery_option_id' => $deliveryOption->id,
        ]);

        $data = new OrderCreateData(
            status: OrderStatusEnum::PENDING->value,
            customer_id: $user->id,
            items: [
                (object) [
                    'product_delivery_option_id' => $deliveryOption->id,
                    'quantity'                   => 1,
                    'discount_amount'            => 0,
                    'tax_amount'                 => 0,
                ],
            ],
            applied_coupon_code: null,
            admin_notes: null,
        );

        $action = new CreateOrderAction();
        expect(fn() => $action->handle($data))->toThrow(ValidationException::class);
    });

    it('throws InvalidArgumentException if delivery option does not exist', function () {
        $user = User::factory()->create();
        $invalidDeliveryOptionId = 999999;

        $data = new OrderCreateData(
            status: OrderStatusEnum::PENDING->value,
            customer_id: $user->id,
            items: [
                (object) [
                    'product_delivery_option_id' => $invalidDeliveryOptionId,
                    'quantity'                   => 1,
                    'discount_amount'            => 0,
                    'tax_amount'                 => 0,
                ],
            ],
            applied_coupon_code: null,
            admin_notes: null,
        );

        $action = new CreateOrderAction();
        expect(fn() => $action->handle($data))->toThrow(\InvalidArgumentException::class);
    });
});
