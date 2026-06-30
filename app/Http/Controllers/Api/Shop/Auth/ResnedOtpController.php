<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\Auth;

use App\Actions\Auth\RequestOtpAction;
use App\Contracts\ApiResponseInterface;
use App\Enums\System\OtpType;
use App\Exceptions\UserNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\OtpRequest;

/**
 * @group Shop - Auth
 */
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
     * @responseFile 200 resources/responses/shop/auth/resend-otp.json
     * @responseFile 422 resources/responses/422.json
     * @responseFile 404 resources/responses/404.json
     */
    public function __invoke(OtpRequest $request): ApiResponseInterface
    {
        try {
            $result = $this->action->execute(
                $request->identifier,
                OtpType::tryFrom($request->otp_type)
            );

            return apiResponse()->success([
                'tracking_code' => $result->trackingCode,
                'otp_type'      => $result->otpType->identifier(),
                'identifier'    => $request->identifier,
                'waiting_time'  => $result->waitingTime,
                'login_method'  => 'OTP',
            ], 'OTP resent successfully');

        } catch (UserNotFoundException $exception) {
            return apiResponse()->notFound(__('messages.auth.login.not_found'));
        }

    }
}
