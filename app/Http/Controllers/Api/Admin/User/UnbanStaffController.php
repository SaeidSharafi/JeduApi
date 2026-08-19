<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\User;

use App\Actions\Admin\Staff\UnbanStaffAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Admin\Staff\ShowStaffData;
use App\Http\Controllers\Controller;
use App\Models\Staff;
use Illuminate\Support\Facades\Gate;

/**
 * @group Admin - Staff Management
 *
 * @authenticated
 */
final class UnbanStaffController extends Controller
{
    /**
     * Unban a staff account.
     *
     * Clears the ban flag and timestamp, restoring password and OTP login.
     *
     * @responseFile 200 resources/responses/admin/staff/show.json
     * @responseFile 403 resources/responses/403.json
     * @responseFile 404 resources/responses/404.json
     */
    public function __invoke(Staff $staff, UnbanStaffAction $action): ApiResponseInterface
    {
        Gate::authorize('ban', $staff);

        $staff = $action->handle($staff);

        return apiResponse()->success(
            ShowStaffData::from($staff),
            __('messages.staff.unbanned')
        );
    }
}
