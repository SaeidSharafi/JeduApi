<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Auth\GenerateOtpAction;
use App\Actions\Auth\ResetPasswordAction;
use App\Actions\Auth\VerifyOtpAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\InitiateAuthRequest;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Requests\Api\V1\Auth\OtpRequest;
use App\Http\Requests\Api\V1\Auth\ResetPasswordOtpRequest;
use App\Http\Requests\Api\V1\Auth\VerifyOtpRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        protected GenerateOtpAction $generateOtp,
        protected VerifyOtpAction $verifyOtp,
        protected ResetPasswordAction $resetPassword
    ) {
    }

    public function initiate(InitiateAuthRequest $request): JsonResponse
    {
        $user = User::when(
            $request->type === 'email',
            fn ($q) => $q->where('email', $request->identifier),
            fn ($q) => $q->where('phone', $request->identifier)
        )->first();

        if (!$user) {
            return response()->json([
                'action' => 'REGISTER',
                'message' => 'User not found. Registration required.'
            ]);
        }

        return response()->json([
            'action' => $user->hasSetPassword() ? 'PASSWORD_LOGIN' : 'OTP_LOGIN',
            'message' => $user->hasSetPassword()
                ? 'Please login with password.'
                : 'Please request OTP to login.'
        ]);
    }

    public function requestOtp(OtpRequest $request): JsonResponse
    {
        $user = User::when(
            $request->type === 'email',
            fn ($q) => $q->where('email', $request->identifier),
            fn ($q) => $q->where('phone', $request->identifier)
        )->firstOrFail();

        $this->generateOtp->execute($user);

        return response()->json(['message' => 'OTP has been sent.']);
    }

    public function verifyOtp(VerifyOtpRequest $request): JsonResponse
    {
        $user = User::when(
            $request->type === 'email',
            fn ($q) => $q->where('email', $request->identifier),
            fn ($q) => $q->where('phone', $request->identifier)
        )->firstOrFail();

        $this->verifyOtp->execute($user, $request->otp);

        if ($request->purpose === 'PASSWORD_RESET') {
            $token = Password::createToken($user);

            return response()->json([
                'reset_token' => $token,
                'message' => 'OTP verified successfully. Use this token to reset password.'
            ]);
        }

        return $this->authenticateUser($user);
    }

    public function resetPassword(ResetPasswordOtpRequest $request): JsonResponse
    {
        $this->resetPassword->execute(
            $request->identifier,
            $request->type,
            $request->token,
            $request->password
        );

        return response()->json(['message' => 'Password has been reset successfully.']);
    }

    public function loginWithPassword(LoginRequest $request): JsonResponse
    {
        $user = User::when(
            $request->type === 'email',
            fn ($q) => $q->where('email', $request->identifier),
            fn ($q) => $q->where('phone', $request->identifier)
        )->firstOrFail();

        if (!Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['The provided credentials are incorrect.'],
            ]);
        }

        return $this->authenticateUser($user);
    }

    public function logout(): JsonResponse
    {
        auth()->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Successfully logged out']);
    }

    protected function authenticateUser(User $user): JsonResponse
    {
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user
        ]);
    }
}
