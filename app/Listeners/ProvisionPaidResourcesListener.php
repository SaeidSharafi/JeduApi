<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\Product\DeliveryMethodEnum;
use App\Events\PaymentCompletedEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

final class ProvisionPaidResourcesListener implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct() {}

    public function handle(PaymentCompletedEvent $event): void
    {
        $order = $event->payment->order()->with([
            'customer',
            'items' => fn ($q) => $q->with('enrollment', 'productDeliveryOption'),
        ])->first();

        if (! $order) {
            return;
        }

        foreach ($order->items as $item) {

            if ($item->enrollment) {
                $deliveryMethod = $item->enrollment->productDeliveryOption->delivery_method;
                if ($deliveryMethod === DeliveryMethodEnum::IN_PERSON) {
                    /**
                     * TODO: Implement the job to provision In person details.
                     * This could involve calling an API to create a user, enrol them in a course,
                     */
                }
                // Dispatch specific jobs based on the product's delivery method.
                if ($deliveryMethod === DeliveryMethodEnum::LMS_MOODLE) {
                    /**
                     * TODO: Implement the job to provision access to Moodle LMS.
                     * This could involve calling an API to create a user, enrol them in a course,
                     */
                }

                if ($deliveryMethod === DeliveryMethodEnum::VIDEO_PLATFORM_SPOTPLAYER) {
                    /**
                     * TODO: Implement the job to provision access to SpotPlayer.
                     */
                }

                if ($deliveryMethod === DeliveryMethodEnum::DIRECT_DOWNLOAD) {
                    /**
                     * TODO: Implement the job to provision direct download access.
                     */
                }
            }
        }

    }
}
