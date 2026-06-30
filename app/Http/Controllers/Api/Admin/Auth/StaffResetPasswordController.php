<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Auth;

use App\Actions\Auth\ResetPasswordAction;
use App\Contracts\ApiResponseInterface;
use App\Exceptions\InvalidOtpCodeException;
use App\Exceptions\UserDoesNotHavePasswordException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordOtpRequest;

/**
 * @group Admin - Staff Auth
 *
 * APIs for staff authentication
 *
 * @authenticated Staff
 */
final class StaffResetPasswordController extends Controller
{
    public function __construct(
        protected ResetPasswordAction $action
    ) {}

    /**
     * Reset password using OTP-derived reset token for staff
     *
     * Requires the phone number and the reset OTP token obtained from a successful OTP verification
     *
     *
     * @throws \App\Exceptions\UserNotFoundException
     *
     * @responseFile 200 resources/responses/admin/auth/staff.reset_password.json
     * @responseFile 422 resources/responses/422.json
     * @responseFile 404 resources/responses/404.json
     */
    public function __invoke(ResetPasswordOtpRequest $request): ApiResponseInterface
    {
        try {
            $this->action->execute(
                $request->identifier,
                $request->tracking_code,
                $request->otp_code,
                $request->password,
                'staff'
            );

            return response()->success('Password reset OTP sent successfully');
        } catch (UserDoesNotHavePasswordException $exception) {
            return response()->validationError(
                message: __('messages.auth.doesnot_have_password')
            );
        } catch (InvalidOtpCodeException $exception) {
            return response()->validationError(
                message: __('messages.auth.otp.invalid_code')
            );
        }
    }
}
