<?php

namespace App\Http\Controllers\Api\V1\Admin\Auth;

use App\Actions\Api\V1\Auth\RequestOtpAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\RequestOtpRequest;
use Illuminate\Http\JsonResponse;

class AdminRequestOtpController extends Controller
{
    public function __construct(
        protected RequestOtpAction $action
    ) {
    }

    public function __invoke(RequestOtpRequest $request): JsonResponse
    {
        $result = $this->action->execute(
            $request->identifier,
            $request->type,
            $request->purpose,
            'admin'
        );

        if ($result['status'] === 'error') {
            return response()->json($result, 404);
        }

        return response()->json($result);
    }
}
