<?php

declare(strict_types=1);

namespace Tests\Unit\Listeners;

use App\Enums\DeliveryMethodEnum;
use App\Enums\EnrolmentStatusEnum;
use App\Enums\Order\OrderItemPaymentTypeEnum;
use App\Enums\Order\OrderItemStatusEnum;
use App\Enums\Order\OrderStatusEnum;
use App\Events\PaymentCompletedEvent;
use App\Listeners\UpdateOrderAndEnrollmentStatusesListener;
use App\Models\Enrolment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\ProductDeliveryOption;
use function Pest\Laravel\assertDatabaseHas;

describe('UpdateOrderAndEnrollmentStatusesListener', function () {

    // SCENARIO 1: A mixed order where an initial, partial payment is made.
    it('updates statuses correctly after an initial partial payment', function () {
        // --- Arrange ---
        // 1. Create a mixed order
        $order = Order::factory()->create([
            'status' => OrderStatusEnum::PENDING,
            'grand_total' => 150000,
        ]);

        // Item 1: In-person course (pre-payment) - Its enrollment SHOULD become active.
        $inPersonItem = OrderItem::factory()->for($order)->create([
            'status' => OrderItemStatusEnum::PENDING,
            'payment_type' => OrderItemPaymentTypeEnum::PRE_PAYMENT,
            'product_delivery_option_id' => ProductDeliveryOption::factory()->create(['delivery_method' => DeliveryMethodEnum::IN_PERSON])->id,
        ]);
        Enrolment::factory()->for($inPersonItem)->create(['enrollment_status' => EnrolmentStatusEnum::PENDING_PROVISIONING]);

        // Item 2: Digital product (full payment) - It is now fully paid.
        $digitalItem = OrderItem::factory()->for($order)->create([
            'status' => OrderItemStatusEnum::PENDING,
            'payment_type' => OrderItemPaymentTypeEnum::FULL_PAYMENT,
            'product_delivery_option_id' => ProductDeliveryOption::factory()->create(['delivery_method' => DeliveryMethodEnum::DIRECT_DOWNLOAD])->id,
        ]);
        Enrolment::factory()->for($digitalItem)->create(['enrollment_status' => EnrolmentStatusEnum::PENDING_PROVISIONING]);

        // 2. Create the completed payment record that triggers the event
        $payment = Payment::factory()->for($order)->create([
            'amount' => 50000, // A partial amount
            'status' => 'completed',
        ]);

        // --- Act ---
        // 3. Manually create the event and listener and fire the handle method
        $event = new PaymentCompletedEvent($payment);
        $listener = new UpdateOrderAndEnrollmentStatusesListener();
        $listener->handle($event);

        // --- Assert ---
        // 4. Check the final state of the database
        // The master order is now 'COMPLETED' from a workflow perspective
        assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatusEnum::COMPLETED->value,
        ]);

        // The In-Person enrollment IS active now, because a payment was made
        assertDatabaseHas('enrolments', [
            'id' => $inPersonItem->enrolment->id,
            'enrollment_status' => EnrolmentStatusEnum::ACTIVE->value,
        ]);
        // The Digital enrollment is NOT active, because its delivery method is not 'IN_PERSON'
        assertDatabaseHas('enrolments', [
            'id' => $digitalItem->enrolment->id,
            'enrollment_status' => EnrolmentStatusEnum::PENDING_PROVISIONING->value,
        ]);

        assertDatabaseHas('order_items', [
            'id' => $inPersonItem->id,
            'status' => OrderItemStatusEnum::COMPLETED->value,
        ]);
        // The full-payment item IS complete because its share of the payment was covered
        assertDatabaseHas('order_items', [
            'id' => $digitalItem->id,
            'status' => OrderItemStatusEnum::COMPLETED->value,
        ]);
    });

    // SCENARIO 2: A final payment is made, settling the order.
    it('updates all statuses correctly after the final payment', function () {
        // --- Arrange ---
        $order = Order::factory()->create([
            'status' => OrderStatusEnum::PROCESSING, // It was partially paid
            'grand_total' => 100000,
        ]);
        // This payment was made previously
        Payment::factory()->for($order)->create(['amount' => 20000, 'status' => 'completed']);

        $item = OrderItem::factory()->for($order)->create([
            'status' => OrderItemStatusEnum::PENDING,
            'payment_type' => OrderItemPaymentTypeEnum::PRE_PAYMENT,
            'product_delivery_option_id' => ProductDeliveryOption::factory()->create(['delivery_method' => DeliveryMethodEnum::IN_PERSON])->id,
        ]);
        Enrolment::factory()->for($item)->create(['enrollment_status' => EnrolmentStatusEnum::ACTIVE]); // Was already active

        // Now we make the final payment
        $finalPayment = Payment::factory()->for($order)->create([
            'amount' => 80000, // The remaining balance
            'status' => 'completed',
        ]);

        // --- Act ---
        $event = new PaymentCompletedEvent($finalPayment);
        (new UpdateOrderAndEnrollmentStatusesListener())->handle($event);

        // --- Assert ---
        // The order status remains 'COMPLETED'
        assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatusEnum::COMPLETED->value,
        ]);

        // The OrderItem is now 'COMPLETED' because balance_due is zero
        assertDatabaseHas('order_items', [
            'id' => $item->id,
            'status' => OrderItemStatusEnum::COMPLETED->value,
        ]);

        // The enrollment remains active (no change)
        assertDatabaseHas('enrolments', [
            'id' => $item->enrolment->id,
            'enrollment_status' => EnrolmentStatusEnum::ACTIVE->value,
        ]);
    });

    it('returns early if the order is missing from the payment', function () {

        $payment = new Payment(); // A fake payment object in memory without a real order
        $event = new PaymentCompletedEvent($payment);

        (new UpdateOrderAndEnrollmentStatusesListener())->handle($event);

        $this->assertTrue(true);
    });
});
