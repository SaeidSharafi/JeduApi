<?php

namespace App\Http\Controllers\Api\V1\Admin\Auth;

use App\Contracts\ApiResponseInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AdminLogoutController extends Controller
{
    /**
     * Logout the current Admin
     *
     *
     * @group Admin Authentication
     *
     * @authenticated
     * @response 500{
     *  "message": "Unauthenticated."
     *  }
     * @response 204
     */
    public function __invoke(Request $request): ApiResponseInterface
    {
        $request->user('admin')->currentAccessToken()->delete();

        return response()->noContentJson();
    }
}
