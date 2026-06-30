<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Auth;

use App\Actions\Auth\ForgotPasswordAction;
use App\Contracts\ApiResponseInterface;
use App\Exceptions\UserDoesNotHavePasswordException;
use App\Exceptions\UserNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\InitiateAuthRequest;

/**
 * @group Admin - Staff Auth
 *
 * APIs for staff authentication
 *
 * @authenticated Staff
 */
final class StaffForgotPasswordController extends Controller
{
    public function __construct(
        protected ForgotPasswordAction $action
    ) {}

    /**
     * Forgot Password for staff
     *
     * Check if staff exists and send OTP to reset password
     *
     *
     * @throws UserNotFoundException
     *
     * @responseFile 200 resources/responses/admin/auth/staff.forgot_password.json
     * @responseFile 422 resources/responses/422.json
     * @responseFile 404 resources/responses/404.json
     */
    public function __invoke(InitiateAuthRequest $request): ApiResponseInterface
    {
        try {
            $otpSent = $this->action->execute(
                $request->identifier,
                'staff'
            );

            return apiResponse()->success([
                'tracking_code' => $otpSent->trackingCode,
                'otp_type'      => $otpSent->otpType->identifier(),
                'identifier'    => $request->identifier,
                'login_method'  => 'OTP',
            ], __('messages.auth.otp.sent'));

        } catch (UserDoesNotHavePasswordException $e) {
            return apiResponse()->validationError(
                message: __('messages.auth.doesnot_have_password')
            );
        } catch (UserNotFoundException $e) {
            return apiResponse()->notFound(
                message: __('messages.auth.login.not_found')
            );
        }
    }
}
