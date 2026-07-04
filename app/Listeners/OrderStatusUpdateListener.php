<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\Order\OrderStatusEnum;
use App\Enums\Product\DeliveryMethodEnum;
use App\Events\OrderStatusUpdatedEvent;
use App\Jobs\Provisioning\ProvisionBbbEnrollmentJob;
use App\Jobs\Provisioning\ProvisionImsEnrollmentJob;
use App\Jobs\Provisioning\ProvisionMoodleEnrollmentJob;
use App\Jobs\Provisioning\ProvisionMoodleQuizJob;
use App\Jobs\Provisioning\ProvisionSkyroomEnrollmentJob;
use App\Jobs\Provisioning\ProvisionSpotPlayerEnrollmentJob;
use App\Models\Order;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

final class OrderStatusUpdateListener implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(OrderStatusUpdatedEvent $event): void
    {
        /** @var Order $order */
        $order = $event->order->fresh();
        if (! $order) {
            return;
        }
        if ($order->status !== OrderStatusEnum::COMPLETED) {
            return;
        }

        $order = $order->load([
            'customer',
            'firstPayment',
            'items' => fn ($q) => $q->with('enrollment', 'productDeliveryOption'),
        ]);

        foreach ($order->items as $item) {
            if (! $item->enrollment || ! $item->productDeliveryOption) {
                continue;
            }

            if (isset($item->productDeliveryOption->details_json['ims_course_code'])) {
                ProvisionImsEnrollmentJob::dispatch($item->enrollment->id, $order->firstPayment->id);
            }

            $deliveryMethod = $item->productDeliveryOption->delivery_method;
            if ($deliveryMethod === DeliveryMethodEnum::LMS_MOODLE) {
                ProvisionMoodleEnrollmentJob::dispatch($item->enrollment->id);
            }

            if ($deliveryMethod === DeliveryMethodEnum::VIDEO_PLATFORM_SPOTPLAYER) {
                ProvisionSpotPlayerEnrollmentJob::dispatch($item->enrollment->id);
            }

            if ($deliveryMethod === DeliveryMethodEnum::LIVE_SESSION_BBB) {
                ProvisionBbbEnrollmentJob::dispatch($item->enrollment->id);
            }

            if ($deliveryMethod === DeliveryMethodEnum::LIVE_SESSION_SKYROOM) {
                ProvisionSkyroomEnrollmentJob::dispatch($item->enrollment->id);
            }

            if ($deliveryMethod !== DeliveryMethodEnum::LMS_MOODLE
                && isset($item->productDeliveryOption->details_json['moodle_quiz_course_id'])
            ) {
                ProvisionMoodleQuizJob::dispatch($item->enrollment->id);
            }
        }
    }
}
