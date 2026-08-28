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
final class AdvancedProvisioningDiagnosticsController extends Controller
{
    /**
     * Return authorized advanced provisioning history.
     *
     * @responseFile resources/responses/admin/enrollment/provisioning-diagnostics-advanced.json
     */
    public function __invoke(Enrollment $enrollment, ProvisioningDiagnosticsService $diagnostics): ApiResponseInterface
    {
        Gate::authorize('viewDiagnostics', $enrollment);

        return apiResponse()->success($diagnostics->diagnostics($enrollment, true));
    }
}
