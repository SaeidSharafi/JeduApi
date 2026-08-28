<?php

declare(strict_types=1);

namespace Tests\Unit\Listeners;

use App\Enums\Order\OrderStatusEnum;
use App\Enums\Product\DeliveryMethodEnum;
use App\Events\EnrollmentStatusChanged;
use App\Events\OrderStatusUpdatedEvent;
use App\Jobs\Provisioning\ProvisionBbbEnrollmentJob;
use App\Jobs\Provisioning\ProvisionImsEnrollmentJob;
use App\Jobs\Provisioning\ProvisionMoodleEnrollmentJob;
use App\Jobs\Provisioning\ProvisionMoodleQuizJob;
use App\Jobs\Provisioning\ProvisionSpotPlayerEnrollmentJob;
use App\Listeners\OrderStatusUpdateListener;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\ProductDeliveryOption;
use App\Services\Enrollment\ProvisioningPlanResolver;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

describe('OrderStatusUpdateListener', function (): void {

    beforeEach(function (): void {
        Queue::fake([
            ProvisionImsEnrollmentJob::class,
            ProvisionMoodleEnrollmentJob::class,
            ProvisionMoodleQuizJob::class,
            ProvisionSpotPlayerEnrollmentJob::class,
            ProvisionBbbEnrollmentJob::class,
        ]);
    });

    it('dispatches provisioning jobs based on delivery method', function (): void {
        Event::fake([
            EnrollmentStatusChanged::class,
        ]);
        $order = Order::factory()->create(['status' => OrderStatusEnum::COMPLETED]);
        Payment::factory()->for($order)->create(['status' => 'completed']);

        $inPersonItem = OrderItem::factory()->for($order)->create([
            'product_delivery_option_id' => ProductDeliveryOption::factory()
                ->create(['delivery_method' => DeliveryMethodEnum::IN_PERSON])->id,
        ]);
        $inPersonItem->productDeliveryOption()->update([
            'details_json' => ['ims_course_code' => 'IMS-IN-PERSON'],
        ]);
        Enrollment::factory()->for($inPersonItem)->create();

        $moodleItem = OrderItem::factory()->for($order)->create([
            'product_delivery_option_id' => ProductDeliveryOption::factory()
                ->create([
                    'delivery_method' => DeliveryMethodEnum::LMS_MOODLE,
                    'details_json'    => [
                        'moodle_course_id' => 123,
                        'ims_course_code'  => 'IMS-MOODLE',
                    ],
                ])->id,
        ]);
        Enrollment::factory()->for($moodleItem)->create();

        $spotplayerItem = OrderItem::factory()->for($order)->create([
            'product_delivery_option_id' => ProductDeliveryOption::factory()
                ->create([
                    'delivery_method' => DeliveryMethodEnum::VIDEO_PLATFORM_SPOTPLAYER,
                    'details_json'    => [
                        'spot_id'         => 'SPOT-1',
                        'ims_course_code' => 'IMS-SPOT',
                    ],
                ])->id,
        ]);
        Enrollment::factory()->for($spotplayerItem)->create();

        $downloadItem = OrderItem::factory()->for($order)->create([
            'product_delivery_option_id' => ProductDeliveryOption::factory()
                ->create([
                    'delivery_method' => DeliveryMethodEnum::DIRECT_DOWNLOAD,
                    'details_json'    => ['ims_course_code' => 'IMS-DOWNLOAD'],
                ])->id,
        ]);
        Enrollment::factory()->for($downloadItem)->create();

        $bbbItem = OrderItem::factory()->for($order)->create([
            'product_delivery_option_id' => ProductDeliveryOption::factory()
                ->create([
                    'delivery_method' => DeliveryMethodEnum::LIVE_SESSION_BBB,
                    'details_json'    => [
                        'meeting_id'      => 'BBB-1',
                        'ims_course_code' => 'IMS-BBB',
                    ],
                ])->id,
        ]);
        Enrollment::factory()->for($bbbItem)->create();

        OrderItem::factory()->for($order)->create();
        $event = new OrderStatusUpdatedEvent($order);

        (new OrderStatusUpdateListener(app(ProvisioningPlanResolver::class)))->handle($event);

        Queue::assertPushed(ProvisionImsEnrollmentJob::class, 5);
        Queue::assertPushed(ProvisionMoodleEnrollmentJob::class, 1);
        Queue::assertPushed(ProvisionSpotPlayerEnrollmentJob::class, 1);
        Queue::assertPushed(ProvisionBbbEnrollmentJob::class, 1);
    });

    it('handles an order with no items gracefully', function (): void {
        $order = Order::factory()->create(['status' => OrderStatusEnum::COMPLETED]);

        $event = new OrderStatusUpdatedEvent($order);
        (new OrderStatusUpdateListener(app(ProvisioningPlanResolver::class)))->handle($event);

        $this->assertTrue(true);
    });

    it('returns early if the order is missing from the payment', function (): void {

        $order = new Order(); // A fake payment object in memory without a real order
        $event = new OrderStatusUpdatedEvent($order);

        (new OrderStatusUpdateListener(app(ProvisioningPlanResolver::class)))->handle($event);

        $this->assertTrue(true);
    });

    it('dispatches ProvisionMoodleQuizJob when moodle_quiz_course_id is set on non-Moodle delivery', function (): void {
        Event::fake([
            EnrollmentStatusChanged::class,
        ]);

        $order = Order::factory()->create(['status' => OrderStatusEnum::COMPLETED]);
        Payment::factory()->for($order)->create(['status' => 'completed']);

        $item = OrderItem::factory()->for($order)->create([
            'product_delivery_option_id' => ProductDeliveryOption::factory()
                ->create([
                    'delivery_method' => DeliveryMethodEnum::VIDEO_PLATFORM_SPOTPLAYER,
                    'details_json'    => [
                        'spot_id'               => 'SPOT-QUIZ',
                        'moodle_quiz_course_id' => 999,
                    ],
                ])->id,
        ]);
        Enrollment::factory()->for($item)->create();

        $event = new OrderStatusUpdatedEvent($order);
        (new OrderStatusUpdateListener(app(ProvisioningPlanResolver::class)))->handle($event);

        Queue::assertPushed(ProvisionMoodleQuizJob::class, 1);
    });
});
