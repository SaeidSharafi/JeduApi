<?php

namespace App\Http\Controllers\Api\V1\Admin\Auth;

use App\Actions\Api\V1\Auth\InitiateAuthAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\InitiateAuthRequest;
use Illuminate\Http\JsonResponse;

class AdminInitiateAuthController extends Controller
{
    public function __construct(
        protected InitiateAuthAction $action
    ) {
    }

    public function __invoke(InitiateAuthRequest $request): JsonResponse
    {
        $result = $this->action->execute(
            $request->identifier,
            'admin'
        );

        return response()->json($result);
    }
}
