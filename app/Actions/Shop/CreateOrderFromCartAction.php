<?php

declare(strict_types=1);

namespace App\Actions\Shop;

use App\Actions\Admin\Order\CreateOrderAction;
use App\Actions\Wallet\RecordWalletTransactionAction;
use App\Data\Admin\Order\OrderCreateData;
use App\Data\Admin\Order\OrderItemCreateData;
use App\Data\Admin\Wallet\RecordTransactionData;
use App\Data\Shop\Cart\CheckoutData;
use App\Enums\Content\PublicationStatusEnum;
use App\Enums\Order\OrderItemPaymentTypeEnum;
use App\Enums\Order\OrderStatusEnum;
use App\Enums\Payment\PaymentMethodEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Enums\Wallet\TransactionSourceEnum;
use App\Enums\Wallet\TransactionTypeEnum;
use App\Models\Order;
use App\Models\Payment;
use App\Services\CartService;
use App\Services\OrderStatusService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class CreateOrderFromCartAction
{
    public function __construct(
        private CartService $cartService,
        private CreateOrderAction $createOrderAction,
        private RecordWalletTransactionAction $recordWalletTransaction,
        private OrderStatusService $orderStatusService
    ) {}

    /**
     * Convert a cart into a pending order and optionally process payment.
     *
     * @throws ValidationException
     */
    public function handle(CheckoutData $checkoutData): Order
    {
        return DB::transaction(function () use ($checkoutData): Order {
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
            $order = $this->createOrderAction->handle($orderCreateData);

            // Step 5: Process payment if wallet method is selected
            if ($checkoutData->payment_method === 'wallet') {
                $this->processWalletPayment($order);
            }

            // Step 6: Delete the cart after successful checkout
            $this->cartService->deleteCart();

            return $order->fresh(['items.productDeliveryOption.product', 'customer', 'payments']);
        });
    }

    /**
     * Process wallet payment for the order.
     *
     * @throws ValidationException
     */
    private function processWalletPayment(Order $order): void
    {
        $user = Auth::guard('user')->user();

        if (! $user || ! $user->wallet) {
            throw ValidationException::withMessages([
                'wallet' => ['Wallet not found for the current user.'],
            ]);
        }

        // Check if user has sufficient balance (including gift balance)
        $availableBalance = $user->wallet->balance + $user->wallet->gift_balance;
        if ($availableBalance < $order->grand_total) {
            throw ValidationException::withMessages([
                'wallet' => [
                    sprintf(
                        'Insufficient wallet balance. You have %s, but need %s.',
                        number_format($availableBalance),
                        number_format($order->grand_total)
                    ),
                ],
            ]);
        }

        // Record wallet transaction (debit)
        $this->recordWalletTransaction->execute(
            new RecordTransactionData(
                user_id: $user->id,
                type: TransactionTypeEnum::PAYMENT,
                amount: $order->grand_total,
                source_type: TransactionSourceEnum::ORDER,
                source_id: $order->id,
                description: "Payment for order #{$order->increment_id}",
                metadata: [
                    'order_id'       => $order->id,
                    'order_number'   => $order->increment_id,
                    'payment_method' => 'wallet',
                ]
            )
        );

        // Create payment record
        Payment::create([
            'order_id'    => $order->id,
            'customer_id' => $user->id,
            'method'      => PaymentMethodEnum::WALLET->value,
            'amount'      => $order->grand_total,
            'status'      => PaymentStatusEnum::COMPLETED,
            'data'        => [
                'wallet_payment' => true,
                'user_id'        => $user->id,
            ],
        ]);

        // Finalize the order using OrderStatusService
        $this->orderStatusService->handlePaymentCompletion($order);
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
