<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\User;

use App\Actions\Admin\Staff\BanStaffAction;
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
final class BanStaffController extends Controller
{
    /**
     * Ban a staff account.
     *
     * Sets the ban flag and instantly revokes all of the staff member's active tokens.
     *
     * @responseFile 200 resources/responses/admin/staff/show.json
     * @responseFile 403 resources/responses/403.json
     * @responseFile 404 resources/responses/404.json
     */
    public function __invoke(Staff $staff, BanStaffAction $action): ApiResponseInterface
    {
        Gate::authorize('ban', $staff);

        $staff = $action->handle($staff);

        return apiResponse()->success(
            ShowStaffData::from($staff),
            __('messages.staff.banned')
        );
    }
}
