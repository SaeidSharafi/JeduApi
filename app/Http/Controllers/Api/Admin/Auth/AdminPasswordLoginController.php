<?php

namespace App\Http\Controllers\Api\Admin\Auth;

use App\Actions\Auth\PasswordLoginAction;
use App\Contracts\ApiResponseInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\Auth\AdminResource;
use App\Http\Resources\Auth\UserResource;
use App\Models\Admin;
use Illuminate\Database\Eloquent\Builder;

class AdminPasswordLoginController extends Controller
{
    public function __construct(
        protected PasswordLoginAction $action
    ) {
    }

    /**
     * Authenticate Admin with identifier (phone/email) and password
     *
     * Used when the /auth/initiate step determines a password exists and is required.
     *
     *
     * @throws \App\Exceptions\UserNotFoundException
     *
     * @group Admin Authentication
     * @response {
     *       "message": "User Logged in successfully",
     *       "data": {
     *           "token": "8|juaIqinWuRHiE2vnr3TGr7Pjuy04oHFFilPXxd2Y26f5f131",
     *           "expires_at": null,
     *           "type": "Bearer",
     *           "user": {
     *               "id": 1,
     *               "name": null,
     *               "phone": "09359933642",
     *               "email": null,
     *               "phone_verified_at": null,
     *               "email_verified_at": null
     *           }
     *       },
     *       "metadata": []
     *  }
     * @response 404 {
     *       "message": "User not found",
     *       "errors": null,
     *       "metadata": []
     *  }
     *
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

        $user = Admin::when(
            $type === 'email',
            fn(Builder $q) => $q->where('email', $request->identifier),
            fn(Builder $q) => $q->where('phone', $request->identifier)
        )->firstOrFail();

        $token = $this->action->execute(
            $user,
            $type,
            $request->password,
            guard: 'admin'
        );

        return response()->success([
            'token'      => $token->plainTextToken,
            'expires_at' => $token->accessToken->expires_at,
            'type'       => 'Bearer',
            'user'       => AdminResource::make($user),
        ], 'User Logged in successfully');
    }
}
