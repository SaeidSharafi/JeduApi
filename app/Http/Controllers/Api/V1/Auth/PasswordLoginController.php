<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\PasswordLoginAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use Illuminate\Http\JsonResponse;

class PasswordLoginController extends Controller
{
    public function __construct(
        protected PasswordLoginAction $action
    ) {}

    public function __invoke(LoginRequest $request): JsonResponse
    {
        $result = $this->action->execute(
            $request->identifier,
            $request->type,
            $request->password
        );

        return response()->json($result);
    }
}
 }
}
