<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\EnrollmentStatusEnum;
use App\Enums\Order\OrderItemPaymentTypeEnum;
use App\Enums\Order\OrderItemStatusEnum;
use App\Enums\Order\OrderStatusEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Services\OrderStatusService;

describe('OrderStatusService', function (): void {

    it('sets order status to PENDING when there is no item', function (): void {
        $order = Order::factory()->create(['status' => OrderStatusEnum::PROCESSING]);

        app(OrderStatusService::class)->updateParentOrderStatus($order);

        expect($order->fresh()->status)->toBe(OrderStatusEnum::PENDING);
    });

    it('sets order status to REFUNDED when all items are refunded', function (): void {
        $order = Order::factory()->create();
        OrderItem::factory()->count(2)->for($order)->create([
            'status' => OrderItemStatusEnum::REFUNDED,
        ]);

        app(OrderStatusService::class)->updateParentOrderStatus($order);

        expect($order->fresh()->status)->toBe(OrderStatusEnum::REFUNDED);
    });

    it('sets order status to PARTIALLY_REFUNDED when some items are refunded', function (): void {
        $order = Order::factory()->create();
        OrderItem::factory()->for($order)->create(['status' => OrderItemStatusEnum::REFUNDED]);
        OrderItem::factory()->for($order)->create(['status' => OrderItemStatusEnum::COMPLETED]);

        app(OrderStatusService::class)->updateParentOrderStatus($order);

        expect($order->fresh()->status)->toBe(OrderStatusEnum::PARTIALLY_REFUNDED);
    });

    it('sets order status to COMPLETED when all items are completed', function (): void {
        $order = Order::factory()->create();
        OrderItem::factory()->count(2)->for($order)->create([
            'status' => OrderItemStatusEnum::COMPLETED,
        ]);

        app(OrderStatusService::class)->updateParentOrderStatus($order);

        expect($order->fresh()->status)->toBe(OrderStatusEnum::COMPLETED);
    });

    it('sets order status to PROCESSING when items are in a mixed state', function (): void {
        $order = Order::factory()->create();
        OrderItem::factory()->for($order)->create(['status' => OrderItemStatusEnum::PENDING]);
        OrderItem::factory()->for($order)->create(['status' => OrderItemStatusEnum::COMPLETED]);

        app(OrderStatusService::class)->updateParentOrderStatus($order);

        expect($order->fresh()->status)->toBe(OrderStatusEnum::PROCESSING);
    });

    it('sets order status to CANCELLED when items are CANCELLED', function (): void {
        $order = Order::factory()->create();
        OrderItem::factory()->count(2)->for($order)->create([
            'status' => OrderItemStatusEnum::CANCELLED,
        ]);

        app(OrderStatusService::class)->updateParentOrderStatus($order);

        expect($order->fresh()->status)->toBe(OrderStatusEnum::CANCELLED);
    });

    it('updates enrollment to PENDING_PROVISIONING when item becomes COMPLETED', function (): void {
        $item       = OrderItem::factory()->create(['status' => OrderItemStatusEnum::COMPLETED]);
        $enrollment = Enrollment::factory()->for($item)->create([
            'enrollment_status' => EnrollmentStatusEnum::AWAITING_PAYMENT,
        ]);

        app(OrderStatusService::class)->updateEnrollmentStatus($item);

        $enrollment->refresh();
        expect($enrollment->enrollment_status)->toBe(EnrollmentStatusEnum::PENDING_PROVISIONING)
            ->and($enrollment->access_start_date)->not->toBeNull();
    });

    it('updates enrollment to CANCELLED when item becomes REFUNDED', function (): void {
        $item       = OrderItem::factory()->create(['status' => OrderItemStatusEnum::REFUNDED]);
        $enrollment = Enrollment::factory()->for($item)->create([
            'enrollment_status' => EnrollmentStatusEnum::ACTIVE,
        ]);

        app(OrderStatusService::class)->updateEnrollmentStatus($item);

        expect($enrollment->fresh()->enrollment_status)->toBe(EnrollmentStatusEnum::CANCELLED);
    });

    it('does not change enrollment status when item is PENDING', function (): void {
        $item       = OrderItem::factory()->create(['status' => OrderItemStatusEnum::PENDING]);
        $enrollment = Enrollment::factory()->for($item)->create([
            'enrollment_status' => EnrollmentStatusEnum::ACTIVE,
        ]);

        app(OrderStatusService::class)->updateEnrollmentStatus($item);

        expect($enrollment->fresh()->enrollment_status)->toBe(EnrollmentStatusEnum::ACTIVE);
    });

    it('creates enrollment after payment success', function (): void {
        $items = [
            [
                'payment_type' => OrderItemPaymentTypeEnum::FULL_PAYMENT,
                'status'       => OrderItemStatusEnum::PENDING,
                'price'        => 100000,
                'total'        => 100000,
            ],
        ];

        $order = Order::factory()
            ->withCalculatedTotals($items)
            ->create(['status' => OrderStatusEnum::PENDING]);

        $order->refresh();
        $item = $order->items->first();

        Payment::factory()->create([
            'order_id' => $order->id,
            'status'   => PaymentStatusEnum::COMPLETED,
            'amount'   => 100000,
        ]);

        expect(Enrollment::where('order_item_id', $item->id)->exists())->toBeFalse();

        app(OrderStatusService::class)->handlePaymentCompletion($order);

        expect(Enrollment::where('order_item_id', $item->id)->exists())->toBeTrue();
    });

    it('correctly cascades all status changes after a payment', function (): void {
        $order = Order::factory()->create(['status' => OrderStatusEnum::PENDING]);

        $item1 = OrderItem::factory()->for($order)->create([
            'status'       => OrderItemStatusEnum::PENDING,
            'payment_type' => OrderItemPaymentTypeEnum::PRE_PAYMENT,
        ]);
        Enrollment::factory()->for($item1)->create(['enrollment_status' => EnrollmentStatusEnum::AWAITING_PAYMENT]);

        $item2 = OrderItem::factory()->for($order)->create([
            'status'       => OrderItemStatusEnum::PENDING,
            'payment_type' => OrderItemPaymentTypeEnum::FULL_PAYMENT,
        ]);
        Enrollment::factory()->for($item2)->create(['enrollment_status' => EnrollmentStatusEnum::AWAITING_PAYMENT]);

        app(OrderStatusService::class)->handlePaymentCompletion($order->fresh());

        $this->assertDatabaseHas('order_items', [
            'id'     => $item1->id,
            'status' => OrderItemStatusEnum::COMPLETED->value,
        ]);
        $this->assertDatabaseHas('order_items', [
            'id'     => $item2->id,
            'status' => OrderItemStatusEnum::COMPLETED->value,
        ]);

        $this->assertDatabaseHas('enrollments', [
            'id'                => $item1->enrollment->id,
            'enrollment_status' => EnrollmentStatusEnum::PENDING_PROVISIONING->value,
        ]);
        $this->assertDatabaseHas('enrollments', [
            'id'                => $item2->enrollment->id,
            'enrollment_status' => EnrollmentStatusEnum::PENDING_PROVISIONING->value,
        ]);

        $this->assertDatabaseHas('orders', [
            'id'     => $order->id,
            'status' => OrderStatusEnum::COMPLETED->value,
        ]);
    });

    it('sets order to PROCESSING when FULL_PAYMENT trigger but order has balance_due > 0', function (): void {
        $original = config('order.provisioning.trigger');
        config(['order.provisioning.trigger' => 'full_payment']);

        $order = Order::factory()->create([
            'status'                 => OrderStatusEnum::PENDING,
            'full_value_grand_total' => 10000,
        ]);
        $item = OrderItem::factory()->for($order)->create([
            'status' => OrderItemStatusEnum::PENDING,
        ]);

        Enrollment::factory()->for($item)->create();

        app(OrderStatusService::class)->handlePaymentCompletion($order->fresh());

        $this->assertDatabaseHas('orders', [
            'id'     => $order->id,
            'status' => OrderStatusEnum::PROCESSING->value,
        ]);
        $this->assertDatabaseHas('order_items', [
            'id'     => $item->id,
            'status' => OrderItemStatusEnum::PENDING->value, // NOT completed
        ]);

        config(['order.provisioning.trigger' => $original]);
    });

    it('sets order to PROCESSING and does not complete items when provisioning trigger is MANUAL_APPROVAL',
        function (): void {
            config()->set('order.provisioning.trigger', 'manual_approval');

            $order = Order::factory()->create(['status' => OrderStatusEnum::PENDING]);
            $item  = OrderItem::factory()->for($order)->create([
                'status' => OrderItemStatusEnum::PENDING,
            ]);
            Enrollment::factory()->for($item)->create();

            app(OrderStatusService::class)->handlePaymentCompletion($order->fresh());

            $this->assertDatabaseHas('orders', [
                'id'     => $order->id,
                'status' => OrderStatusEnum::PROCESSING->value,
            ]);
            $this->assertDatabaseHas('order_items', [
                'id'     => $item->id,
                'status' => OrderItemStatusEnum::PENDING->value,
            ]);

            config()->set('order.provisioning.trigger', 'any_payment');
        });
});
