<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Enrollment;

use App\Contracts\ApiResponseInterface;
use App\Data\Admin\Enrollment\ConfirmEnrollmentRevocationData;
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
final class ConfirmEnrollmentRevocationController extends Controller
{
    /**
     * Manually confirm that an unsupported provider revocation was completed.
     *
     * Use this only after the external provider access was actually removed
     * outside the system. It marks every outstanding provider as revoked and
     * releases Purchase Eligibility.
     *
     * @responseFile 200 resources/responses/admin/enrollment/revocation.json
     * @responseFile 403 resources/responses/403.json
     */
    public function __invoke(
        ConfirmEnrollmentRevocationData $data,
        Enrollment $enrollment,
        EnrollmentRevocationService $revocations,
    ): ApiResponseInterface {
        Gate::authorize('confirmRevocation', $enrollment);

        $revocations->confirmManually($enrollment, (int) auth('staff')->id(), $data->reason);

        return apiResponse()->success(
            data: EnrollmentRevocationData::from($enrollment->fresh()),
            message: __('messages.enrollment.revocation_confirmed'),
        );
    }
}
