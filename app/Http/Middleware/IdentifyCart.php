<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class IdentifyCart
{
    /**
     * Handle an incoming request.
     *
     * This middleware identifies the cart owner (authenticated user or guest)
     * and stores the identifier in the request for use by CartService.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if user is authenticated
        if (auth('user')->check()) {
            // Store user ID as cart identifier
            $request->merge([
                'cart_user_id'     => auth('user')->id(),
                'cart_guest_token' => null,
            ]);
        } else {
            // Guest user - get or generate guest token
            $guestToken = $request->header('X-Guest-Token');

            if (! $guestToken || ! Str::isUuid($guestToken)) {
                // Generate new guest token if not provided or invalid
                $guestToken = (string) Str::uuid();
            }

            $request->merge([
                'cart_user_id'     => null,
                'cart_guest_token' => $guestToken,
            ]);

            // Add guest token to response header for client to store
            $response = $next($request);
            $response->headers->set('X-Guest-Token', $guestToken);

            return $response;
        }

        return $next($request);
    }
}
