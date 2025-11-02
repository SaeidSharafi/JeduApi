<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Actions\Shop\MergeGuestCartAction;
use Closure;
use Exception;
use Illuminate\Http\Request;
use Log;
use Symfony\Component\HttpFoundation\Response;

final class MergeGuestCartAfterLogin
{
    public function __construct(
        private readonly MergeGuestCartAction $mergeGuestCartAction
    ) {}

    /**
     * Handle an incoming request and merge guest cart after successful login.
     *
     * This middleware should be applied to authentication endpoints. It runs after
     * authentication is complete, checks for the X-Guest-Token header, and merges
     * the guest cart into the authenticated user's cart.
     *
     * Note: Since authentication happens inside the controller action (not in middleware),
     * we need to run this AFTER the controller response to ensure the user is authenticated.
     * However, we store the request guest token for later use.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Store the guest token before the request is processed
        $guestToken = $request->header('X-Guest-Token');

        // Process the request (authentication happens here)
        $response = $next($request);

        // After authentication, if the response is successful and user is now authenticated
        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            // Check if user is now authenticated and had a guest token
            if ($guestToken && auth('user')->check()) {
                $userId = auth('user')->id();

                try {
                    $this->mergeGuestCartAction->handle($guestToken, $userId);
                } catch (Exception $e) {
                    // Log error but don't fail the login
                    Log::warning('Failed to merge guest cart after login', [
                        'user_id'     => $userId,
                        'guest_token' => $guestToken,
                        'error'       => $e->getMessage(),
                    ]);
                }
            }
        }

        return $response;
    }
}
