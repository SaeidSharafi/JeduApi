<?php

namespace App\Http\Controllers\Api\V1\Admin\Auth;

use App\Actions\Auth\VerifyOtpForResetAction;
use App\Actions\Auth\AuthenticateUserAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\VerifyOtpRequest;
use App\Models\Admin;
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
        $admin = Admin::when(
            $request->type === 'email',
            fn ($q) => $q->where('email', $request->identifier),
            fn ($q) => $q->where('phone', $request->identifier)
        )->firstOrFail();

        if ($request->purpose === 'PASSWORD_RESET') {
            $result = $this->verifyOtpForReset->execute(
                $request->identifier,
                $request->type,
                $request->otp,
                'admin'
            );

            return response()->json($result);
        }

        return response()->json(
            $this->authenticateUser->execute($admin, 'admin')
        );
    }
}
