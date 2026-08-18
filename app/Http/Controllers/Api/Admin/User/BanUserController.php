<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\User;

use App\Actions\Admin\User\BanUserAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Admin\User\ShowUserData;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * @group Admin - Users
 *
 * @authenticated
 */
final class BanUserController extends Controller
{
    /**
     * Ban a customer.
     *
     * Sets the ban flag and instantly revokes all of the customer's active tokens.
     *
     * @responseFile 200 resources/responses/admin/user/show.json
     * @responseFile 403 resources/responses/403.json
     * @responseFile 404 resources/responses/404.json
     */
    public function __invoke(User $user, BanUserAction $action): ApiResponseInterface
    {
        Gate::authorize('ban', $user);

        $user = $action->handle($user);

        return apiResponse()->success(
            ShowUserData::from($user),
            __('messages.user.banned')
        );
    }
}
