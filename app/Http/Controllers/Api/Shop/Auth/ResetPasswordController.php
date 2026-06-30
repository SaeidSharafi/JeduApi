<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\Auth;

use App\Actions\Auth\ResetPasswordAction;
use App\Contracts\ApiResponseInterface;
use App\Exceptions\InvalidOtpCodeException;
use App\Exceptions\UserDoesNotHavePasswordException;
use App\Exceptions\UserNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordOtpRequest;

/**
 * @group Shop - Auth
 */
final class ResetPasswordController extends Controller
{
    public function __construct(
        protected ResetPasswordAction $action
    ) {}

    /**
     * Reset password using OTP-derived reset token
     *
     * Requires the phone number and the reset OTP token obtained from a successful OTP verification
     *
     *
     * @throws UserNotFoundException
     *
     * @responseFile 200 resources/responses/shop/auth/reset-password.json
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
            );

            return response()->success(message: __('messages.auth.password_reset'));
        } catch (UserDoesNotHavePasswordException $exception) {
            return response()->validationError(
                message: __('messages.auth.doesnot_have_password')
            );
        } catch (InvalidOtpCodeException $exception) {
            return response()->validationError(
                message: __('messages.auth.otp.invalid_code')
            );
        } catch (UserNotFoundException $exception) {
            return response()->notFound(
                message: __('messages.auth.login.not_found')
            );
        }
    }
}
