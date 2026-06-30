<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Admin - Staff Auth
 *
 * APIs for staff authentication
 *
 * @authenticated Staff
 */
final class StaffLogoutController extends Controller
{
    /**
     * Logout the current Staff
     *
     *
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
        $request->user('staff')->currentAccessToken()->delete();

        return apiResponse()->noContentJson();
    }
}
