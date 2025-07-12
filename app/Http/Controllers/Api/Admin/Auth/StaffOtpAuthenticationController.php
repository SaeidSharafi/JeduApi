<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Auth;

use App\Actions\Auth\AuthenticateUserAction;
use App\Actions\Auth\VerifyOtpAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Admin\Auth\StaffData;
use App\Enums\OtpType;
use App\Exceptions\InvalidOtpCode;
use App\Exceptions\UserNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\VerifyOtpRequest;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;

/**
 * @group Admin - Staff Auth
 *
 * APIs for staff authentication
 *
 * @authenticated Staff
 */
final class StaffOtpAuthenticationController extends Controller
{
    public function __construct(
        protected VerifyOtpAction $verifyOtpAction,
        protected AuthenticateUserAction $authenticateUser
    ) {}

    /**
     * Verify an OTP code and potentially log in staff
     *
     * User submits phone number (or email if already registered) and OTP code.
     * If valid for login/registration, authenticates the user (creating if necessary) and returns auth token.
     *
     *
     * @throws InvalidOtpCode
     *
     * @responseFile 200 responses/auth/staff.login.json
     *
     * @response 422 {
     *  "message": "Invalid OTP code",
     *  "errors": null,
     *  "metadata": []
     *  }
     */
    public function __invoke(VerifyOtpRequest $request): ApiResponseInterface
    {
        try {
            $user = $this->verifyOtpAction->execute(
                $request->identifier,
                $request->tracking_code,
                (int) $request->otp_code,
                OtpType::from($request->otp_type),
                guard: 'staff'
            );
            $token = $this->authenticateUser->execute($user);
        } catch (UserNotFoundException) {
            return response()->notFound(__('messages.auth.login.not_found'));
        } catch (InvalidOtpCode $e) {
            return response()->validationError(
                message: __('messages.auth.otp.invalid_code')
            );
        }
        $permissions = Cache::rememberForever(config('cache.keys.all_permissions'), function () {
            return Permission::query()->where('guard_name', 'staff')->get()->pluck('name')->toArray();
        });

        return response()->success(
            [
                'token'       => $token->plainTextToken,
                'expires_at'  => $token->accessToken->expires_at,
                'type'        => 'Bearer',
                'user'        => StaffData::from($user),
                'permissions' => $permissions,
            ], 'Authenticated successfully');
    }
}
