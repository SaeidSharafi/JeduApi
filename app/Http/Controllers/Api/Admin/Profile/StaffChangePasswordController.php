<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Profile;

use App\Actions\Auth\ChangePasswordAction;
use App\Actions\Auth\ResetPasswordAction;
use App\Contracts\ApiResponseInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Models\Staff;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * @group Admin - Profile
 *
 * @authenticated Staff
 */
final class StaffChangePasswordController extends Controller
{
    public function __construct(
        protected ResetPasswordAction $action
    ) {}

    /**
     * Change authorized staff password
     *
     * @bodyParam current_password string nullable The current password. Example: currentpassword
     * @bodyParam password string required The new password. Example: newpassword
     * @bodyParam password_confirmation string required The new password confirmation. Example: newpassword
     *
     * @responseFile 422 resources/responses/422.json
     * @responseFile 404 resources/responses/404.json
     */
    public function __invoke(ChangePasswordRequest $request, ChangePasswordAction $action): ApiResponseInterface
    {
        /** @var Staff $staff */
        $staff = auth('staff')->user();

        abort_unless($staff !== null, 404);

        $action->handle($staff, $request);

        return apiResponse()->success(message: __('messages.auth.password_reset'));
    }
}
