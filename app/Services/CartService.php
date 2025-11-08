<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Admin\Order\OrderCreateData;
use App\Data\Admin\Order\OrderItemCreateData;
use App\Data\Shop\Cart\AddCartItemData;
use App\Data\Shop\Cart\ApplyCouponData;
use App\Data\Shop\Cart\CartData;
use App\Data\Shop\Cart\UpdateCartItemData;
use App\Enums\Order\OrderStatusEnum;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\ProductDeliveryOption;
use App\Services\Discounts\OrderCalculationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class CartService
{
    public function __construct(
        private Request $request,
        private OrderCalculationService $orderCalculationService
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

        // @codeCoverageIgnoreStart
        // This should never happen if middleware is working correctly
        abort(500, 'Cart identifier not found in request');
        // @codeCoverageIgnoreEnd
    }

    /**
     * Get the current cart data with calculated totals.
     */
    public function getCart(): CartData
    {
        $cart = $this->findOrCreateCart();

        return $this->buildCartDataWithTotals($cart);
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
                'payment_type'               => $data->payment_type->value,
                'quantity'                   => $data->quantity,
            ]);
        }

        // Reload cart with relationships
        $cart->load(['items.productDeliveryOption.product']);

        return $this->buildCartDataWithTotals($cart);
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

        return $this->buildCartDataWithTotals($cart);
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

        return $this->buildCartDataWithTotals($cart);
    }

    /**
     * Apply a coupon code to the cart.
     */
    public function applyCoupon(ApplyCouponData $data): CartData
    {
        $cart = $this->findOrCreateCart();

        // Validate coupon exists and is active
        $coupon = \App\Models\DiscountCoupon::query()
            ->where('code', $data->coupon_code)
            ->where('is_active', true)
            ->first();

        if (! $coupon) {
            throw ValidationException::withMessages([
                'coupon_code' => __('validation.exists', ['attribute' => 'coupon code']),
            ]);
        }

        // Update cart with coupon code
        $cart->update([
            'applied_coupon_code' => $data->coupon_code,
        ]);

        // Reload cart with relationships
        $cart->load(['items.productDeliveryOption.product']);

        return $this->buildCartDataWithTotals($cart);
    }

    /**
     * Remove the applied coupon from the cart.
     */
    public function removeCoupon(): CartData
    {
        $cart = $this->findOrCreateCart();

        // Remove coupon code
        $cart->update([
            'applied_coupon_code' => null,
        ]);

        // Reload cart with relationships
        $cart->load(['items.productDeliveryOption.product']);

        return $this->buildCartDataWithTotals($cart);
    }

    /**
     * Merge a guest cart into a user's cart after login.
     * This is called after successful authentication with a guest token.
     */
    public function mergeGuestCart(string $guestToken, int $userId): void
    {
        if (! Str::isUuid($guestToken)) {
            // Invalid guest token format
            return;
        }
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
    }

    /**
     * Delete the current cart.
     */
    public function deleteCart(): void
    {
        $cart = $this->findOrCreateCart();
        $cart->delete();
    }

    /**
     * Calculate cart totals using the order calculation service.
     */
    private function buildCartDataWithTotals(Cart $cart): CartData
    {
        // If cart is empty, return with zero totals
        if ($cart->items->isEmpty()) {
            return CartData::fromModel($cart, 0, 0, 0);
        }

        // Get the user ID - for guest carts, we'll use a temporary user concept
        $userId = $cart->user_id ?? Auth::guard('user')->id();

        // If no user (guest cart), we can't apply user-specific discounts
        // We'll use a minimal approach - calculate without user-specific promotions
        if (! $userId) {
            // For guest carts, calculate subtotal without discounts
            $subtotal = $cart->items->sum(function ($item) {
                return $item->productDeliveryOption->price * $item->quantity;
            });

            return CartData::fromModel($cart, $subtotal, 0, $subtotal);
        }

        // Convert cart to OrderCreateData for calculation
        $items = $cart->items->map(fn (CartItem $item) => new OrderItemCreateData(
            product_delivery_option_id: $item->product_delivery_option_id,
            payment_type: $item->payment_type->value,
            qty_ordered: $item->quantity
        ))->all();

        $orderData = new OrderCreateData(
            status: OrderStatusEnum::PENDING->value,
            customer_id: $userId,
            items: $items,
            applied_coupon_code: $cart->applied_coupon_code
        );

        // Calculate totals using existing order calculation service
        $context = $this->orderCalculationService->calculate($orderData);

        // Extract totals from context
        $subtotal       = $context->items->sum(fn ($item) => $item->price * $item->qty);
        $discountAmount = $context->items->sum('discount_amount');
        $grandTotal     = $subtotal - $discountAmount;

        return CartData::fromModel($cart, $subtotal, $discountAmount, $grandTotal);
    }
}
