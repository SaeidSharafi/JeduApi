<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\User;

use App\Actions\Admin\User\UnbanUserAction;
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
final class UnbanUserController extends Controller
{
    /**
     * Unban a customer.
     *
     * Clears the ban flag and timestamp, restoring password and OTP login.
     *
     * @responseFile 200 resources/responses/admin/user/show.json
     * @responseFile 403 resources/responses/403.json
     * @responseFile 404 resources/responses/404.json
     */
    public function __invoke(User $user, UnbanUserAction $action): ApiResponseInterface
    {
        Gate::authorize('ban', $user);

        $user = $action->handle($user);

        return apiResponse()->success(
            ShowUserData::from($user),
            __('messages.user.unbanned')
        );
    }
}
