<?php

declare(strict_types=1);

namespace App\Actions\Shop;

use App\Models\Cart;
use Illuminate\Support\Facades\DB;

final class MergeGuestCartAction
{
    /**
     * Merge a guest cart into a user's cart after login.
     * This is called after successful authentication with a guest token.
     */
    public function handle(string $guestToken, int $userId): void
    {
        DB::transaction(function () use ($guestToken, $userId): void {
            // Find the guest cart
            $guestCart = Cart::query()
                ->where('guest_token', $guestToken)
                ->with('items')
                ->first();

            if (! $guestCart || $guestCart->items->isEmpty()) {
                // No guest cart or empty cart, nothing to merge
                return;
            }

            // Find or create the user's cart
            $userCart = Cart::query()
                ->where('user_id', $userId)
                ->first();

            if (! $userCart) {
                // No user cart exists, convert guest cart to user cart
                $guestCart->update([
                    'user_id'     => $userId,
                    'guest_token' => null,
                ]);

                return;
            }

            // User cart exists, merge guest cart items into it
            foreach ($guestCart->items as $guestItem) {
                // Check if user already has this item
                $existingItem = $userCart->items()
                    ->where('product_delivery_option_id', $guestItem->product_delivery_option_id)
                    ->first();

                if ($existingItem) {
                    // Add quantities together
                    $existingItem->update([
                        'quantity' => $existingItem->quantity + $guestItem->quantity,
                    ]);
                } else {
                    // Move item to user cart
                    $guestItem->update([
                        'cart_id' => $userCart->id,
                    ]);
                }
            }

            // Delete the guest cart
            $guestCart->delete();
        });
    }
}
