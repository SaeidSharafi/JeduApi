<?php

declare(strict_types=1);

use App\Actions\Admin\Refund\UpdateRefundStatusAction;
use App\Data\Admin\Refund\RefundStatusUpdateData;
use App\Enums\EnrollmentStatusEnum;
use App\Enums\Order\OrderItemStatusEnum;
use App\Enums\Order\RefundStatusEnum;
use App\Events\RefundCompletedEvent;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Refund;
use App\Models\User;
use App\Services\OrderStatusService;
use App\Services\Payment\Digipay\DigipayAdminService;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

describe('UpdateRefundStatusAction', function (): void {
    beforeEach(function (): void {
        Event::fake(
            [
                RefundCompletedEvent::class,
            ]
        );
    });

    it('transitions from pending to completed and fires event', function (): void {
        $order = Order::factory()
            ->withCalculatedTotals([
                ['price' => 2000, 'total' => 2000],
            ])->state([
                'customer_id' => User::factory()->create()->id,
            ])
            ->create();
        $orderItem  = $order->items()->first();
        $enrollment = $orderItem->enrollment()->create([
            'enrollment_status'          => EnrollmentStatusEnum::ACTIVE,
            'order_id'                   => $order->id,
            'order_item_id'              => $orderItem->id,
            'customer_id'                => $order->customer_id,
            'product_delivery_option_id' => $orderItem->product_delivery_option_id,
        ])->fresh();
        $refund = Refund::factory()->create([
            'order_item_id' => $orderItem->id,
            'status'        => RefundStatusEnum::PENDING,
            'amount'        => 1000,
        ]);
        $data = new RefundStatusUpdateData(
            status: RefundStatusEnum::COMPLETED->value,
            tracking_code: 'TRACK123',
            admin_notes: 'Completed refund',
        );
        $action  = new UpdateRefundStatusAction(new OrderStatusService(), app(DigipayAdminService::class));
        $updated = $action->handle($refund, $data);
        expect($updated->status)->toBe(RefundStatusEnum::COMPLETED)
            ->and($updated->admin_notes)->toBe('Completed refund')
            ->and($updated->transaction_details['tracking_code'])->toBe('TRACK123');
        Event::assertDispatched(RefundCompletedEvent::class);
        $orderItem->refresh();
        expect($orderItem->status)->toBe(OrderItemStatusEnum::REFUNDED);
        \Pest\Laravel\assertDatabaseHas('orders', [
            'id'     => $order->id,
            'status' => App\Enums\Order\OrderStatusEnum::REFUNDED,
        ]);
        \Pest\Laravel\assertDatabaseHas('enrollments', [
            'id'                => $enrollment->id,
            'enrollment_status' => EnrollmentStatusEnum::CANCELLED,
        ]);
    });

    it('transitions from pending to processing', function (): void {
        $order     = Order::factory()->create();
        $orderItem = OrderItem::factory()->create([
            'order_id' => $order->id,
        ]);
        $refund = Refund::factory()->create([
            'order_item_id' => $orderItem->id,
            'status'        => RefundStatusEnum::PENDING,
        ]);
        $data = new RefundStatusUpdateData(
            status: RefundStatusEnum::PROCESSING->value,
            tracking_code: null,
            admin_notes: 'Processing refund',
        );
        $action  = new UpdateRefundStatusAction(new OrderStatusService(), app(DigipayAdminService::class));
        $updated = $action->handle($refund, $data);
        expect($updated->status)->toBe(RefundStatusEnum::PROCESSING)
            ->and($updated->admin_notes)->toBe('Processing refund');
    });

    it('transitions from processing to completed', function (): void {
        $order     = Order::factory()->create();
        $orderItem = OrderItem::factory()->create([
            'order_id' => $order->id,
        ]);
        $refund = Refund::factory()->create([
            'order_item_id' => $orderItem->id,
            'status'        => RefundStatusEnum::PROCESSING,
            'amount'        => 500,
        ]);
        $data = new RefundStatusUpdateData(
            status: RefundStatusEnum::COMPLETED->value,
            tracking_code: 'TRACK456',
            admin_notes: null,
        );
        $action  = new UpdateRefundStatusAction(new OrderStatusService(), app(DigipayAdminService::class));
        $updated = $action->handle($refund, $data);
        expect($updated->status)->toBe(RefundStatusEnum::COMPLETED)
            ->and($updated->transaction_details['tracking_code'])->toBe('TRACK456');
        Event::assertDispatched(RefundCompletedEvent::class);
    });

    it('throws if transition is not allowed', function (): void {
        $order     = Order::factory()->create();
        $orderItem = OrderItem::factory()->create([
            'order_id' => $order->id,
        ]);
        $refund = Refund::factory()->create([
            'order_item_id' => $orderItem->id,
            'status'        => RefundStatusEnum::COMPLETED,
        ]);
        $data = new RefundStatusUpdateData(
            status: RefundStatusEnum::PROCESSING->value,
            tracking_code: null,
            admin_notes: null,
        );
        $action = new UpdateRefundStatusAction(new OrderStatusService(), app(DigipayAdminService::class));
        expect(fn (): Refund => $action->handle($refund, $data))
            ->toThrow(ValidationException::class);
    });

    it('does not allow transition to pending', function (): void {
        $order     = Order::factory()->create();
        $orderItem = OrderItem::factory()->create([
            'order_id' => $order->id,
        ]);
        $refund = Refund::factory()->create([
            'order_item_id' => $orderItem->id,
            'status'        => RefundStatusEnum::PROCESSING,
        ]);
        $data = new RefundStatusUpdateData(
            status: RefundStatusEnum::PENDING->value,
            tracking_code: null,
            admin_notes: null,
        );
        $action = new UpdateRefundStatusAction(new OrderStatusService(), app(DigipayAdminService::class));
        expect(fn (): Refund => $action->handle($refund, $data))
            ->toThrow(ValidationException::class);
    });

    it('transitions from processing to failed', function (): void {
        $order     = Order::factory()->create();
        $orderItem = OrderItem::factory()->create([
            'order_id' => $order->id,
        ]);
        $refund = Refund::factory()->create([
            'order_item_id' => $orderItem->id,
            'status'        => RefundStatusEnum::PROCESSING,
        ]);
        $data = new RefundStatusUpdateData(
            status: RefundStatusEnum::FAILED->value,
            tracking_code: null,
            admin_notes: 'Failed refund',
        );
        $action  = new UpdateRefundStatusAction(new OrderStatusService(), app(DigipayAdminService::class));
        $updated = $action->handle($refund, $data);
        expect($updated->status)->toBe(RefundStatusEnum::FAILED)
            ->and($updated->admin_notes)->toBe('Failed refund');
    });

    it('transitions from pending to cancelled', function (): void {
        $order     = Order::factory()->create();
        $orderItem = OrderItem::factory()->create([
            'order_id' => $order->id,
        ]);
        $refund = Refund::factory()->create([
            'order_item_id' => $orderItem->id,
            'status'        => RefundStatusEnum::PENDING,
        ]);
        $data = new RefundStatusUpdateData(
            status: RefundStatusEnum::CANCELLED->value,
            tracking_code: null,
            admin_notes: 'Cancelled refund',
        );
        $action  = new UpdateRefundStatusAction(new OrderStatusService(), app(DigipayAdminService::class));
        $updated = $action->handle($refund, $data);
        expect($updated->status)->toBe(RefundStatusEnum::CANCELLED)
            ->and($updated->admin_notes)->toBe('Cancelled refund');
    });

    it('does not change admin_notes if not provided', function (): void {
        $order     = Order::factory()->create();
        $orderItem = OrderItem::factory()->create([
            'order_id' => $order->id,
        ]);
        $refund = Refund::factory()->create([
            'order_item_id' => $orderItem->id,
            'status'        => RefundStatusEnum::PROCESSING,
            'admin_notes'   => 'Original note',
        ]);
        $data = new RefundStatusUpdateData(
            status: RefundStatusEnum::FAILED->value,
            tracking_code: null,
            admin_notes: null,
        );
        $action  = new UpdateRefundStatusAction(new OrderStatusService(), app(DigipayAdminService::class));
        $updated = $action->handle($refund, $data);
        expect($updated->admin_notes)->toBe('Original note');
    });
});
