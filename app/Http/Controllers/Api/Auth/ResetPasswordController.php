<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Actions\Auth\ResetPasswordAction;
use App\Contracts\ApiResponseInterface;
use App\Exceptions\InvalidOtpCode;
use App\Exceptions\UserDoesNotHavePasswordException;
use App\Exceptions\UserNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordOtpRequest;

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
     * @group User Authentication
     *
     * @response  {
     * "message": "Operation successful.",
     * "data": "Password reset OTP sent successfully",
     * "metadata": []
     * }
     * @response 422{
     *     {
     * "message": "Invalid OTP code",
     * "errors": null,
     * "metadata": []
     * }
     * }
     * @response 422{
     *     {
     * "message": "User does not have password",
     * "errors": null,
     * "metadata": []
     * }
     * }
     * @response 404{
     * "message": "User not found",
     * "errors": null,
     * "metadata": []
     * }
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

            return response()->success(message: 'Password reset successfully');
        } catch (UserDoesNotHavePasswordException $exception) {
            return response()->validationError(
                message: 'User does not have password'
            );
        } catch (InvalidOtpCode $exception) {
            return response()->validationError(
                message: 'Invalid OTP code'
            );
        } catch (UserNotFoundException $exception) {
            return response()->notFound(
                message: 'User not found'
            );
        }
    }
}
