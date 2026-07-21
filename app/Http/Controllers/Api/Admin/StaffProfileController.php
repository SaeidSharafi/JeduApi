<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Admin\UpdateStaffProfileAction;
use App\Actions\Shop\UpdateProfileAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Admin\Auth\StaffData;
use App\Data\Admin\UpdateStaffProfileData;
use App\Data\Shop\Customer\CustomerData;
use App\Data\Shop\Customer\UpdateProfileData;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiSuccessResponse;
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
    /**
     * Display the authenticated staff's profile.
     *
     * @responseFile 200 resources/responses/admin/profile/show.json
     */
    public function show(): ApiResponseInterface
    {
        return apiResponse()->success(StaffData::from(auth('staff')->user()));
    }

    /**
     * Update the authenticated staff's profile.
     *
     * @responseFile 200 resources/responses/admin/profile/show.json
     */
    public function update(UpdateStaffProfileData $data, UpdateStaffProfileAction $action): ApiResponseInterface
    {
        $action->handle($data, auth('staff')->user());

        return apiResponse()->updated(StaffData::from(auth('staff')->user()->fresh()), model: Staff::class);
    }
}
