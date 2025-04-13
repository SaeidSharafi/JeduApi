<?php

namespace App\Http\Controllers\Api\V1\Admin\Auth;

use App\Actions\Api\V1\Auth\ResetPasswordAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\ResetPasswordOtpRequest;
use Illuminate\Http\JsonResponse;

class AdminResetPasswordController extends Controller
{
    public function __construct(
        protected ResetPasswordAction $action
    ) {
    }

    public function __invoke(ResetPasswordOtpRequest $request): JsonResponse
    {
        $this->action->execute(
            $request->identifier,
            $request->type,
            $request->otp,
            $request->password,
            'admin'
        );

        return response()->json(['message' => 'Password has been reset successfully.']);
    }
}
