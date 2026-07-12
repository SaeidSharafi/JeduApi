<?php

declare(strict_types=1);

namespace Tests\Feature\Listeners;

use App\Events\PaymentCompletedEvent;
use App\Listeners\UpdateStatusesAfterPaymentListener;
use App\Models\Order;
use App\Models\Payment;
use App\Services\OrderStatusService;
use Mockery\MockInterface;

describe('UpdateStatusesAfterPaymentListener', function (): void {

    it('correctly calls the OrderStatusService when a payment is completed', function (): void {
        // --- Arrange ---
        // 1. Create the real data that will be in the event
        $order   = Order::factory()->create();
        $payment = Payment::factory()->for($order)->create();
        $event   = new PaymentCompletedEvent($payment);

        // 2. Mock the OrderStatusService. We don't want to test the service here,
        //    we just want to ensure our listener calls it correctly.
        $this->mock(OrderStatusService::class, function (MockInterface $mock) use ($order): void {
            // We expect the 'updateStatusesAfterPayment' method to be called exactly once
            // with an Order object that has the same ID as the one we created.
            $mock->shouldReceive('handlePaymentCompletion')
                ->once()
                ->withArgs(function ($argOrder) use ($order) {
                    return $argOrder instanceof Order && $argOrder->id === $order->id;
                });
        });

        // --- Act ---
        // 3. Resolve the listener from the container (so it gets the mocked service)
        //    and call its handle method.
        $listener = resolve(UpdateStatusesAfterPaymentListener::class);
        $listener->handle($event);

        // --- Assert ---
        // The mock assertion `shouldReceive('...')->once()` handles the assertion.
        // If the service method wasn't called, or was called with the wrong order,
        // the test will fail.
    });

    it('does nothing if the payment has no associated order', function (): void {
        // --- Arrange ---
        $paymentWithoutOrder = Payment::factory()->create([
            'order_id' => null,
        ]);
        $event               = new PaymentCompletedEvent($paymentWithoutOrder);

        // Mock the service but expect it to NEVER be called.
        $this->mock(OrderStatusService::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('handlePaymentCompletion');
        });

        // --- Act ---
        $listener = resolve(UpdateStatusesAfterPaymentListener::class);
        $listener->handle($event);

        // --- Assert ---
        // The mock assertion `shouldNotReceive` will pass if the method was not called.
    });
});
