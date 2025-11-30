<?php

declare(strict_types=1);

namespace App\Actions\Shop;

use App\Actions\Admin\Order\CreateOrderAction;
use App\Actions\Admin\Order\ValidateNoDuplicatePurchasesAction;
use App\Data\Admin\Order\OrderCreateData;
use App\Data\Admin\Order\OrderItemCreateData;
use App\Data\Admin\Payment\PaymentCreateData;
use App\Data\Admin\Payment\PaymentProcessResultData;
use App\Data\Shop\Cart\CheckoutData;
use App\Enums\Content\PublicationStatusEnum;
use App\Enums\Order\OrderStatusEnum;
use App\Enums\Payment\PaymentMethodEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Events\PaymentCompletedEvent;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\CartService;
use App\Services\Payment\PaymentProcessorFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class CreateOrderFromCartAction
{
    public function __construct(
        private CartService $cartService,
        private CreateOrderAction $createOrderAction,
        private PaymentProcessorFactory $processorFactory,
        private ValidateNoDuplicatePurchasesAction $validateNoDuplicatePurchases,
    ) {}

    /**
     * Convert a cart into an order and process payment.
     *
     * Returns PaymentProcessResultData which may contain:
     * - For free orders: completed payment with NO_PAYMENT
     * - For single-step payments (wallet): completed payment with no redirect
     * - For multi-step payments (online gateway): pending payment with redirect URL
     *
     * @throws ValidationException
     */
    public function handle(CheckoutData $checkoutData, User $user): PaymentProcessResultData
    {
        return DB::transaction(function () use ($checkoutData, $user): PaymentProcessResultData {
            // Step 1: Get the cart model directly
            $cart = $this->cartService->findOrCreateCart($user);

            if ($cart->items->count() === 0) {
                throw ValidationException::withMessages([
                    'cart' => ['Your cart is empty. Please add items before checking out.'],
                ]);
            }

            // Step 2: Check velocity limit (max 5 orders in the last hour)
            $this->validateOrderVelocity($user);

            // Step 3: Validate availability and capacity for each item
            $this->validateCartItems($cart);

            // Step 4: Validate that the user doesn't already own these products
            $deliveryOptions = $cart->items->pluck('productDeliveryOption');
            $this->validateNoDuplicatePurchases->handle($user, $deliveryOptions);
            // Step 5: Build OrderCreateData from cart
            $orderCreateData = $this->buildOrderCreateData($cart, $user);

            // Step 6: Execute the existing CreateOrderAction
            $order = $this->createOrderAction->handle($orderCreateData);

            // Step 7: Process payment based on order total
            $result = $this->processPayment($order, $checkoutData, $user);

            // Step 8: Delete the cart after successful checkout
            $this->cartService->deleteCart();

            return $result;
        });
    }

    /**
     * Process payment for the order based on the grand total and payment method.
     *
     * @throws ValidationException
     */
    private function processPayment(Order $order, CheckoutData $checkoutData, User $user): PaymentProcessResultData
    {
        // Handle free orders automatically with NO_PAYMENT
        if ($order->grand_total <= 0) {
            return $this->createFreeOrderPayment($order, $user);
        }

        // For paid orders, payment_method is required
        if (empty($checkoutData->payment_method)) {
            throw ValidationException::withMessages([
                'payment_method' => [__('validation.custom.checkout.payment_method_required')],
            ]);
        }

        $paymentMethod = PaymentMethodEnum::from($checkoutData->payment_method);

        // Get the appropriate payment processor
        $processor = $this->processorFactory->make($paymentMethod);

        // Create PaymentCreateData for the processor
        $paymentData = new PaymentCreateData(
            method: $paymentMethod->value,
            data: null,
            admin_notes: null
        );

        // Process the payment
        return $processor->process($order, $paymentData, $user, $order->grand_total);
    }

    /**
     * Create a NO_PAYMENT record for free orders.
     */
    private function createFreeOrderPayment(Order $order, User $user): PaymentProcessResultData
    {
        $payment = Payment::create([
            'order_id'    => $order->id,
            'customer_id' => $user->id,
            'amount'      => 0,
            'method'      => PaymentMethodEnum::NO_PAYMENT->value,
            'status'      => PaymentStatusEnum::COMPLETED->value,
            'admin_notes' => 'Free order automatically completed.',
        ]);

        // Dispatch event to trigger enrollments
        PaymentCompletedEvent::dispatch($payment);

        return PaymentProcessResultData::completed($payment);
    }

    /**
     * Validate that the user hasn't exceeded the order velocity limit.
     *
     * @throws ValidationException
     */
    private function validateOrderVelocity(User $user): void
    {
        // Check how many orders the user has created in the last hour
        $ordersInLastHour = Order::where('customer_id', $user->id)
            ->where('created_at', '>=', now()->subHour())
            ->count();

        if ($ordersInLastHour >= 5) {
            throw ValidationException::withMessages([
                'velocity' => [__('validation.custom.checkout.order_velocity_exceeded')],
            ]);
        }
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

            // its impossible to have cart item without delivery option, but just in case
            // @codeCoverageIgnoreStart
            if (! $deliveryOption) {
                $errors["items.{$index}"] = [__('validation.custom.checkout.product_delivery_option_not_found')];

                continue;
            }
            // @codeCoverageIgnoreEnd

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

            // Check registration window (Gap #3 fix)
            $now = now();
            if ($deliveryOption->registration_start_date && $now->lt($deliveryOption->registration_start_date)) {
                $errors["items.{$index}"] = ["Registration for '{$deliveryOption->product->name}' has not started yet."];
                continue;
            }
            if ($deliveryOption->registration_end_date && $now->gt($deliveryOption->registration_end_date)) {
                $errors["items.{$index}"] = ["Registration period for '{$deliveryOption->product->name}' has ended."];
                continue;
            }

            // Check content availability window (Gap #4 fix)
            if ($deliveryOption->available_from && $now->lt($deliveryOption->available_from)) {
                $errors["items.{$index}"] = ["'{$deliveryOption->product->name}' is not yet available for purchase."];
                continue;
            }
            if ($deliveryOption->available_to && $now->gt($deliveryOption->available_to)) {
                $errors["items.{$index}"] = ["'{$deliveryOption->product->name}' is no longer available for purchase."];
                continue;
            }

            // Check capacity if applicable
            if ($deliveryOption->capacity !== null) {
                $enrolledCount     = $deliveryOption->enrolled_count;
                $availableCapacity = $deliveryOption->capacity - $enrolledCount;

                if ($availableCapacity <= 0) {
                    $errors["items.{$index}"] = [__('validation.custom.checkout.product_delivery_option_sold_out', ['product_name' => $deliveryOption->product->name])];

                    continue;
                }
                // Beacuse we do not have any multi-quantity products, we never reach here in tests
                // @codeCoverageIgnoreStart
                if ($cartItem->quantity > $availableCapacity) {
                    $errors["items.{$index}"] = [
                        "Only {$availableCapacity} spot(s) remaining for '{$deliveryOption->product->name}', but you requested {$cartItem->quantity}.",
                    ];
                }
                // @codeCoverageIgnoreEnd
            }
        }

        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Build OrderCreateData from Cart model.
     */
    private function buildOrderCreateData($cart, User $user): OrderCreateData
    {
        // Convert cart items to order items
        $orderItems = [];
        /* @var  $cartItem CartItem */
        foreach ($cart->items as $cartItem) {
            $orderItems[] = new OrderItemCreateData(
                product_delivery_option_id: $cartItem->product_delivery_option_id,
                payment_type: $cartItem->payment_type->value,
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
