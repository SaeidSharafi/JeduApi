<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

final class ProfileCheckMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $user = auth('user')->user();
        if ($user && ! $user->profileCompleted()) {
            return response()->json([
                'message'    => __('shop.profile_incomplete_message'),
                'error_code' => 'PROFILE_INCOMPLETE',
            ], 403);
        }

        return $next($request);
    }
}
