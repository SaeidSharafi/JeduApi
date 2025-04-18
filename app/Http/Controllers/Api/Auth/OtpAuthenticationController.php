<?php

namespace App\Http\Controllers\Api\Auth;

use App\Actions\Auth\AuthenticateUserAction;
use App\Actions\Auth\VertifyOtpAction;
use App\Contracts\ApiResponseInterface;
use App\Enums\OtpType;
use App\Exceptions\InvalidOtpCode;
use App\Exceptions\UserNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Http\Resources\Auth\UserResource;
use App\Models\User;

class OtpAuthenticationController extends Controller
{
    public function __construct(
        protected VertifyOtpAction $vertifyOtpAction,
        protected AuthenticateUserAction $authenticateUser,
    ) {
    }

    /**
     * Verify an OTP code and potentially log in/register
     *
     * User submits phone number (or email if already registered) and OTP code.
     * If valid for login/registration, authenticates the user (creating if necessary) and returns auth token.
     *
     *
     * @throws \App\Exceptions\InvalidOtpCode
     *
     * @group User Authentication
     *
     * @response 200{
     * "message": "User Logged in successfully",
     * "data": {
     * "token": "11|fudCok5UpqHuOiYiK8croExt91j2p667woCSNS5e7ba9305b",
     * "expires_at": null,
     * "type": "Bearer",
     * "user": {
     * "id": 1,
     * "name": null,
     * "phone": "09351234567",
     * "email": "09351234567@example.com",
     * "phone_verified_at": null,
     * "email_verified_at": null
     * }
     * },
     * "metadata": []
     * }
     * @response 422 {
     * "message": "Invalid OTP code",
     * "errors": null,
     * "metadata": []
     * }
     *
     */
    public function __invoke(VerifyOtpRequest $request): ApiResponseInterface
    {
        try {
            $user = $this->vertifyOtpAction->execute(
                $request->identifier,
                $request->tracking_code,
                $request->otp_code,
                OtpType::from($request->otp_type));
            $token = $this->authenticateUser->execute($user);
        } catch (UserNotFoundException) {
            return response()->notFound('User not found');
        } catch (InvalidOtpCode $e) {
            return response()->validationError(
                message: 'Invalid OTP code'
            );
        }

        return response()->success(
            [
                'token'      => $token->plainTextToken,
                'expires_at' => $token->accessToken->expires_at,
                'type'       => 'Bearer',
                'user'       => UserResource::make($user),
            ]
        );
    }
}
