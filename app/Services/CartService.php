<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Shop\Cart\AddCartItemData;
use App\Data\Shop\Cart\CartData;
use App\Data\Shop\Cart\UpdateCartItemData;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\ProductDeliveryOption;
use Illuminate\Http\Request;

final readonly class CartService
{
    public function __construct(
        private Request $request
    ) {}

    /**
     * Find or create cart based on authenticated user or guest token.
     * This is the primary method for cart identification.
     */
    public function findOrCreateCart(): Cart
    {
        $userId     = $this->request->get('cart_user_id');
        $guestToken = $this->request->get('cart_guest_token');

        $query = Cart::query()->with(['items.productDeliveryOption.product']);

        // Try to find by user_id first (authenticated user)
        if ($userId) {
            $cart = $query->where('user_id', $userId)->first();

            if (! $cart) {
                // Create new cart for authenticated user
                $cart = Cart::create(['user_id' => $userId]);
                $cart->load(['items.productDeliveryOption.product']);
            }

            return $cart;
        }

        // Fall back to guest_token (guest user)
        if ($guestToken) {
            $cart = $query->where('guest_token', $guestToken)->first();

            if (! $cart) {
                // Create new cart for guest
                $cart = Cart::create(['guest_token' => $guestToken]);
                $cart->load(['items.productDeliveryOption.product']);
            }

            return $cart;
        }

        // This should never happen if middleware is working correctly
        abort(500, 'Cart identifier not found in request');
    }

    /**
     * Get the current cart data.
     */
    public function getCart(): CartData
    {
        $cart = $this->findOrCreateCart();

        return CartData::fromModel($cart);
    }

    /**
     * Add an item to the cart.
     */
    public function addItem(AddCartItemData $data): CartData
    {
        $cart = $this->findOrCreateCart();

        // Resolve UUID to internal ID
        $deliveryOption = ProductDeliveryOption::query()
            ->where('uuid', $data->product_delivery_option_uuid)
            ->firstOrFail();

        // Check if the item already exists in the cart
        $existingItem = $cart->items()
            ->where('product_delivery_option_id', $deliveryOption->id)
            ->first();

        if ($existingItem) {
            // Update quantity if item already exists
            $existingItem->update([
                'quantity' => $existingItem->quantity + $data->quantity,
            ]);
        } else {
            // Create new cart item
            $cart->items()->create([
                'product_delivery_option_id' => $deliveryOption->id,
                'quantity'                   => $data->quantity,
            ]);
        }

        // Reload cart with relationships
        $cart->load(['items.productDeliveryOption.product']);

        return CartData::fromModel($cart);
    }

    /**
     * Update the quantity of a cart item.
     */
    public function updateItem(int $cartItemId, UpdateCartItemData $data): CartData
    {
        $cart = $this->findOrCreateCart();

        $cartItem = CartItem::query()
            ->where('id', $cartItemId)
            ->where('cart_id', $cart->id)
            ->firstOrFail();

        $cartItem->update([
            'quantity' => $data->quantity,
        ]);

        // Reload cart with relationships
        $cart->load(['items.productDeliveryOption.product']);

        return CartData::fromModel($cart);
    }

    /**
     * Remove an item from the cart.
     */
    public function removeItem(int $cartItemId): CartData
    {
        $cart = $this->findOrCreateCart();

        $cartItem = CartItem::query()
            ->where('id', $cartItemId)
            ->where('cart_id', $cart->id)
            ->firstOrFail();

        $cartItem->delete();

        // Reload cart with relationships
        $cart->load(['items.productDeliveryOption.product']);

        return CartData::fromModel($cart);
    }
}
