<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Contracts\ApiResponseInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LogoutController extends Controller
{
    /**
     * Logout the current Admin
     *
     *
     * @group User Authentication
     *
     * @authenticated
     *
     * @response 500{
     * "message": "Unauthenticated."
     * }
     * @response 204
     */
    public function __invoke(Request $request): JsonResponse
    {
        if (!$request->user('user')->currentAccessToken()){
            return new JsonResponse([
                'message' => 'Unauthenticated.'
            ], 401);
        }
        $request->user()->currentAccessToken()->delete();

        return response()->noContentJson();
    }
}
