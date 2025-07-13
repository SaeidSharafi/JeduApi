<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class LogoutController extends Controller
{
    /**
     * Logout the current Staff
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
        $request->user()->currentAccessToken()->delete();

        return response()->noContentJson();
    }
}
