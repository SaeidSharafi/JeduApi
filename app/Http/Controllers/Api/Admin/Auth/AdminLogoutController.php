<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AdminLogoutController extends Controller
{
    /**
     * Logout the current Admin
     *
     *
     * @group Admin Authentication
     *
     * @authenticated
     *
     * @response 500{
     *  "message": "Unauthenticated."
     *  }
     * @response 204
     */
    public function __invoke(Request $request): JsonResponse
    {
        $request->user('admin')->currentAccessToken()->delete();

        return response()->noContentJson();
    }
}
