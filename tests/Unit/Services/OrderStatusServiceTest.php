<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\EnrolmentStatusEnum;
use App\Enums\Order\OrderItemPaymentTypeEnum;
use App\Enums\Order\OrderItemStatusEnum;
use App\Enums\Order\OrderStatusEnum;
use App\Models\Enrolment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\OrderStatusService;
use Illuminate\Database\Eloquent\Collection;

describe('OrderStatusService', function () {

    // Helper function to create a mock OrderItem with a specific status
    $createMockItem = function ($status, $enrollmentStatus = null) {
        $item = \Mockery::mock(OrderItem::class)->makePartial();
        $item->status = $status;

        if ($enrollmentStatus) {
            $enrolment = \Mockery::mock(Enrolment::class)->makePartial();
            $enrolment->enrollment_status = $enrollmentStatus;
            $item->enrolment = $enrolment;
        } else {
            $item->enrolment = null;
        }

        // Mock the saveQuietly method to track if it's called
        $item->shouldReceive('saveQuietly')->byDefault();
        if ($item->enrolment) {
            $item->enrolment->shouldReceive('saveQuietly')->byDefault();
        }

        return $item;
    };

    // --- Testing updateParentOrderStatus ---
    it('sets order status to PEDNING when there is no item', function () use ($createMockItem) {
        $order = \Mockery::mock(Order::class)->makePartial();
        $items = new Collection([]);
        $order->items = $items;

        $order->shouldReceive('saveQuietly')->once();

        (new OrderStatusService())->updateParentOrderStatus($order);

        expect($order->status)->toBe(OrderStatusEnum::PENDING);
    });
    it('sets order status to REFUNDED when all items are refunded', function () use ($createMockItem) {
        $order = \Mockery::mock(Order::class)->makePartial();
        $items = new Collection([
            $createMockItem(OrderItemStatusEnum::REFUNDED),
            $createMockItem(OrderItemStatusEnum::REFUNDED),
        ]);
        $order->items = $items;

        $order->shouldReceive('saveQuietly')->once();

        (new OrderStatusService())->updateParentOrderStatus($order);

        expect($order->status)->toBe(OrderStatusEnum::REFUNDED);
    });

    it('sets order status to PARTIALLY_REFUNDED when some items are refunded', function () use ($createMockItem) {
        $order = \Mockery::mock(Order::class)->makePartial();
        $items = new Collection([
            $createMockItem(OrderItemStatusEnum::REFUNDED),
            $createMockItem(OrderItemStatusEnum::COMPLETED),
        ]);
        $order->items = $items;
        $order->shouldReceive('saveQuietly')->once();

        (new OrderStatusService())->updateParentOrderStatus($order);

        expect($order->status)->toBe(OrderStatusEnum::PARTIALLY_REFUNDED);
    });

    it('sets order status to COMPLETED when all items are completed', function () use ($createMockItem) {
        $order = \Mockery::mock(Order::class)->makePartial();
        $items = new Collection([
            $createMockItem(OrderItemStatusEnum::COMPLETED),
            $createMockItem(OrderItemStatusEnum::COMPLETED),
        ]);
        $order->items = $items;
        $order->shouldReceive('saveQuietly')->once();

        (new OrderStatusService())->updateParentOrderStatus($order);

        expect($order->status)->toBe(OrderStatusEnum::COMPLETED);
    });

    it('sets order status to PROCESSING when items are in a mixed state', function () use ($createMockItem) {
        $order = \Mockery::mock(Order::class)->makePartial();
        $items = new Collection([
            $createMockItem(OrderItemStatusEnum::PENDING),
            $createMockItem(OrderItemStatusEnum::COMPLETED),
        ]);
        $order->items = $items;
        $order->shouldReceive('saveQuietly')->once();

        (new OrderStatusService())->updateParentOrderStatus($order);

        expect($order->status)->toBe(OrderStatusEnum::PROCESSING);
    });
    it('sets order status to CACNELED when items are CACNELED', function () use ($createMockItem) {
        $order = \Mockery::mock(Order::class)->makePartial();
        $items = new Collection([
            $createMockItem(OrderItemStatusEnum::CANCELLED),
            $createMockItem(OrderItemStatusEnum::CANCELLED),
        ]);
        $order->items = $items;
        $order->shouldReceive('saveQuietly')->once();

        (new OrderStatusService())->updateParentOrderStatus($order);

        expect($order->status)->toBe(OrderStatusEnum::CANCELLED);
    });
    // --- Testing updateEnrollmentStatus ---
    it('updates enrollment to ACTIVE when item becomes COMPLETED', function () use ($createMockItem) {
        $item = $createMockItem(OrderItemStatusEnum::COMPLETED, EnrolmentStatusEnum::PENDING_PROVISIONING);

        // Expect the enrollment's save method to be called
        $item->enrolment->shouldReceive('saveQuietly')->once();

        (new OrderStatusService())->updateEnrollmentStatus($item);

        expect($item->enrolment->enrollment_status)->toBe(EnrolmentStatusEnum::ACTIVE);
        expect($item->enrolment->access_start_date)->not->toBeNull();
    });

    it('updates enrollment to CANCELLED when item becomes REFUNDED', function () use ($createMockItem) {
        $item = $createMockItem(OrderItemStatusEnum::REFUNDED, EnrolmentStatusEnum::ACTIVE);
        $item->enrolment->shouldReceive('saveQuietly')->once();

        (new OrderStatusService())->updateEnrollmentStatus($item);

        expect($item->enrolment->enrollment_status)->toBe(EnrolmentStatusEnum::CANCELLED);
    });

    it('deos not cahnge enrollment status when item is PEDNING', function () use ($createMockItem) {
        $item = $createMockItem(OrderItemStatusEnum::PENDING, EnrolmentStatusEnum::ACTIVE);
        $item->enrolment->shouldNotReceive('saveQuietly');

        (new OrderStatusService())->updateEnrollmentStatus($item);

        expect($item->enrolment->enrollment_status)->toBe(EnrolmentStatusEnum::ACTIVE);
    });

    // --- Testing the main orchestrator method: updateStatusesAfterPayment ---
    it('correctly cascades all status changes after a payment', function () {
        // This is a more integrated test using real models
        // --- Arrange ---
        $order = Order::factory()->create(['status' => OrderStatusEnum::PENDING]);

        // Item 1: Pre-payment, should become COMPLETED, enrollment ACTIVE
        $item1 = OrderItem::factory()->for($order)->create([
            'status' => OrderItemStatusEnum::PENDING,
            'payment_type' => OrderItemPaymentTypeEnum::PRE_PAYMENT
        ]);
        Enrolment::factory()->for($item1)->create(['enrollment_status' => EnrolmentStatusEnum::PENDING_PROVISIONING]);

        // Item 2: Full payment, should become COMPLETED, enrollment ACTIVE
        $item2 = OrderItem::factory()->for($order)->create([
            'status' => OrderItemStatusEnum::PENDING,
            'payment_type' => OrderItemPaymentTypeEnum::FULL_PAYMENT
        ]);
        Enrolment::factory()->for($item2)->create(['enrollment_status' => EnrolmentStatusEnum::PENDING_PROVISIONING]);

        // --- Act ---
        (new OrderStatusService())->handlePaymentCompletion($order->fresh());

        // --- Assert ---
        // Both items are now COMPLETED
        $this->assertDatabaseHas('order_items', ['id' => $item1->id, 'status' => OrderItemStatusEnum::COMPLETED->value]);
        $this->assertDatabaseHas('order_items', ['id' => $item2->id, 'status' => OrderItemStatusEnum::COMPLETED->value]);

        // Both enrollments are now ACTIVE
        $this->assertDatabaseHas('enrolments', ['id' => $item1->enrolment->id, 'enrollment_status' => EnrolmentStatusEnum::ACTIVE->value]);
        $this->assertDatabaseHas('enrolments', ['id' => $item2->enrolment->id, 'enrollment_status' => EnrolmentStatusEnum::ACTIVE->value]);

        // The parent order is now COMPLETED because all its items are
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => OrderStatusEnum::COMPLETED->value]);
    });
});
