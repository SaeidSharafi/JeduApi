<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\Profile;

use App\Actions\Shop\UpdateProfileAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Shop\Customer\CustomerData;
use App\Data\Shop\Customer\UpdateProfileData;
use App\Http\Controllers\Controller;

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
    public function show(): ApiResponseInterface
    {
        return apiResponse()->success(CustomerData::fromUser(auth()->user()));
    }

    /**
     * Update the authenticated user's profile.
     *
     * @responseFile 200 resources/responses/shop/profile/show.json
     */
    public function update(UpdateProfileData $data, UpdateProfileAction $action): ApiResponseInterface
    {
        $user = $action->handle($data, auth()->user());

        return apiResponse()->updated(CustomerData::fromUser($user), model: $user);
    }
}
