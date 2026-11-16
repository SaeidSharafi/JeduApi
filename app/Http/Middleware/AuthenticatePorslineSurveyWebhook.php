<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AuthenticatePorslineSurveyWebhook
{
    /** @param Closure(Request): (Response) $next */
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('services.porsline.webhook_secret');
        $provided = $request->header('X-Survey-Webhook-Secret');

        if (! is_string($expected) || $expected    === ''
                                   || ! is_string($provided) || $provided === ''
                                   || ! hash_equals($expected, $provided)
        ) {
            return apiResponse()->unauthorized()->toResponse($request);
        }

        return $next($request);
    }
}
