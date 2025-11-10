<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\EnrollmentStatusEnum;
use App\Events\EnrollmentStatusChanged;

final class UpdateProductDeliveryOptionEnrolledCount
{
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
     * - CANCELLED: Access permanently revoked, seat freed
     * - EXPIRED: Access period ended, seat freed
     * - PROVISIONING_FAILED: Setup failed, seat freed
     */
    public function handle(EnrollmentStatusChanged $event): void
    {
        $deliveryOption = $event->enrollment->productDeliveryOption;

        if (! $deliveryOption) {
            return; // Safety check
        }

        $oldStatus = $event->oldStatus;
        $newStatus = $event->newStatus;

        // Define statuses that "occupy" a seat
        $occupyingStatuses = [
            EnrollmentStatusEnum::ACTIVE,
            EnrollmentStatusEnum::PENDING_PROVISIONING,
            EnrollmentStatusEnum::SUSPENDED, // Temporary block, but seat is still reserved
        ];

        $wasOccupying = $oldStatus && in_array($oldStatus, $occupyingStatuses, true);
        $isOccupying  = in_array($newStatus, $occupyingStatuses, true);

        // Determine if we need to increment or decrement
        if (! $wasOccupying && $isOccupying) {
            // Status changed to occupying (e.g., created as ACTIVE, or CANCELLED -> ACTIVE)
            $deliveryOption->increment('enrolled_count');
        } elseif ($wasOccupying && ! $isOccupying) {
            // Status changed from occupying to non-occupying (e.g., ACTIVE -> CANCELLED)
            $deliveryOption->decrement('enrolled_count');
        }
        // If both were occupying or both were non-occupying, no change needed
    }
}
