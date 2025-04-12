<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\GenerateOtpAction;
use App\Actions\VerifyOtpAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\InitiateAuthRequest;
use App\Http\Requests\Api\V1\RequestOtpRequest;
use App\Http\Requests\Api\V1\ResetPasswordOtpRequest;
use App\Http\Requests\Api\V1\VerifyOtpRequest;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Models\Admin;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        protected GenerateOtpAction $generateOtp,
        protected VerifyOtpAction $verifyOtp
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
            throw ValidationException::withMessages([
                'identifier' => ['Admin account not found.'],
            ]);
        }

        if ($admin->hasSetPassword()) {
            return response()->json([
                'action' => 'PASSWORD_LOGIN',
                'message' => 'Please login with password.'
            ]);
        }

        return response()->json([
            'action' => 'OTP_LOGIN',
            'message' => 'Please request OTP to login.'
        ]);
    }

    public function requestOtp(RequestOtpRequest $request): JsonResponse
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
            $resetToken = Str::random(60);
            $admin->update(['reset_token' => Hash::make($resetToken)]);

            return response()->json([
                'reset_token' => $resetToken,
                'message' => 'OTP verified successfully. Use this token to reset password.'
            ]);
        }

        return $this->authenticateAdmin($admin);
    }

    public function resetPassword(ResetPasswordOtpRequest $request): JsonResponse
    {
        $admin = Admin::when(
            $request->type === 'email',
            fn ($q) => $q->where('email', $request->identifier),
            fn ($q) => $q->where('phone', $request->identifier)
        )->firstOrFail();

        if (!$admin->reset_token || !Hash::check($request->reset_token, $admin->reset_token)) {
            throw ValidationException::withMessages([
                'reset_token' => ['Invalid or expired reset token.'],
            ]);
        }

        $admin->update([
            'password' => Hash::make($request->password),
            'reset_token' => null
        ]);

        return response()->json(['message' => 'Password has been reset successfully.']);
    }

    public function loginWithPassword(LoginRequest $request): JsonResponse
    {
        $admin = Admin::when(
            $request->type === 'email',
            fn ($q) => $q->where('email', $request->identifier),
            fn ($q) => $q->where('phone', $request->identifier)
        )->firstOrFail();

        if (!$admin->hasSetPassword() || !Hash::check($request->password, $admin->password)) {
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
