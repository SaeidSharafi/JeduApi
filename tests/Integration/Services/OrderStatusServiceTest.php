<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\EnrollmentStatusEnum;
use App\Enums\Order\OrderItemPaymentTypeEnum;
use App\Enums\Order\OrderItemStatusEnum;
use App\Enums\Order\OrderStatusEnum;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\OrderStatusService;

describe('OrderStatusService', function (): void {


    it('sets order status to PENDING when there is no item', function (): void {
        $order = Order::factory()->create(['status' => OrderStatusEnum::PROCESSING]);

        (new OrderStatusService())->updateParentOrderStatus($order);

        expect($order->fresh()->status)->toBe(OrderStatusEnum::PENDING);
    });

    it('sets order status to REFUNDED when all items are refunded', function (): void {
        $order = Order::factory()->create();
        OrderItem::factory()->count(2)->for($order)->create([
            'status' => OrderItemStatusEnum::REFUNDED,
        ]);

        (new OrderStatusService())->updateParentOrderStatus($order);

        expect($order->fresh()->status)->toBe(OrderStatusEnum::REFUNDED);
    });

    it('sets order status to PARTIALLY_REFUNDED when some items are refunded', function (): void {
        $order = Order::factory()->create();
        OrderItem::factory()->for($order)->create(['status' => OrderItemStatusEnum::REFUNDED]);
        OrderItem::factory()->for($order)->create(['status' => OrderItemStatusEnum::COMPLETED]);

        (new OrderStatusService())->updateParentOrderStatus($order);

        expect($order->fresh()->status)->toBe(OrderStatusEnum::PARTIALLY_REFUNDED);
    });

    it('sets order status to COMPLETED when all items are completed', function (): void {
        $order = Order::factory()->create();
        OrderItem::factory()->count(2)->for($order)->create([
            'status' => OrderItemStatusEnum::COMPLETED,
        ]);

        (new OrderStatusService())->updateParentOrderStatus($order);

        expect($order->fresh()->status)->toBe(OrderStatusEnum::COMPLETED);
    });

    it('sets order status to PROCESSING when items are in a mixed state', function (): void {
        $order = Order::factory()->create();
        OrderItem::factory()->for($order)->create(['status' => OrderItemStatusEnum::PENDING]);
        OrderItem::factory()->for($order)->create(['status' => OrderItemStatusEnum::COMPLETED]);

        (new OrderStatusService())->updateParentOrderStatus($order);

        expect($order->fresh()->status)->toBe(OrderStatusEnum::PROCESSING);
    });

    it('sets order status to CANCELLED when items are CANCELLED', function (): void {
        $order = Order::factory()->create();
        OrderItem::factory()->count(2)->for($order)->create([
            'status' => OrderItemStatusEnum::CANCELLED,
        ]);

        (new OrderStatusService())->updateParentOrderStatus($order);

        expect($order->fresh()->status)->toBe(OrderStatusEnum::CANCELLED);
    });

    it('updates enrollment to PENDING_PROVISIONING when item becomes COMPLETED', function (): void {
        $item = OrderItem::factory()->create(['status' => OrderItemStatusEnum::COMPLETED]);
        $enrollment = Enrollment::factory()->for($item)->create([
            'enrollment_status' => EnrollmentStatusEnum::AWAITING_PAYMENT,
        ]);

        (new OrderStatusService())->updateEnrollmentStatus($item);

        $enrollment->refresh();
        expect($enrollment->enrollment_status)->toBe(EnrollmentStatusEnum::PENDING_PROVISIONING)
            ->and($enrollment->access_start_date)->not->toBeNull();
    });

    it('updates enrollment to CANCELLED when item becomes REFUNDED', function (): void {
        $item = OrderItem::factory()->create(['status' => OrderItemStatusEnum::REFUNDED]);
        $enrollment = Enrollment::factory()->for($item)->create([
            'enrollment_status' => EnrollmentStatusEnum::ACTIVE,
        ]);

        (new OrderStatusService())->updateEnrollmentStatus($item);

        expect($enrollment->fresh()->enrollment_status)->toBe(EnrollmentStatusEnum::CANCELLED);
    });

    it('does not change enrollment status when item is PENDING', function (): void {
        $item = OrderItem::factory()->create(['status' => OrderItemStatusEnum::PENDING]);
        $enrollment = Enrollment::factory()->for($item)->create([
            'enrollment_status' => EnrollmentStatusEnum::ACTIVE,
        ]);

        (new OrderStatusService())->updateEnrollmentStatus($item);

        expect($enrollment->fresh()->enrollment_status)->toBe(EnrollmentStatusEnum::ACTIVE);
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

        (new OrderStatusService())->handlePaymentCompletion($order->fresh());

        $this->assertDatabaseHas('order_items', [
            'id'     => $item1->id,
            'status' => OrderItemStatusEnum::COMPLETED->value
        ]);
        $this->assertDatabaseHas('order_items', [
            'id'     => $item2->id,
            'status' => OrderItemStatusEnum::COMPLETED->value
        ]);

        $this->assertDatabaseHas('enrollments', [
            'id'                => $item1->enrollment->id,
            'enrollment_status' => EnrollmentStatusEnum::PENDING_PROVISIONING->value
        ]);
        $this->assertDatabaseHas('enrollments', [
            'id'                => $item2->enrollment->id,
            'enrollment_status' => EnrollmentStatusEnum::PENDING_PROVISIONING->value
        ]);

        $this->assertDatabaseHas('orders', [
            'id'     => $order->id,
            'status' => OrderStatusEnum::COMPLETED->value
        ]);
    });
});
