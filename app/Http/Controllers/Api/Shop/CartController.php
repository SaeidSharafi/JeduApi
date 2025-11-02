<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop;

use App\Data\Shop\Cart\AddCartItemData;
use App\Data\Shop\Cart\UpdateCartItemData;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiSuccessResponse;
use App\Models\CartItem;
use App\Services\CartService;
use Illuminate\Http\Response;

/**
 * @group Shop - Cart
 *
 * APIs for managing shopping cart.
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
     * @responseFile 200 storage/responses/shop/cart/index.json
     */
    public function index(): ApiSuccessResponse
    {
        $cartData = $this->cartService->getCart();

        return response()->success($cartData);
    }

    /**
     * Add an item to the cart.
     *
     * @responseFile storage/responses/shop/cart/show.json
     */
    public function store(AddCartItemData $data): ApiSuccessResponse
    {
        $cartData = $this->cartService->addItem($data);

        return response()->success($cartData);
    }

    /**
     * Update a cart item's quantity.
     *
     * @responseFile storage/responses/shop/cart/show.json
     */
    public function update(UpdateCartItemData $data, CartItem $cartItem): ApiSuccessResponse
    {
        $cartData = $this->cartService->updateItem($cartItem->id, $data);

        return response()->updated($cartData);
    }

    /**
     * Remove an item from the cart.
     */
    public function destroy(CartItem $cartItem): Response
    {
        $this->cartService->removeItem($cartItem->id);

        return response()->noContent();
    }
}
