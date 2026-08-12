<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Shop - Auth
 *
 * @authenticated
 */
final class LogoutController extends Controller
{
    /**
     * Logout the current User
     *
     * @response 500{
     * "message": "Unauthenticated."
     * }
     * @response 204
     */
    public function __invoke(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        cookie()->queue(cookie()->forget('user_token'));

        return apiResponse()->noContentJson();
    }
}
