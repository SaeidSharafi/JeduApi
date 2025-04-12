<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Api\V1\Auth\RequestOtpAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\OtpRequest;
use Illuminate\Http\JsonResponse;

class RequestOtpController extends Controller
{
    public function __construct(
        protected RequestOtpAction $action
    ) {
    }

    public function __invoke(OtpRequest $request): JsonResponse
    {
        $result = $this->action->execute(
            $request->identifier,
            $request->type,
            $request->purpose
        );

        return response()->json($result);
    }
}
