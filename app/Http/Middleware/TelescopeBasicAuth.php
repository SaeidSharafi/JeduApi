<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class TelescopeBasicAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! app()->environment('dev')) {
            return $next($request);
        }

        $expectedUser = config('app.telescope_dev_user');
        $expectedPass = config('app.telescope_dev_password');

        // Check if .env variables are set. If not, deny access to prevent accidental exposure.
        if (empty($expectedUser) || empty($expectedPass)) {
            // Log an error for the admin to see
            logger()->error('Telescope dev credentials are not set in .env file.');

            return response('Unauthorized. Configuration error.', 401);
        }

        $providedUser = $request->getUser(); // Gets user from Authorization header
        $providedPass = $request->getPassword(); // Gets password from Authorization header

        if ($providedUser === $expectedUser && $providedPass === $expectedPass) {
            return $next($request); // Credentials match, proceed
        }

        // Credentials do not match or were not provided
        $headers = ['WWW-Authenticate' => 'Basic realm="Telescope Dev Access"'];

        return response('Unauthorized.', 401, $headers);
    }
}
