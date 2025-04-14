<?php

namespace App\Http\Controllers\Api\V1\Admin\Auth;

use App\Actions\Api\V1\Auth\VerifyOtpForResetAction;
use App\Actions\Api\V1\Auth\AuthenticateUserAction;
use App\Actions\Api\V1\Auth\VertifyOtpAction;
use App\Contracts\ApiResponseInterface;
use App\Enums\OtpType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\VerifyOtpRequest;
use App\Http\Resources\Api\V1\Auth\UserResource;
use App\Models\Admin;
use App\Models\User;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;

class AdminOtpAuthenticationController extends Controller
{
    public function __construct(
        protected VertifyOtpAction $vertifyOtpAction,
        protected AuthenticateUserAction $authenticateUser
    ) {
    }

    /**
     * Verify an OTP code and potentially log in/register
     *
     * User submits phone number (or email if already registered) and OTP code.
     * If valid for login/registration, authenticates the user (creating if necessary) and returns auth token.
     *
     * @param  VerifyOtpRequest  $request
     *
     * @return ApiResponseInterface
     * @throws \App\Exceptions\InvalidOtpCode
     * @group Admin Authentication
     * @response 200{
     *  "message": "User Logged in successfully",
     *  "data": {
     *  "token": "11|fudCok5UpqHuOiYiK8croExt91j2p667woCSNS5e7ba9305b",
     *  "expires_at": null,
     *  "type": "Bearer",
     *  "user": {
     *  "id": 1,
     *  "name": null,
     *  "phone": "09351234567",
     *  "email": "09351234567@example.com",
     *  "phone_verified_at": null,
     *  "email_verified_at": null
     *  }
     *  },
     *  "metadata": []
     *  }
     * @response 422 {
     *  "message": "Invalid OTP code",
     *  "errors": null,
     *  "metadata": []
     *  }
     */
    public function __invoke(VerifyOtpRequest $request): ApiResponseInterface
    {
        $type = filter_var($request->identifier, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';
        $user = Admin::when(
            $type === 'email',
            fn($q) => $q->where('email', $request->identifier),
            fn($q) => $q->where('phone', $request->identifier)
        )->firstOrFail();

        if (!$this->vertifyOtpAction->execute(
            $request->identifier,
            $request->tracking_code,
            $request->otp_code,
            OtpType::from($request->otp_type),
            guard: 'admin'
        )
        ) {
            return response()->validationError(message: "Invalid OTP code");
        }
        $token = $this->authenticateUser->execute($user, 'admin');

        return response()->success(
            [
                'token' => $token->plainTextToken,
                'expires_at' => $token->accessToken->expires_at,
                'type' => 'Bearer',
                'user' => UserResource::make($user)
            ], 'Authenticated successfully');
    }
}
