<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Enrollment;

use App\Contracts\ApiResponseInterface;
use App\Data\Admin\Enrollment\EnrollmentRevocationData;
use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Services\Provisioning\EnrollmentRevocationService;
use Illuminate\Support\Facades\Gate;

/**
 * @group Admin - Enrollment
 *
 * @subgroup Enrollment Revocation
 *
 * @authenticated
 */
final class RetryEnrollmentRevocationController extends Controller
{
    /**
     * Retry the external provider revocation of one Enrollment.
     *
     * Only providers whose revocation has not succeeded are retried; providers
     * already revoked are never contacted again.
     *
     * @responseFile 200 resources/responses/admin/enrollment/revocation.json
     * @responseFile 403 resources/responses/403.json
     */
    public function __invoke(
        Enrollment $enrollment,
        EnrollmentRevocationService $revocations,
    ): ApiResponseInterface {
        Gate::authorize('retryRevocation', $enrollment);

        $revocations->dispatchAttempts($revocations->retry($enrollment));

        return apiResponse()->success(
            data: EnrollmentRevocationData::from($enrollment->fresh()),
            message: __('messages.enrollment.revocation_retried'),
        );
    }
}
