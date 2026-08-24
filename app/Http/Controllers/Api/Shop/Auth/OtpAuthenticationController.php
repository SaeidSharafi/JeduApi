<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\Auth;

use App\Actions\Auth\AuthenticateUserAction;
use App\Actions\Auth\VerifyOtpAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Shop\Customer\CustomerData;
use App\Enums\System\OtpType;
use App\Exceptions\InvalidOtpCodeException;
use App\Exceptions\UserBannedException;
use App\Exceptions\UserNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\VerifyOtpRequest;

/**
 * @group Shop - Auth
 */
final class OtpAuthenticationController extends Controller
{
    public function __construct(
        protected VerifyOtpAction $verifyOtpAction,
        protected AuthenticateUserAction $authenticateUser,
    ) {}

    /**
     * Verify an OTP code and potentially log in/register
     *
     * User submits phone number (or email if already registered) and OTP code.
     * If valid for login/registration, authenticates the user (creating if necessary) and returns auth token.
     *
     *
     * @throws InvalidOtpCodeException
     *
     * @responseFile 200 resources/responses/shop/auth/login.json
     * @responseFile 404 resources/responses/404.json
     * @responseFile 422 resources/responses/422.json
     */
    public function __invoke(VerifyOtpRequest $request): ApiResponseInterface
    {

        try {
            $user = $this->verifyOtpAction->execute(
                $request->identifier,
                $request->tracking_code,
                (int) $request->otp_code,
                OtpType::from($request->otp_type));
            $token = $this->authenticateUser->execute($user);
        } catch (UserNotFoundException) {
            return apiResponse()->notFound(__('messages.auth.login.not_found'));
        } catch (InvalidOtpCodeException $e) {
            return apiResponse()->validationError(
                message: __('messages.auth.otp.invalid_code')
            );
        } catch (UserBannedException) {
            return apiResponse()->forbidden(
                message: __('messages.auth.login.banned')
            );
        }

        cookie()->queue(
            'user_token',
            $token->plainTextToken,
            (int) config('sanctum.expiration'),
            '/',
            null,
            app()->isProduction() || (bool) config('session.secure'),
            true,
            false,
            'Lax'
        );

        return apiResponse()->success(
            [
                'token'      => $token->plainTextToken,
                'expires_at' => $token->accessToken->expires_at,
                'type'       => 'Bearer',
                'user'       => CustomerData::fromUser($user),
            ]
        );
    }
}
