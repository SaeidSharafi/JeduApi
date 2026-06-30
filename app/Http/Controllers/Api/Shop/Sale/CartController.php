<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\Sale;

use App\Data\Shop\Cart\AddCartItemData;
use App\Data\Shop\Cart\ApplyCouponData;
use App\Data\Shop\Cart\UpdateCartItemData;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiSuccessResponse;
use App\Models\CartItem;
use App\Services\CartService;
use Illuminate\Http\JsonResponse;

/**
 * @group Shop - Cart
 *
 * APIs for managing shopping cart.
 *
 * pass X-Guest-Token header for guest carts.
 *
 * @authenticated user
 */
final class CartController extends Controller
{
    public function __construct(
        private readonly CartService $cartService
    ) {}

    /**
     * Get the current user's cart.
     *
     * @responseFile 200 resources/responses/shop/cart/index.json
     */
    public function index(): ApiSuccessResponse
    {
        $cartData = $this->cartService->getCart();

        return apiResponse()->success($cartData);
    }

    /**
     * Add an item to the cart.
     *
     * @responseFile resources/responses/shop/cart/show.json
     */
    public function store(AddCartItemData $data): ApiSuccessResponse
    {
        $cartData = $this->cartService->addItem($data);

        return apiResponse()->success($cartData);
    }

    /**
     * Update a cart item's quantity.
     *
     * @responseFile resources/responses/shop/cart/show.json
     */
    public function update(UpdateCartItemData $data, CartItem $cartItem): ApiSuccessResponse
    {
        $cartData = $this->cartService->updateItem($cartItem->id, $data);

        return apiResponse()->updated($cartData);
    }

    /**
     * Remove an item from the cart.
     */
    public function destroy(CartItem $cartItem): JsonResponse
    {
        $this->cartService->removeItem($cartItem->id);

        return apiResponse()->noContentJson();
    }

    /**
     * Apply a coupon code to the cart.
     *
     * @responseFile resources/responses/shop/cart/show.json
     */
    public function applyCoupon(ApplyCouponData $data): ApiSuccessResponse
    {
        $cartData = $this->cartService->applyCoupon($data);

        return apiResponse()->success($cartData);
    }

    /**
     * Remove the applied coupon from the cart.
     *
     * @responseFile resources/responses/shop/cart/show.json
     */
    public function removeCoupon(): ApiSuccessResponse
    {
        $cartData = $this->cartService->removeCoupon();

        return apiResponse()->success($cartData);
    }
}
