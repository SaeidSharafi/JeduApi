<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class HorizonBasicAuth
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $expectedUsername = config('horizon.auth.username');
        $expectedPassword = config('horizon.auth.password');

        if (
            ! is_string($expectedUsername) || $expectedUsername === ''
            || ! is_string($expectedPassword) || $expectedPassword === ''
        ) {
            return $this->unauthorized();
        }

        $providedUsername = $request->getUser();
        $providedPassword = $request->getPassword();

        if (
            is_string($providedUsername) && is_string($providedPassword)
            && hash_equals($expectedUsername, $providedUsername)
            && hash_equals($expectedPassword, $providedPassword)
        ) {
            return $next($request);
        }

        return $this->unauthorized();
    }

    private function unauthorized(): Response
    {
        return response('Unauthorized.', Response::HTTP_UNAUTHORIZED, [
            'WWW-Authenticate' => 'Basic realm="Horizon"',
        ]);
    }
}
