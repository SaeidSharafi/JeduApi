<?php

declare(strict_types=1);

namespace Tests\Unit\Listeners;

use App\Enums\Product\DeliveryMethodEnum;
use App\Events\EnrollmentStatusChanged;
use App\Events\PaymentCompletedEvent;
use App\Listeners\ProvisionPaidResourcesListener;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\ProductDeliveryOption;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

describe('ProvisionPaidResourcesListener', function (): void {

    beforeEach(function (): void {
        // This listener is queued, but since it dispatches no jobs yet,
        // faking the queue is good practice for the future.
        Queue::fake();
    });

    it('executes all delivery method checks without error', function (): void {
        Event::fake([
            EnrollmentStatusChanged::class,
        ]);
        $order   = Order::factory()->create();
        $payment = Payment::factory()->for($order)->create(['status' => 'completed']);

        $inPersonItem = OrderItem::factory()->for($order)->create([
            'product_delivery_option_id' => ProductDeliveryOption::factory()->create(['delivery_method' => DeliveryMethodEnum::IN_PERSON])->id,
        ]);
        Enrollment::factory()->for($inPersonItem)->create();

        $moodleItem = OrderItem::factory()->for($order)->create([
            'product_delivery_option_id' => ProductDeliveryOption::factory()->create(['delivery_method' => DeliveryMethodEnum::LMS_MOODLE])->id,
        ]);
        Enrollment::factory()->for($moodleItem)->create();

        $spotplayerItem = OrderItem::factory()->for($order)->create([
            'product_delivery_option_id' => ProductDeliveryOption::factory()->create(['delivery_method' => DeliveryMethodEnum::VIDEO_PLATFORM_SPOTPLAYER])->id,
        ]);
        Enrollment::factory()->for($spotplayerItem)->create();

        $downloadItem = OrderItem::factory()->for($order)->create([
            'product_delivery_option_id' => ProductDeliveryOption::factory()->create(['delivery_method' => DeliveryMethodEnum::DIRECT_DOWNLOAD])->id,
        ]);
        Enrollment::factory()->for($downloadItem)->create();

        OrderItem::factory()->for($order)->create();

        $event = new PaymentCompletedEvent($payment);
        (new ProvisionPaidResourcesListener())->handle($event);

        // Since the listener currently has no actions (no job dispatching, no status changes),
        // the only meaningful assertion is that it ran without throwing an exception.
        // We also assert no jobs were pushed, which is correct for the current code.
        $this->assertTrue(true);
        Queue::assertNothingPushed();
    });

    it('handles an order with no items gracefully', function (): void {
        $order   = Order::factory()->create(); // No items created for this order
        $payment = Payment::factory()->for($order)->create(['status' => 'completed']);

        $event = new PaymentCompletedEvent($payment);
        (new ProvisionPaidResourcesListener())->handle($event);

        $this->assertTrue(true);
    });

    it('returns early if the order is missing from the payment', function (): void {

        $payment = new Payment(); // A fake payment object in memory without a real order
        $event   = new PaymentCompletedEvent($payment);

        (new ProvisionPaidResourcesListener())->handle($event);

        $this->assertTrue(true);
    });
});
