<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Actions\Auth\AuthenticateUserAction;
use App\Actions\Auth\VerifyOtpAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Shop\Customer\CustomerData;
use App\Enums\OtpType;
use App\Exceptions\InvalidOtpCode;
use App\Exceptions\UserNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Models\User;

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
     * @throws InvalidOtpCode
     *
     * @group User Authentication
     *
     * @response {
     *      "message": "User Logged in successfully",
     *      "data": {
     *          "token": "8|juaIqinWuRHiE2vnr3TGr7Pjuy04oHFFilPXxd2Y26f5f131",
     *          "expires_at": null,
     *          "type": "Bearer",
     *          "user": {
     *              "uuid": "0197f38e-84a3-70d3-ae33-73b777915eb2",
     *              "phone": "09151235664",
     *              "is_profile_completed": true,
     *              "first_name": "Juvenal",
     *              "last_name": "Murray",
     *              "email": "vschiller@example.com",
     *              "phone2": "09371134162",
     *              "civil_id": "93530102067499",
     *              "civil_id_type": {
     *                  "value": "immigrant_code",
     *                  "label": "کد اتباع"
     *              },
     *              "date_of_birth": "1353-01-16",
     *              "father_name": "Prof. Solon Gutkowski",
     *              "gender": {
     *                  "value": "female",
     *                  "label": "زن"
     *              },
     *              "education_level": {
     *                      "value": "under_diploma",
     *                      "label": "زیردیپلم"
     *                  },
     *              "field_of_study": "هنر",
     *              "education_status": {
     *                  "value": "student",
     *                  "label": "دانشجو"
     *              }
     *          }
     *      },
     *      "metadata": []
     *    }
     * @response 422 {
     *      "message": "Invalid OTP code",
     *      "errors": null,
     *      "metadata": []
     * }
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
            return response()->notFound(__('messages.auth.login.not_found'));
        } catch (InvalidOtpCode $e) {
            return response()->validationError(
                message: __('messages.auth.otp.invalid_code')
            );
        }

        return response()->success(
            [
                'token'      => $token->plainTextToken,
                'expires_at' => $token->accessToken->expires_at,
                'type'       => 'Bearer',
                'user'       => CustomerData::from($user),
            ]
        );
    }
}
