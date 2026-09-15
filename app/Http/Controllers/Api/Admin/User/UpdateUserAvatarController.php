<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\User;

use App\Actions\User\UpdateUserAvatarAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Admin\User\ShowUserData;
use App\Data\Admin\User\UpdateUserAvatarData;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * @group Admin - User Management
 *
 * APIs for managing users in the system.
 *
 * @authenticated Staff
 */
final class UpdateUserAvatarController extends Controller
{
    /**
     * Replace the specified User's avatar.
     *
     * The previous avatar is detached; the new media is attached to the `avatar` tag.
     *
     * @responseFile 200 resources/responses/admin/user/show.json
     * @responseFile 403 resources/responses/403.json
     * @responseFile 404 resources/responses/404.json
     * @responseFile 422 resources/responses/422.json
     */
    public function __invoke(
        UpdateUserAvatarData $data,
        User $user,
        UpdateUserAvatarAction $action
    ): ApiResponseInterface {
        Gate::authorize('update', $user);

        $action->handle($user, $data->file);
        $user->load('media');

        return apiResponse()->updated(ShowUserData::from(
            [
                ...$user->toArray(),
                'media' => $user->getAllMedia(false, ['avatar']),
            ]
        ), model: User::class);
    }
}
