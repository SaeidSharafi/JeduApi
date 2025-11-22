<?php

namespace App\Listeners;

use App\Events\CustomerAuthenticatedEvent;
use App\Services\CartService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class MergeGuestCartAfterLogin
{
    /**
     * Create the event listener.
     */
    public function __construct(
        private readonly CartService $cartService
    )
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(CustomerAuthenticatedEvent $event): void
    {
        $guestToken = $event->request->header('X-Guest-Token');

        if ($guestToken && $event->user) {
            try {
                $this->cartService->mergeGuestCart($guestToken, $event->user->id);
            }
            //@codeCoverageIgnoreStart
            catch (Exception $e) {
                // Log error but don't fail the login
                Log::warning('Failed to merge guest cart after login', [
                    'user_id'     => $event->user->id,
                    'guest_token' => $guestToken,
                    'error'       => $e->getMessage(),
                ]);
            }
            //@codeCoverageIgnoreEnd
        }
    }
}
