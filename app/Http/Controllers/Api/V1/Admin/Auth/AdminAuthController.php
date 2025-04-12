<?php

namespace App\Http\Controllers\Api\V1\Admin\Auth;

use App\Actions\Api\V1\Auth\GenerateOtpAction;
use App\Actions\Api\V1\Auth\ResetPasswordAction;
use App\Actions\Api\V1\Auth\VerifyOtpAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\InitiateAuthRequest;
use App\Http\Requests\Api\V1\Admin\LoginRequest;
use App\Http\Requests\Api\V1\Auth\OtpRequest;
use App\Http\Requests\Api\V1\Auth\ResetPasswordOtpRequest;
use App\Http\Requests\Api\V1\Auth\VerifyOtpRequest;
use App\Models\Admin;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class AdminAuthController extends Controller
{
    public function __construct(
        protected GenerateOtpAction $generateOtp,
        protected VerifyOtpAction $verifyOtp,
        protected ResetPasswordAction $resetPassword
    ) {
    }

    public function initiate(InitiateAuthRequest $request): JsonResponse
    {
        $admin = Admin::when(
            $request->type === 'email',
            fn ($q) => $q->where('email', $request->identifier),
            fn ($q) => $q->where('phone', $request->identifier)
        )->first();

        if (!$admin) {
            return response()->json([
                'action' => 'NOT_FOUND',
                'message' => 'Admin account not found.'
            ], 404);
        }

        return response()->json([
            'action' => $admin->hasSetPassword() ? 'PASSWORD_LOGIN' : 'OTP_LOGIN',
            'message' => $admin->hasSetPassword()
                ? 'Please login with password.'
                : 'Please request OTP to login.'
        ]);
    }

    public function requestOtp(OtpRequest $request): JsonResponse
    {
        $admin = Admin::when(
            $request->type === 'email',
            fn ($q) => $q->where('email', $request->identifier),
            fn ($q) => $q->where('phone', $request->identifier)
        )->firstOrFail();

        $this->generateOtp->execute($admin);

        return response()->json(['message' => 'OTP has been sent.']);
    }

    public function verifyOtp(VerifyOtpRequest $request): JsonResponse
    {
        $admin = Admin::when(
            $request->type === 'email',
            fn ($q) => $q->where('email', $request->identifier),
            fn ($q) => $q->where('phone', $request->identifier)
        )->firstOrFail();

        $this->verifyOtp->execute($admin, $request->otp);

        if ($request->purpose === 'PASSWORD_RESET') {
            $token = Password::broker('admins')->createToken($admin);

            return response()->json([
                'reset_token' => $token,
                'message' => 'OTP verified successfully. Use this token to reset password.'
            ]);
        }

        return $this->authenticateAdmin($admin);
    }

    public function resetPassword(ResetPasswordOtpRequest $request): JsonResponse
    {
        $this->resetPassword->execute(
            $request->identifier,
            $request->type,
            $request->token,
            $request->password,
            'admin'
        );

        return response()->json(['message' => 'Password has been reset successfully.']);
    }

    public function loginWithPassword(LoginRequest $request): JsonResponse
    {
        $admin = Admin::when(
            $request->type === 'email',
            fn ($q) => $q->where('email', $request->identifier),
            fn ($q) => $q->where('phone', $request->identifier)
        )->firstOrFail();

        if (!Hash::check($request->password, $admin->password)) {
            throw ValidationException::withMessages([
                'password' => ['The provided credentials are incorrect.'],
            ]);
        }

        return $this->authenticateAdmin($admin);
    }

    public function logout(): JsonResponse
    {
        auth('admin')->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Successfully logged out']);
    }

    protected function authenticateAdmin(Admin $admin): JsonResponse
    {
        $token = $admin->createToken('admin_token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'admin' => $admin
        ]);
    }
}
