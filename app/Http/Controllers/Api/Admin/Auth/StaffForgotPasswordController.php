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
     * Forgot Password
     *
     * Check if staff exists and send OTP to reset password
     *
     *
     * @throws UserNotFoundException
     *
     * @response {
     *     "tracking_code": "string",
     *     "otp_type": "string",
     *     "identifier": "string"
     *  }
     *  @response status=401 { "message" : 'User does not have password'}
     *  @response status=404 { "message" : 'User Not found'}
     */
    public function __invoke(InitiateAuthRequest $request): ApiResponseInterface
    {
        try {
            $otpSent = $this->action->execute(
                $request->identifier,
                'staff'
            );

            return response()->success([
                'tracking_code' => $otpSent->trackingCode,
                'otp_type'      => $otpSent->otpType->identifier(),
                'identifier'    => $request->identifier,
                'login_method'  => 'OTP',
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
