<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\Profile;

use App\Actions\Auth\ChangePasswordAction;
use App\Actions\Auth\ResetPasswordAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Auth\ChagePasswordData;
use App\Http\Controllers\Controller;
use App\Models\User;

/**
 * @group Shop - Profile
 *
 * @authenticated User
 */
final class CustomerChangePasswordController extends Controller
{
    public function __construct(
        protected ResetPasswordAction $action
    ) {}

    /**
     * Change authorized user password
     *
     * @bodyParam current_password string nullable The current password. Example: currentpassword
     * @bodyParam password string required The new password. Example: newpassword
     * @bodyParam password_confirmation string required The new password confirmation. Example: newpassword
     *
     * @responseFile 422 resources/responses/422.json
     * @responseFile 404 resources/responses/404.json
     */
    public function __invoke(ChagePasswordData $request, ChangePasswordAction $action): ApiResponseInterface
    {
        /** @var ?User $user */
        $user = auth()->user();

        abort_unless($user !== null, 404);

        $action->handle($user, $request);
        return apiResponse()->success(message:__('messages.auth.password_reset'));
    }
}
