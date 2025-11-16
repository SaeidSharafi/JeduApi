<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\EnrollmentStatusEnum;
use App\Events\EnrollmentStatusChanged;
use App\Models\Enrollment;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

final class UpdateProductDeliveryOptionEnrolledCount implements ShouldQueue
{
    use InteractsWithQueue;

    public bool $afterCommit = true;

    /**
     * Handle the event.
     *
     * This listener updates the enrolled_count on ProductDeliveryOption when:
     * - An enrollment is created with occupying status (increment)
     * - An enrollment status changes from occupying to non-occupying (decrement)
     * - An enrollment status changes from non-occupying to occupying (increment)
     *
     * Occupying statuses (count towards capacity):
     * - ACTIVE: User has active access
     * - PENDING_PROVISIONING: Access being set up, seat reserved
     * - SUSPENDED: Temporary block by admin, seat still reserved
     *
     * Non-occupying statuses (do not count towards capacity):
     * - AWAITING_PAYMENT: Order created, not paid yet
     * - CANCELLED: Access permanently revoked, seat freed
     * - EXPIRED: Access period ended, seat freed
     * - PROVISIONING_FAILED: Setup failed, seat freed
     */
    public function handle(EnrollmentStatusChanged $event): void
    {
        $deliveryOption = $event->enrollment->productDeliveryOption?->fresh();

        if (! $deliveryOption) {
            return;
        }

        $occupying = array_map(static fn (EnrollmentStatusEnum $e): string => $e->value, EnrollmentStatusEnum::occupyingStatuses());

        $count = Enrollment::query()
            ->where('product_delivery_option_id', $deliveryOption->id)
            ->whereIn('enrollment_status', EnrollmentStatusEnum::occupyingStatuses())
            ->count();

        $deliveryOption->enrolled_count = $count;
        $deliveryOption->saveQuietly();
    }
}
