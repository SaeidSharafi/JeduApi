<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Auth;

use App\Actions\Auth\PasswordLoginAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Admin\Auth\StaffData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;

/**
 * @group Admin - Staff Auth
 *
 * APIs for staff authentication
 *
 * @authenticated Staff
 */
final class StaffPasswordLoginController extends Controller
{
    public function __construct(
        protected PasswordLoginAction $action
    ) {}

    /**
     * Authenticate Staff with identifier (phone/email) and password
     *
     * Used when the /auth/initiate step determines a password exists and is required.
     *
     *
     * @throws \App\Exceptions\UserNotFoundException
     *
     * @responseFile 200 resources/responses/admin/auth/staff.login.json
     *
     * @response 404 {
     *       "message": "User not found",
     *       "errors": null,
     *       "metadata": []
     *  }
     * @response 422{
     *       "message": "The provided credentials are incorrect.",
     *       "errors": {
     *       "password": [
     *           "The provided credentials are incorrect."
     *           ]
     *       },
     *       "metadata": []
     *  }
     */
    public function __invoke(LoginRequest $request): ApiResponseInterface
    {
        $type = filter_var($request->identifier, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';

        $user = Staff::when(
            $type === 'email',
            fn (Builder $q) => $q->where('email', $request->identifier),
            fn (Builder $q) => $q->where('phone', $request->identifier)
        )->firstOrFail();

        $token = $this->action->execute(
            $user,
            $type,
            $request->password,
            guard: 'staff'
        );
        $permissions = Cache::rememberForever(config('cache.keys.all_permissions'), function () {
            return Permission::query()->where('guard_name', 'staff')->get()->pluck('name')->toArray();
        });

        cookie()->queue(
            'staff_token',
            $token->plainTextToken,
            (int) config('sanctum.expiration'),
            '/',
            null,
            app()->isProduction() || (bool) config('session.secure'),
            true,
            false,
            'Lax'
        );

        return apiResponse()->success([
            'token'      => $token->plainTextToken,
            'expires_at' => $token->accessToken->expires_at,
            'type'       => 'Bearer',
            'user'       => StaffData::from($user)
                ->additional([
                    'roles'       => $user->getRoleNames(),
                    'permissions' => $user->getAllPermissions()->pluck('name'),
                ]),
            'permissions' => $permissions,
        ], __('messages.auth.login.success'));
    }
}
