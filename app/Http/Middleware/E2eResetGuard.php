<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Testing\E2eResetState;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class E2eResetGuard
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('api/v1/e2e/reset') || ! app()->environment('e2e')) {
            return $next($request);
        }

        if (app(E2eResetState::class)->isResetting()) {
            return apiResponse()
                ->error('E2E reset is in progress.', Response::HTTP_SERVICE_UNAVAILABLE)
                ->toResponse($request);
        }

        return $next($request);
    }
}
