<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Api\V1\Auth\VerifyOtpForResetAction;
use App\Actions\Api\V1\Auth\AuthenticateUserAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\VerifyOtpRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class OtpVerificationController extends Controller
{
    public function __construct(
        protected VerifyOtpForResetAction $verifyOtpForReset,
        protected AuthenticateUserAction $authenticateUser
    ) {
    }

    public function __invoke(VerifyOtpRequest $request): JsonResponse
    {
        $user = User::when(
            $request->type === 'email',
            fn ($q) => $q->where('email', $request->identifier),
            fn ($q) => $q->where('phone', $request->identifier)
        )->firstOrFail();

        if ($request->purpose === 'PASSWORD_RESET') {
            $result = $this->verifyOtpForReset->execute(
                $request->identifier,
                $request->type,
                $request->otp
            );

            return response()->json($result);
        }

        return response()->json(
            $this->authenticateUser->execute($user)
        );
    }
}
