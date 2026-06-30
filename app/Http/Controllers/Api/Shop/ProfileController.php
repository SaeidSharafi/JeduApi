<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop;

use App\Actions\Shop\UpdateProfileAction;
use App\Data\Shop\Customer\CustomerData;
use App\Data\Shop\Customer\UpdateProfileData;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiSuccessResponse;

/**
 * @group Shop - Profile
 *
 * APIs for managing customer profile.
 *
 * @authenticated user
 */
final class ProfileController extends Controller
{
    /**
     * Display the authenticated user's profile.
     *
     * @responseFile 200 resources/responses/shop/profile/show.json
     */
    public function show(): ApiSuccessResponse
    {
        return apiResponse()->success(CustomerData::from(auth()->user()));
    }

    /**
     * Update the authenticated user's profile.
     *
     * @responseFile 200 resources/responses/shop/profile/show.json
     */
    public function update(UpdateProfileData $data, UpdateProfileAction $action): ApiSuccessResponse
    {
        $user = $action->handle($data, auth()->user());

        return apiResponse()->updated(CustomerData::from($user), model: $user);
    }
}
