<?php

declare(strict_types=1);

namespace App\Actions\Admin\Refund;

use App\Models\BundlePurchase;
use App\Models\Enrollment;
use App\Services\Provisioning\EnrollmentRevocationService;
use Illuminate\Support\Collection;

/**
 * Retries every outstanding component revocation of one Bundle Purchase.
 *
 * Components whose revocation already succeeded are never contacted again, so
 * retrying one component cannot undo another component's completed revocation.
 */
final readonly class RetryBundlePurchaseRevocationAction
{
    public function __construct(private EnrollmentRevocationService $revocations) {}

    /** @return Collection<int, Enrollment> */
    public function handle(BundlePurchase $bundlePurchase): Collection
    {
        $bundlePurchase->loadMissing('components.enrollment');

        $attemptIds  = [];
        $enrollments = [];
        foreach ($bundlePurchase->components as $component) {
            $enrollment = $component->enrollment;
            if (! $enrollment || $enrollment->isRevocationComplete()) {
                continue;
            }

            $attemptIds    = array_merge($attemptIds, $this->revocations->retry($enrollment));
            $enrollments[] = $enrollment->fresh();
        }

        $this->revocations->dispatchAttempts($attemptIds);

        return new Collection($enrollments);
    }
}
