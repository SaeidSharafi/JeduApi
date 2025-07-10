<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Actions\Auth\RequestOtpAction;
use App\Contracts\ApiResponseInterface;
use App\Enums\OtpType;
use App\Exceptions\UserNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\OtpRequest;

final class ResnedOtpController extends Controller
{
    public function __construct(
        protected RequestOtpAction $action
    ) {}

    /**
     * Resned an OTP code via SMS or Email
     *
     * Re-Sends an OTP to the user's phone number (or Email) for a specific
     * purpose (login/registration or password reset).
     *
     *
     * @group User Authentication
     *
     * @response {
     * "message": "OTP resent successfully",
     * "data": {
     * "tracking_code": "6e175b9e-c175-4001-a3a8-9d6c51afbf16",
     * "otp_type": "SIGNIN",
     * "identifier": "09351234567",
     * "login_method": "OTP"
     * },
     * "metadata": []
     * }
     *@response 422{
     * "message": "'messages.auth.otp.otp.throttle",
     * "errors": {
     * "otp": [
     * "'messages.auth.otp.otp.throttle"
     * ]
     * },
     * "metadata": []
     * }
     * @response 422{
     * "message": "User not found",
     * "errors": null,
     * "metadata": []
     * }
     */
    public function __invoke(OtpRequest $request): ApiResponseInterface
    {
        try {
            $result = $this->action->execute(
                $request->identifier,
                OtpType::tryFrom($request->otp_type)
            );

            return response()->success([
                'tracking_code' => $result->trackingCode,
                'otp_type'      => $result->otpType->identifier(),
                'identifier'    => $request->identifier,
                'waiting_time'  => $result->waitingTime,
                'login_method'  => 'OTP',
            ], 'OTP resent successfully');

        } catch (UserNotFoundException $exception) {
            return response()->notFound(__('messages.auth.login.not_found'));
        }

    }
}
