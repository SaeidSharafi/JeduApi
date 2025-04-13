<?php

namespace App\Http\Controllers\Api\V1\Admin\Auth;

use App\Actions\Api\V1\Auth\PasswordLoginAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use Illuminate\Http\JsonResponse;

class AdminPasswordLoginController extends Controller
{
    public function __construct(
        protected PasswordLoginAction $action
    ) {
    }

    public function __invoke(LoginRequest $request): JsonResponse
    {
        $result = $this->action->execute(
            $request->identifier,
            $request->type,
            $request->password,
            'admin'
        );

        return response()->json($result);
    }
}
