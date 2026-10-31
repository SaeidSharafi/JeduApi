<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Profile;

use App\Actions\Admin\Staff\GetStaffAccessAction;
use App\Actions\Admin\UpdateStaffProfileAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Admin\Auth\StaffData;
use App\Data\Admin\UpdateStaffProfileData;
use App\Http\Controllers\Controller;
use App\Models\Staff;

/**
 * @group Admin - Profile
 *
 * APIs for managing admin profile.
 *
 * @authenticated user
 */
final class StaffProfileController extends Controller
{
    public function __construct(
        protected GetStaffAccessAction $access
    ) {}

    /**
     * Display the authenticated staff's profile.
     *
     * @responseFile 200 resources/responses/admin/profile/show.json
     */
    public function show(): ApiResponseInterface
    {
        /** @var Staff $staff */
        $staff = auth('staff')->user();

        return apiResponse()->success($this->staffData($staff));
    }

    /**
     * Update the authenticated staff's profile.
     *
     * @responseFile 200 resources/responses/admin/profile/show.json
     */
    public function update(UpdateStaffProfileData $data, UpdateStaffProfileAction $action): ApiResponseInterface
    {
        /** @var Staff $staff */
        $staff = auth('staff')->user();

        $action->handle($data, $staff);
        $staff->refresh();

        return apiResponse()->updated($this->staffData($staff), model: Staff::class);
    }

    private function staffData(Staff $staff): StaffData
    {
        return StaffData::from($staff)->additional($this->access->handle($staff));
    }
}
