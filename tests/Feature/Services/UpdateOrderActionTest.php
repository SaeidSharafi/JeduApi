<?php

declare(strict_types=1);

use App\Actions\Admin\Order\UpdateOrderAction;
use App\Data\Admin\Order\OrderUpdateData;
use App\Enums\EnrollmentStatusEnum;
use App\Enums\Order\OrderCancellationReasonEnum;
use App\Enums\Order\OrderItemStatusEnum;
use App\Enums\Order\OrderStatusEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Enums\Payment\PaymentTransactionErrorCodeEnum;
use App\Enums\Payment\PaymentTransactionStatusEnum;
use App\Events\OrderStatusUpdatedEvent;
use App\Models\DiscountPromotion;
use App\Models\DiscountPromotionUsage;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\ProductDeliveryOption;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

mutates(UpdateOrderAction::class);

describe('UpdateOrderAction', function (): void {
    it('releases only pending reservations and cancels enrollments when closing an order', function (OrderStatusEnum $status): void {
        $order           = Order::factory()->create(['status' => OrderStatusEnum::PENDING]);
        $pendingOption   = ProductDeliveryOption::factory()->create(['reserved_count' => 5]);
        $completedOption = ProductDeliveryOption::factory()->create(['reserved_count' => 4]);
        $pending         = OrderItem::factory()->for($order)->create([
            'product_delivery_option_id' => $pendingOption->id,
            'qty_ordered'                => 2,
            'status'                     => OrderItemStatusEnum::PENDING,
        ]);
        OrderItem::factory()->for($order)->create([
            'product_delivery_option_id' => $completedOption->id,
            'status'                     => OrderItemStatusEnum::COMPLETED,
        ]);
        $enrollment = Enrollment::factory()->create([
            'order_id'                   => $order->id,
            'order_item_id'              => $pending->id,
            'customer_id'                => $order->customer_id,
            'product_delivery_option_id' => $pendingOption->id,
            'enrollment_status'          => EnrollmentStatusEnum::ACTIVE,
        ]);
        Event::fake([OrderStatusUpdatedEvent::class]);

        $result = app(UpdateOrderAction::class)->handle(new OrderUpdateData($status->value), $order);

        expect($result->status)->toBe($status);

        if ($status === OrderStatusEnum::CANCELLED) {
            expect($result->cancelled_at)->not->toBeNull()
                ->and($result->cancelled_reason)->toBe(OrderCancellationReasonEnum::ADMIN);
        } else {
            expect($result->cancelled_at)->toBeNull()
                ->and($result->cancelled_reason)->toBeNull();
        }

        expect($pendingOption->fresh()->reserved_count)->toBe(3);
        expect($completedOption->fresh()->reserved_count)->toBe(4);
        expect($enrollment->fresh()->enrollment_status)->toBe(EnrollmentStatusEnum::CANCELLED);
        Event::assertDispatched(OrderStatusUpdatedEvent::class, fn ($event): bool => $event->order->id === $order->id);
    })->with([OrderStatusEnum::CANCELLED, OrderStatusEnum::REFUNDED]);

    it('releases a promotion usage slot when an order fails', function (): void {
        $order     = Order::factory()->create(['status' => OrderStatusEnum::PENDING]);
        $promotion = DiscountPromotion::factory()->create();
        $usage     = DiscountPromotionUsage::query()->create([
            'discount_promotion_id' => $promotion->id,
            'customer_id'           => $order->customer_id,
            'order_id'              => $order->id,
        ]);
        Event::fake([OrderStatusUpdatedEvent::class]);

        $result = app(UpdateOrderAction::class)->handle(new OrderUpdateData(OrderStatusEnum::FAILED->value), $order);

        expect($result->status)->toBe(OrderStatusEnum::FAILED)
            ->and(DiscountPromotionUsage::query()->whereKey($usage->id)->exists())->toBeFalse();
        Event::assertDispatched(
            OrderStatusUpdatedEvent::class,
            fn (OrderStatusUpdatedEvent $event): bool => $event->order->id === $order->id
                && $event->order->status                                   === OrderStatusEnum::FAILED,
        );
    });

    it('fails open payment attempts when an admin closes the order', function (OrderStatusEnum $status): void {
        $order = Order::factory()->create(['status' => OrderStatusEnum::PENDING]);

        $pending = Payment::factory()->create([
            'order_id' => $order->id,
            'status'   => PaymentStatusEnum::PENDING,
        ]);
        $transaction = PaymentTransaction::factory()->create([
            'payment_id'            => $pending->id,
            'status'                => PaymentTransactionStatusEnum::INITIATED,
            'transaction_reference' => '200000013',
            'initiated_at'          => now()->subMinutes(5),
            'completed_at'          => null,
        ]);
        $completed = Payment::factory()->create([
            'order_id' => $order->id,
            'status'   => PaymentStatusEnum::COMPLETED,
        ]);

        app(UpdateOrderAction::class)->handle(new OrderUpdateData($status->value), $order);

        $transaction->refresh();
        expect($pending->fresh()->status)->toBe(PaymentStatusEnum::FAILED)
            ->and($transaction->status)->toBe(PaymentTransactionStatusEnum::FAILED)
            ->and($transaction->error_code)->toBe(PaymentTransactionErrorCodeEnum::ORDER_CLOSED->value)
            ->and($completed->fresh()->status)->toBe(PaymentStatusEnum::COMPLETED);
    })->with([OrderStatusEnum::CANCELLED, OrderStatusEnum::FAILED]);

    it('leaves open payment attempts when an admin moves the order to processing', function (): void {
        $order = Order::factory()->create(['status' => OrderStatusEnum::PENDING]);

        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'status'   => PaymentStatusEnum::PENDING,
        ]);
        $transaction = PaymentTransaction::factory()->create([
            'payment_id'            => $payment->id,
            'status'                => PaymentTransactionStatusEnum::INITIATED,
            'transaction_reference' => '200000014',
            'initiated_at'          => now()->subMinutes(5),
            'completed_at'          => null,
        ]);

        app(UpdateOrderAction::class)->handle(
            new OrderUpdateData(OrderStatusEnum::PROCESSING->value),
            $order,
        );

        expect($payment->fresh()->status)->toBe(PaymentStatusEnum::PENDING)
            ->and($transaction->fresh()->status)->toBe(PaymentTransactionStatusEnum::INITIATED);
    });

    it('does not release reservations twice when a cancelled order is cancelled again', function (): void {
        $order  = Order::factory()->create(['status' => OrderStatusEnum::PENDING]);
        $option = ProductDeliveryOption::factory()->create(['reserved_count' => 2]);
        OrderItem::factory()->for($order)->create([
            'product_delivery_option_id' => $option->id,
            'qty_ordered'                => 1,
            'status'                     => OrderItemStatusEnum::PENDING,
        ]);

        app(UpdateOrderAction::class)->handle(new OrderUpdateData(OrderStatusEnum::CANCELLED->value), $order);
        expect($option->fresh()->reserved_count)->toBe(1);

        app(UpdateOrderAction::class)->handle(new OrderUpdateData(OrderStatusEnum::CANCELLED->value), $order->fresh());
        expect($option->fresh()->reserved_count)->toBe(1);
    });

    it('refuses to reopen a cancelled order', function (): void {
        $order = Order::factory()->create([
            'status'           => OrderStatusEnum::CANCELLED,
            'cancelled_at'     => now()->subHour(),
            'cancelled_reason' => OrderCancellationReasonEnum::ADMIN,
        ]);

        expect(fn () => app(UpdateOrderAction::class)->handle(
            new OrderUpdateData(OrderStatusEnum::PENDING->value),
            $order,
        ))->toThrow(ValidationException::class);

        $order->refresh();
        expect($order->status)->toBe(OrderStatusEnum::CANCELLED)
            ->and($order->cancelled_at)->not->toBeNull()
            ->and($order->cancelled_reason)->toBe(OrderCancellationReasonEnum::ADMIN);
    });
});
