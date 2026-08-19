<?php

declare(strict_types=1);

namespace App\Actions\Shop;

use App\Actions\Admin\Order\CreateOrderAction;
use App\Actions\Admin\Order\ValidateNoDuplicatePurchasesAction;
use App\Actions\Payment\CompleteFreeOrderPaymentAction;
use App\Contracts\Payment\PendingPaymentPreparerContract;
use App\Data\Admin\Order\OrderCreateData;
use App\Data\Admin\Order\OrderItemCreateData;
use App\Data\Admin\Payment\PaymentProcessResultData;
use App\Data\Shop\Cart\CheckoutData;
use App\Enums\Content\PublicationStatusEnum;
use App\Enums\Order\OrderStatusEnum;
use App\Enums\Payment\PaymentMethodEnum;
use App\Enums\Payment\PaymentPurposeEnum;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\CartService;
use App\Services\Discounts\OrderCalculationService;
use App\Services\Payment\PaymentProcessorFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use ValueError;

final readonly class CreateOrderFromCartAction
{
    public function __construct(
        private CartService $cartService,
        private OrderCalculationService $orderCalculationService,
        private CreateOrderAction $createOrderAction,
        private PaymentProcessorFactory $processorFactory,
        private ValidateNoDuplicatePurchasesAction $validateNoDuplicatePurchases,
        private CompleteFreeOrderPaymentAction $completeFreeOrderPayment,
        private PendingPaymentPreparerContract $preparePendingPayment,
    ) {}

    /**
     * Convert a cart into an order and process payment.
     *
     * Payment eligibility (method required, valid, processor available) is
     * validated BEFORE the order-creation transaction, so a rejected payment
     * setup never deletes the cart. The pending payment is then prepared
     * atomically with order creation: if order creation OR payment prep
     * fails, the transaction rolls back and the cart stays intact.
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
        // Step 0: Validate payment eligibility before any mutation happens.
        // A missing/invalid payment method must fail fast with a 422 —
        // never after the cart is deleted.
        $paymentMethod = $this->validatePaymentEligibility($checkoutData, $user);

        // Steps 1-7: Create order + prepare payment inside one DB transaction
        // (atomic cart→order conversion; any failure rolls back, keeping the cart).
        [$order, $payment] = DB::transaction(function () use ($checkoutData, $user, $paymentMethod): array {
            // Step 1: Get the cart model directly
            $cart = $this->cartService->findOrCreateCart($user, lockForUpdate: true);
            if ($cart->items->count() === 0) {
                throw ValidationException::withMessages([
                    'cart' => [__('messages.checkout.cart_empty')],
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

            // Step 7: Prepare the pending payment atomically with order creation.
            // If this throws, the transaction rolls back — cart intact, no orphan order.
            $payment = $this->preparePaymentForOrder($order, $checkoutData, $paymentMethod, $user);

            $cart->delete();

            return [$order, $payment];
        });

        return $this->processPayment($order, $checkoutData, $paymentMethod, $payment, $user);
    }

    /**
     * Validate payment eligibility BEFORE the order-creation transaction.
     *
     * Uses the current cart contents to estimate the grand total. A free
     * order (grand_total <= 0) needs no payment method. A paid order requires
     * a non-empty, valid payment method backed by a registered processor.
     *
     * @throws ValidationException
     */
    private function validatePaymentEligibility(CheckoutData $checkoutData, User $user): ?PaymentMethodEnum
    {
        // Read the cart without locking — this is a pre-flight check only.
        // The order-creation transaction re-fetches and re-validates everything.
        $cart = $this->cartService->findOrCreateCart($user);

        if ($cart->items->isEmpty()) {
            return null; // cart is empty → the transaction will throw cart_empty
        }

        $orderCreateData = $this->buildOrderCreateData($cart, $user);
        $context         = $this->orderCalculationService->calculate($orderCreateData);

        if ($context->calculateGrandTotal() <= 0) {
            return null; // free order → NO_PAYMENT, no payment method needed
        }

        $paymentMethod = $this->resolvePaymentMethod($checkoutData);

        // Confirm a processor is registered for the method (no gateway call).
        $this->processorFactory->make($paymentMethod);

        return $paymentMethod;
    }

    /**
     * Resolve the payment method from checkout data.
     *
     * @throws ValidationException
     */
    private function resolvePaymentMethod(CheckoutData $checkoutData): PaymentMethodEnum
    {
        if (empty($checkoutData->payment_method)) {
            throw ValidationException::withMessages([
                'payment_method' => [__('validation.custom.checkout.payment_method_required')],
            ]);
        }

        try {
            return PaymentMethodEnum::from($checkoutData->payment_method);
        } catch (ValueError) {
            throw ValidationException::withMessages([
                'payment_method' => [__('validation.custom.checkout.invalid_payment_method')],
            ]);
        }
    }

    /**
     * Prepare the pending payment for a paid order.
     */
    private function preparePaymentForOrder(
        Order $order,
        CheckoutData $checkoutData,
        ?PaymentMethodEnum $paymentMethod,
        User $user
    ): ?Payment {
        if ($order->grand_total <= 0) {
            return null; // free order → payment handled by createFreeOrderPayment
        }

        // Defensive re-resolution: the cart may have changed since the
        // pre-flight eligibility check (e.g. price/quantity drift).
        $paymentMethod ??= $this->resolvePaymentMethod($checkoutData);

        return $this->preparePendingPayment->handle(
            actor: $user,
            customerId: $user->id,
            method: $paymentMethod,
            purpose: PaymentPurposeEnum::ORDER,
            amount: $order->grand_total,
            order: $order,
            data: $checkoutData->payment_data
        );
    }

    /**
     * Process payment for the order based on the grand total and payment method.
     *
     * The payment record is already prepared (inside the order transaction);
     * only the processor call remains, which stays outside the application
     * transaction.
     *
     * @throws ValidationException
     */
    private function processPayment(
        Order $order,
        CheckoutData $checkoutData,
        ?PaymentMethodEnum $paymentMethod,
        ?Payment $payment,
        User $user
    ): PaymentProcessResultData {
        // Handle free orders automatically with NO_PAYMENT
        if ($order->grand_total <= 0) {
            return $this->createFreeOrderPayment($order, $user);
        }

        $paymentMethod ??= $this->resolvePaymentMethod($checkoutData);

        // Get the appropriate payment processor
        $processor = $this->processorFactory->make($paymentMethod);

        return $processor->process($payment);
    }

    /**
     * Create a NO_PAYMENT record for free orders.
     */
    private function createFreeOrderPayment(Order $order, User $user): PaymentProcessResultData
    {
        // Wrap in transaction so payment creation + event cascade are atomic,
        // matching the WalletPaymentProcessor's atomicity guarantee.
        return DB::transaction(fn (): PaymentProcessResultData => $this->completeFreeOrderPayment->handle(
            order: $order,
            actor: $user,
        ));
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
    private function validateCartItems(Cart $cart): void
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
                $errors["items.{$index}"] = [__('messages.product.no_longer_available', ['name' => $deliveryOption->product->name])];

                continue;
            }

            // Check if delivery option is active
            if ($deliveryOption->status !== PublicationStatusEnum::PUBLISHED) {
                $errors["items.{$index}"] = [__('messages.checkout.delivery_option_unavailable', ['name' => $deliveryOption->product->name])];

                continue;
            }

            // Check registration window (Gap #3 fix)
            $now = now();
            if ($deliveryOption->registration_start_date && $now->lt($deliveryOption->registration_start_date)) {
                $errors["items.{$index}"] = [__('messages.product.registration_not_started', ['name' => $deliveryOption->product->name])];

                continue;
            }
            if ($deliveryOption->registration_end_date && $now->gt($deliveryOption->registration_end_date)) {
                $errors["items.{$index}"] = [__('messages.product.registration_ended', ['name' => $deliveryOption->product->name])];

                continue;
            }

            // Check content availability window (Gap #4 fix)
            if ($deliveryOption->available_from && $now->lt($deliveryOption->available_from)) {
                $errors["items.{$index}"] = [__('messages.product.not_available_yet', ['name' => $deliveryOption->product->name])];

                continue;
            }
            if ($deliveryOption->available_to && $now->gt($deliveryOption->available_to)) {
                $errors["items.{$index}"] = [__('messages.product.no_longer_available', ['name' => $deliveryOption->product->name])];

                continue;
            }

            // Check capacity if applicable
            if ($deliveryOption->capacity !== null) {
                $enrolledCount     = $deliveryOption->enrolled_count;
                $reservedCount     = $deliveryOption->reserved_count;
                $availableCapacity = $deliveryOption->capacity - $enrolledCount - $reservedCount;

                if ($availableCapacity <= 0) {
                    $errors["items.{$index}"] = [__('validation.custom.checkout.product_delivery_option_sold_out', ['product_name' => $deliveryOption->product->name])];

                    continue;
                }
                // Beacuse we do not have any multi-quantity products, we never reach here in tests
                // @codeCoverageIgnoreStart
                if ($cartItem->quantity > $availableCapacity) {
                    $errors["items.{$index}"] = [
                        __('messages.checkout.spots_remaining', ['count' => $availableCapacity, 'name' => $deliveryOption->product->name, 'requested' => $cartItem->quantity]),
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
    private function buildOrderCreateData(Cart $cart, User $user): OrderCreateData
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
