<?php

namespace App\Http\Controllers\Api\Auth;

use App\Actions\Auth\ForgotPasswordAction;
use App\Contracts\ApiResponseInterface;
use App\Exceptions\UserDoesNotHavePasswordException;
use App\Exceptions\UserNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\InitiateAuthRequest;

class ForgotPasswordController extends Controller
{
    public function __construct(
        protected ForgotPasswordAction $action
    ) {
    }

    /**
     * Forgot Password
     *
     * Check if admin exists and send OTP to reset password
     *
     *
     * @throws \App\Exceptions\UserNotFoundException
     *
     * @group User Authentication
     *
     * @response {
     *    "tracking_code": "string",
     *    "otp_type": "string",
     *    "identifier": "string"
     * }
     * @response status=401 { "message" : 'User does not have password'}
     * @response status=404 { "message" : 'User Not found'}
     */
    public function __invoke(InitiateAuthRequest $request): ApiResponseInterface
    {
        try {
            $otpSent = $this->action->execute(
                $request->identifier,
            );

            return response()->success([
                'tracking_code' => $otpSent->trackingCode,
                'otp_type'      => $otpSent->otpType->value,
                'identifier'    => $request->identifier,
            ], 'OTP sent successfully');

        } catch (UserDoesNotHavePasswordException $e) {
            return response()->validationError(
                message: 'User does not have password'
            );
        } catch (UserNotFoundException $e) {
            return response()->notFound(
                message: 'User not found'
            );
        }
    }
}
