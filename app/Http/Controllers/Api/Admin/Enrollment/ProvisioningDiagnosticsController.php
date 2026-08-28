<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Enrollment;

use App\Contracts\ApiResponseInterface;
use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Services\Provisioning\ProvisioningDiagnosticsService;
use Illuminate\Support\Facades\Gate;

/**
 * @group Admin - Enrollment
 *
 * @subgroup Provisioning diagnostics
 *
 * @authenticated
 */
final class ProvisioningDiagnosticsController extends Controller
{
    /**
     * Return safe provisioning diagnostics.
     *
     * @responseFile resources/responses/admin/enrollment/provisioning-diagnostics.json
     */
    public function __invoke(Enrollment $enrollment, ProvisioningDiagnosticsService $diagnostics): ApiResponseInterface
    {
        Gate::authorize('viewProvisioningDiagnostics', $enrollment);

        return apiResponse()->success($diagnostics->diagnostics($enrollment));
    }
}
