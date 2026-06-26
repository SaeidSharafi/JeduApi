<?php

declare(strict_types=1);

use App\Enums\Order\RefundStatusEnum;
use App\Events\RefundCompletedEvent;
use App\Models\Order;
use App\Models\Refund;
use Illuminate\Support\Facades\Event;

uses(Tests\Support\Traits\AuthTestTrait::class);
describe('RefundUpdateStatusController', function (): void {
    beforeEach(function (): void {
        Event::fake([RefundCompletedEvent::class]);
    });
    it('should update the status of a refund', function (): void {
        $this->authorized_user([App\Enums\PermissionEnum::REFUND_UPDATE_STATUS]);
        $order = Order::factory()
            ->withCalculatedTotals([
                [
                    'price' => 10000,
                    'total' => 10000,
                ],
            ])->create()->fresh();
        $orderItem = $order->items()->first();
        $order->payments()->create([
            'customer_id' => $order->customer_id,
            'amount'      => 10000,
            'status'      => App\Enums\Payment\PaymentStatusEnum::COMPLETED,
            'method'      => App\Enums\Payment\PaymentMethodEnum::BANK_TRANSFER,
        ]);
        $orderItem->enrollment()
            ->create([
                'customer_id'                => $order->customer_id,
                'order_id'                   => $order->id,
                'product_delivery_option_id' => $orderItem->product_delivery_option_id,
                'enrollment_status'          => App\Enums\EnrollmentStatusEnum::ACTIVE,
            ]);
        $refund = Refund::factory()->create([
            'order_item_id' => $orderItem->id,
            'status'        => RefundStatusEnum::PENDING,
        ]);

        $response = $this->putJson(route('api.v1.admin.refund.status', ['refund' => $refund->id]), [
            'status' => RefundStatusEnum::COMPLETED,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('refunds', [
            'id'     => $refund->id,
            'status' => RefundStatusEnum::COMPLETED,
        ]);
        $this->assertDatabaseHas('orders', [
            'id'     => $order->id,
            'status' => App\Enums\Order\OrderStatusEnum::REFUNDED,
        ]);
        $this->assertDatabaseHas('order_items', [
            'id'     => $orderItem->id,
            'status' => App\Enums\Order\OrderItemStatusEnum::REFUNDED,
        ]);
        $this->assertDatabaseHas('enrollments', [
            'order_id'          => $order->id,
            'order_item_id'     => $orderItem->id,
            'enrollment_status' => App\Enums\EnrollmentStatusEnum::CANCELLED,
        ]);

    });

    it('should not update the status of a non-pending refund', function (): void {
        $this->authorized_user([App\Enums\PermissionEnum::REFUND_UPDATE_STATUS]);
        $order = Order::factory()
            ->withCalculatedTotals([
                [
                    'price' => 10000,
                    'total' => 10000,
                ],
            ])->create()->fresh();
        $orderItem = $order->items()->first();
        $order->payments()->create([
            'customer_id' => $order->customer_id,
            'amount'      => 10000,
            'status'      => App\Enums\Payment\PaymentStatusEnum::COMPLETED,
            'method'      => App\Enums\Payment\PaymentMethodEnum::BANK_TRANSFER,
        ]);
        $orderItem->enrollment()
            ->create([
                'customer_id'                => $order->customer_id,
                'order_id'                   => $order->id,
                'product_delivery_option_id' => $orderItem->product_delivery_option_id,
                'enrollment_status'          => App\Enums\EnrollmentStatusEnum::ACTIVE,
            ]);
        $refund = Refund::factory()->create([
            'order_item_id' => $orderItem->id,
            'status'        => RefundStatusEnum::COMPLETED, // Not pending
        ]);

        $response = $this->putJson(route('api.v1.admin.refund.status', ['refund' => $refund->id]), [
            'status' => RefundStatusEnum::COMPLETED, // Trying to set to the same status
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['status']);
    });

    it('should not update the status of a refund without permission', function (): void {
        $this->unauthorized_user();
        $order = Order::factory()
            ->withCalculatedTotals([
                [
                    'price' => 10000,
                    'total' => 10000,
                ],
            ])->create()->fresh();
        $orderItem = $order->items()->first();
        $order->payments()->create([
            'customer_id' => $order->customer_id,
            'amount'      => 10000,
            'status'      => App\Enums\Payment\PaymentStatusEnum::COMPLETED,
            'method'      => App\Enums\Payment\PaymentMethodEnum::BANK_TRANSFER,
        ]);
        $orderItem->enrollment()
            ->create([
                'customer_id'                => $order->customer_id,
                'order_id'                   => $order->id,
                'product_delivery_option_id' => $orderItem->product_delivery_option_id,
                'enrollment_status'          => App\Enums\EnrollmentStatusEnum::ACTIVE,
            ]);
        $refund = Refund::factory()->create([
            'order_item_id' => $orderItem->id,
            'status'        => RefundStatusEnum::PENDING,
        ]);

        $response = $this->putJson(route('api.v1.admin.refund.status', ['refund' => $refund->id]), [
            'status' => RefundStatusEnum::COMPLETED,
        ]);

        $response->assertForbidden();
    });

});
