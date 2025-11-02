<?php

declare(strict_types=1);

namespace App\Actions\Shop;

use App\Actions\Admin\Order\CreateOrderAction;
use App\Data\Admin\Order\OrderCreateData;
use App\Data\Admin\Order\OrderItemCreateData;
use App\Enums\Content\PublicationStatusEnum;
use App\Enums\Order\OrderItemPaymentTypeEnum;
use App\Enums\Order\OrderStatusEnum;
use App\Models\Order;
use App\Services\CartService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class CreateOrderFromCartAction
{
    public function __construct(
        private CartService $cartService,
        private CreateOrderAction $createOrderAction
    ) {}

    /**
     * Convert a cart into a pending order.
     *
     * @throws ValidationException
     */
    public function handle(): Order
    {
        return DB::transaction(function (): Order {
            // Step 1: Get the cart model directly
            $cart = $this->cartService->findOrCreateCart();

            if ($cart->items->count() === 0) {
                throw ValidationException::withMessages([
                    'cart' => ['Your cart is empty. Please add items before checking out.'],
                ]);
            }

            // Step 2: Validate availability and capacity for each item
            $this->validateCartItems($cart);

            // Step 3: Build OrderCreateData from cart
            $orderCreateData = $this->buildOrderCreateData($cart);

            // Step 4: Execute the existing CreateOrderAction
            return $this->createOrderAction->handle($orderCreateData);
        });
    }

    /**
     * Validate that all cart items are available and have sufficient capacity.
     *
     * @throws ValidationException
     */
    private function validateCartItems($cart): void
    {
        $errors = [];

        foreach ($cart->items as $index => $cartItem) {
            $deliveryOption = $cartItem->productDeliveryOption;
            $deliveryOption->load('product');

            if (! $deliveryOption) {
                $errors["items.{$index}"] = ['Product delivery option not found.'];

                continue;
            }

            // Check if product is published and visible
            if ($deliveryOption->product->status !== PublicationStatusEnum::PUBLISHED || ! $deliveryOption->product->is_visible) {
                $errors["items.{$index}"] = ["The product '{$deliveryOption->product->name}' is no longer available."];

                continue;
            }

            // Check if delivery option is active
            if ($deliveryOption->status !== PublicationStatusEnum::PUBLISHED) {
                $errors["items.{$index}"] = ["The delivery option for '{$deliveryOption->product->name}' is no longer available."];

                continue;
            }

            // Check capacity if applicable
            if ($deliveryOption->capacity !== null) {
                $enrolledCount     = $deliveryOption->enrollments()->count();
                $availableCapacity = $deliveryOption->capacity - $enrolledCount;

                if ($availableCapacity <= 0) {
                    $errors["items.{$index}"] = ["The delivery option for '{$deliveryOption->product->name}' is sold out."];

                    continue;
                }

                if ($cartItem->quantity > $availableCapacity) {
                    $errors["items.{$index}"] = [
                        "Only {$availableCapacity} spot(s) remaining for '{$deliveryOption->product->name}', but you requested {$cartItem->quantity}.",
                    ];
                }
            }
        }

        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Build OrderCreateData from Cart model.
     */
    private function buildOrderCreateData($cart): OrderCreateData
    {
        $user = Auth::guard('user')->user();

        if (! $user) {
            throw ValidationException::withMessages([
                'user' => ['You must be logged in to complete checkout.'],
            ]);
        }

        // Convert cart items to order items
        $orderItems = [];
        foreach ($cart->items as $cartItem) {
            $orderItems[] = new OrderItemCreateData(
                product_delivery_option_id: $cartItem->product_delivery_option_id,
                payment_type: OrderItemPaymentTypeEnum::FULL_PAYMENT->value,
                qty_ordered: $cartItem->quantity
            );
        }

        return new OrderCreateData(
            status: OrderStatusEnum::PENDING->value,
            customer_id: $user->id,
            items: $orderItems,
            applied_coupon_code: $cart->applied_coupon_code,
            admin_notes: null,
            promotion_id: null
        );
    }
}
