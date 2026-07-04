<?php

declare(strict_types=1);

use App\Enums\Order\OrderItemPaymentTypeEnum;
use App\Enums\Order\OrderItemStatusEnum;
use App\Enums\Order\OrderStatusEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\Payment;
use App\Services\OrderStatusService;

describe('OrderStatusService', function (): void {
    it('creates enrollment after payment success', function (): void {
        // Arrange: Create order with one pending item
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

        // Create completed payment for the order
        Payment::factory()->create([
            'order_id' => $order->id,
            'status'   => PaymentStatusEnum::COMPLETED,
            'amount'   => 100000,
        ]);

        // Pre-assert: no enrollment exists yet
        expect(Enrollment::where('order_item_id', $item->id)->exists())->toBeFalse();

        // Act: Process payment completion
        $service = app(OrderStatusService::class);
        $service->handlePaymentCompletion($order);

        // Assert: Enrollment SHOULD be created (but currently bug prevents it - RED phase)
        expect(Enrollment::where('order_item_id', $item->id)->exists())->toBeTrue();
    });
});
