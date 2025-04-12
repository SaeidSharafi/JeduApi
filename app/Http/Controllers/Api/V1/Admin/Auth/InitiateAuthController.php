<?php

namespace App\Http\Controllers\Api\V1\Admin\Auth;

use App\Actions\Auth\InitiateAuthAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\InitiateAuthRequest;
use Illuminate\Http\JsonResponse;

class InitiateAuthController extends Controller
{
    public function __construct(
        protected InitiateAuthAction $action
    ) {
    }

    public function __invoke(InitiateAuthRequest $request): JsonResponse
    {
        $result = $this->action->execute(
            $request->identifier,
            $request->type,
            'admin'
        );

        return response()->json($result);
    }
}
