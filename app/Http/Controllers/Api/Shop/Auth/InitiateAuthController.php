<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\Auth;

use App\Actions\Auth\InitiateAuthAction;
use App\Contracts\ApiResponseInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\InitiateAuthRequest;

/**
 * @group Shop - Auth
 */
final class InitiateAuthController extends Controller
{
    public function __construct(
        protected InitiateAuthAction $action
    ) {}

    /**
     * Initiate authentication flow
     *
     * User provides phone or email. API determines the next step (e.g., prompt for password, request OTP, user not
     * found)
     *
     *
     * @responseFile 200 resources/responses/shop/auth/initiate.json
     * @responseFile 200 resources/responses/shop/auth/initiate-password.json
     * @responseFile 404 resources/responses/404.json
     */
    public function __invoke(InitiateAuthRequest $request): ApiResponseInterface
    {
        $result = $this->action->execute(
            $request->identifier,
            'user',
            $request->ip(),
            $request->userAgent(),
        );

        if ($result->requiresPassword) {
            return apiResponse()->success([
                'login_method' => 'PASSWORD',
            ], __('messages.auth.user_has_password'));
        }

        $otpSent = $result->otpSent;

        return apiResponse()->success([
            'tracking_code' => $otpSent->trackingCode,
            'otp_type'      => $otpSent->otpType->identifier(),
            'identifier'    => $request->identifier,
            'waiting_time'  => $otpSent->waitingTime,
            'login_method'  => 'OTP',
        ], __('messages.auth.otp.sent'));
    }
}
