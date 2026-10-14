<?php

declare(strict_types=1);

use App\Actions\Admin\Order\ApproveOrderAction;
use App\Enums\Order\OrderItemPaymentTypeEnum;
use App\Enums\Order\OrderItemStatusEnum;
use App\Enums\Order\OrderStatusEnum;
use App\Enums\Payment\PaymentMethodEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Events\OrderStatusUpdatedEvent;
use App\Models\DiscountCoupon;
use App\Models\DiscountPromotion;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\ProductDeliveryOption;
use App\Models\User;
use Illuminate\Support\Facades\Event;

it('dispatches OrderStatusUpdatedEvent exactly once and completes the order on approval', function (OrderStatusEnum $status): void {
    Event::fake([OrderStatusUpdatedEvent::class]);

    $customer  = User::factory()->create();
    $promotion = DiscountPromotion::factory()->create(['is_active' => true]);
    $coupon    = DiscountCoupon::factory()->create([
        'discount_promotion_id' => $promotion->id,
        'code'                  => 'SAVE10',
    ]);

    $option = ProductDeliveryOption::factory()->create(['reserved_count' => 5]);

    $order = Order::factory()->create([
        'customer_id'                 => $customer->id,
        'status'                      => $status,
        'grand_total'                 => 1000000,
        'full_value_grand_total'      => 1000000,
        'applied_coupon_code'         => 'SAVE10',
        'applied_cart_discounts_json' => [
            [
                'promotion_id'   => $promotion->id,
                'promotion_name' => $promotion->name,
                'applied_amount' => 5000,
                'coupon_code'    => 'SAVE10',
            ],
        ],
    ]);

    OrderItem::factory()->create([
        'order_id'                   => $order->id,
        'product_delivery_option_id' => $option->id,
        'qty_ordered'                => 2,
        'payment_type'               => OrderItemPaymentTypeEnum::FULL_PAYMENT,
        'price'                      => 1000000,
        'total'                      => 1000000,
        'status'                     => OrderItemStatusEnum::PENDING,
    ]);

    Payment::factory()->create([
        'order_id'    => $order->id,
        'customer_id' => $customer->id,
        'method'      => PaymentMethodEnum::MELLAT_GATEWAY,
        'amount'      => 1000000,
        'status'      => PaymentStatusEnum::COMPLETED,
    ]);

    app(ApproveOrderAction::class)->handle($order);

    Event::assertDispatchedTimes(OrderStatusUpdatedEvent::class, 1);

    // Order and its items end up COMPLETED.
    $freshOrder = $order->fresh();
    expect($freshOrder->status)->toBe(OrderStatusEnum::COMPLETED);
    expect($freshOrder->items->every(
        fn ($item): bool => $item->status === OrderItemStatusEnum::COMPLETED
    ))->toBeTrue();

    // Reservation consumed exactly once (reserved 5, qty 2 → 3).
    expect($option->fresh()->reserved_count)->toBe(3);

    // Usage counters incremented exactly once — the removed explicit call must not double-count.
    expect($promotion->fresh()->total_usage_count)->toBe(1);
    expect($coupon->fresh()->usage_count)->toBe(1);
})->with([
    'pending order'    => [OrderStatusEnum::PENDING],
    'processing order' => [OrderStatusEnum::PROCESSING],
]);

it('approves a deposit order because the required payment is the undiscounted deposit', function (): void {
    Event::fake([OrderStatusUpdatedEvent::class]);

    $customer = User::factory()->create();
    $option   = ProductDeliveryOption::factory()->create(['price' => 100000, 'prepayment_amount' => 25000]);

    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'status'      => OrderStatusEnum::PENDING,
        'grand_total' => 25000,
    ]);

    OrderItem::factory()->create([
        'order_id'                   => $order->id,
        'product_delivery_option_id' => $option->id,
        'qty_ordered'                => 1,
        'payment_type'               => OrderItemPaymentTypeEnum::PRE_PAYMENT,
        'price'                      => 100000,
        'prepayment_amount'          => 25000,
        'discount_amount'            => 0,
        'total'                      => 25000,
        'status'                     => OrderItemStatusEnum::PENDING,
    ]);

    Payment::factory()->create([
        'order_id'    => $order->id,
        'customer_id' => $customer->id,
        'method'      => PaymentMethodEnum::WALLET,
        'amount'      => 25000,
        'status'      => PaymentStatusEnum::COMPLETED,
    ]);

    app(ApproveOrderAction::class)->handle($order);

    expect($order->fresh()->status)->toBe(OrderStatusEnum::COMPLETED)
        ->and($order->fresh()->items->first()->status)->toBe(OrderItemStatusEnum::COMPLETED);
});

it('refuses to approve a deposit order that underpaid the deposit', function (): void {
    Event::fake([OrderStatusUpdatedEvent::class]);

    $customer = User::factory()->create();
    $option   = ProductDeliveryOption::factory()->create(['price' => 100000, 'prepayment_amount' => 25000]);

    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'status'      => OrderStatusEnum::PENDING,
        'grand_total' => 25000,
    ]);

    OrderItem::factory()->create([
        'order_id'                   => $order->id,
        'product_delivery_option_id' => $option->id,
        'qty_ordered'                => 1,
        'payment_type'               => OrderItemPaymentTypeEnum::PRE_PAYMENT,
        'price'                      => 100000,
        'prepayment_amount'          => 25000,
        'discount_amount'            => 0,
        'total'                      => 25000,
        'status'                     => OrderItemStatusEnum::PENDING,
    ]);

    Payment::factory()->create([
        'order_id'    => $order->id,
        'customer_id' => $customer->id,
        'method'      => PaymentMethodEnum::WALLET,
        'amount'      => 20000,
        'status'      => PaymentStatusEnum::COMPLETED,
    ]);

    expect(fn () => app(ApproveOrderAction::class)->handle($order))
        ->toThrow(Illuminate\Validation\ValidationException::class);

    expect($order->fresh()->status)->toBe(OrderStatusEnum::PENDING);
});
