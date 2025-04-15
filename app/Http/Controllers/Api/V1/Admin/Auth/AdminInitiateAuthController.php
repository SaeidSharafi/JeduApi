<?php

namespace App\Http\Controllers\Api\V1\Admin\Auth;

use App\Actions\Api\V1\Auth\InitiateAuthAction;
use App\Contracts\ApiResponseInterface;
use App\Exceptions\UserHasPasswordException;
use App\Exceptions\UserNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\InitiateAuthRequest;

class AdminInitiateAuthController extends Controller
{
    public function __construct(
        protected InitiateAuthAction $action
    ) {}

    /**
     * Initiate authentication flow
     *
     * User provides phone or email. API determines the next step (e.g., prompt for password, request OTP, user not found)
     *
     * @group Admin Authentication
     * @response 201{
     *          "message": "OTP sent successfully",
     *          "data": {
     *                "tracking_code": "eca11365-c491-445c-b110-ee5a763e7c27",
     *                "otp_type": "SIGNUP",
     *                "identifier": "09351234567",
     *                "login_method": "OTP"
     *            },
     *        "metadata": []
     *   }
     * @response {
     *         "message": "OTP sent successfully",
     *         "data": {
     *               "tracking_code": "eca11365-c491-445c-b110-ee5a763e7c27",
     *               "otp_type": "SIGNIN",
     *               "identifier": "09351234567",
     *               "login_method": "OTP"
     *           },
     *       "metadata": []
     *  }
     * @response {
     *           "message": "User has set password",
     *           "data": {
     *               "login_method": "PASSWORD"
     *           },
     *           "metadata": []
     *  }
     */
    public function __invoke(InitiateAuthRequest $request): ApiResponseInterface
    {
        try {
            $otpSent = $this->action->execute(
                $request->identifier,
                'admin'
            );

            return response()->success([
                'tracking_code' => $otpSent->trackingCode,
                'otp_type' => $otpSent->otpType->value,
                'identifier' => $request->identifier,
                'login_method' => 'OTP',
            ], 'OTP sent successfully');

        } catch (UserHasPasswordException $e) {
            return response()->success([
                'login_method' => 'PASSWORD',
            ], 'User has set password');
        } catch (UserNotFoundException $exception) {
            return response()->notFound('User not found');
        }
    }
}
