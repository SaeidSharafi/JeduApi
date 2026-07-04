<?php

declare(strict_types=1);

use App\Actions\Shop\CreateOrderFromCartAction;
use App\Contracts\Payment\PaymentProcessorContract;
use App\Data\Admin\Payment\PaymentCreateData;
use App\Data\Admin\Payment\PaymentProcessResultData;
use App\Data\Shop\Cart\CheckoutData;
use App\Enums\Content\PublicationStatusEnum;
use App\Enums\Order\OrderStatusEnum;
use App\Enums\Payment\PaymentMethodEnum;
use App\Enums\System\MorphTypeEnum;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Course;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductDeliveryOption;
use App\Models\Term;
use App\Models\Vendor;
use App\Services\Payment\PaymentProcessorFactory;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(Tests\Support\Traits\AuthTestTrait::class);

beforeEach(function (): void {
    $vendor = Vendor::factory()->create();
    $term   = Term::factory()->create();
    $course = Course::factory()->create([
        'status' => PublicationStatusEnum::PUBLISHED,
    ]);

    $product = Product::factory()->create([
        'vendor_id'        => $vendor->id,
        'term_id'          => $term->id,
        'productable_id'   => $course->id,
        'productable_type' => MorphTypeEnum::COURSE->value,
        'status'           => PublicationStatusEnum::PUBLISHED,
        'is_visible'       => true,
        'name'             => 'Test Course Product',
    ]);

    $this->deliveryOption = ProductDeliveryOption::factory()->create([
        'product_id'              => $product->id,
        'price'                   => 500000,
        'uuid'                    => Str::uuid()->toString(),
        'capacity'                => 5,
        'status'                  => PublicationStatusEnum::PUBLISHED,
        'registration_start_date' => null,
        'registration_end_date'   => null,
        'available_from'          => null,
        'available_to'            => null,
    ]);

    $this->customer();
});

describe('CreateOrderFromCartAction idempotency & transaction scope', function (): void {
    it('payment_processor_called_outside_transaction', function (): void {
        // Arrange: create cart with items
        $cart = Cart::factory()->create(['user_id' => $this->user->id]);
        CartItem::factory()->create([
            'cart_id'                    => $cart->id,
            'product_delivery_option_id' => $this->deliveryOption->id,
            'quantity'                   => 1,
        ]);

        // Create a mock processor that records DB transaction level when process() is called
        $mockProcessor = new class implements PaymentProcessorContract
        {
            public int $transactionLevel = -1;

            public function canHandle(PaymentMethodEnum $paymentMethod): bool
            {
                return true;
            }

            public function process(
                Order $order,
                PaymentCreateData $paymentData,
                Authenticatable $adminUser,
                int $amountToPay,
            ): PaymentProcessResultData {
                $this->transactionLevel = DB::transactionLevel();

                return PaymentProcessResultData::completed(new Payment());
            }

            public function requiresRedirect(): bool
            {
                return false;
            }

            public function verify(Payment $payment, array $callbackData): Payment
            {
                throw new BadMethodCallException('Mock does not support verify.');
            }
        };

        // Register mock factory that returns our tracking processor
        $this->app->instance(
            PaymentProcessorFactory::class,
            new PaymentProcessorFactory([$mockProcessor])
        );

        // Act
        $action = app(CreateOrderFromCartAction::class);
        $action->handle(new CheckoutData(payment_method: 'bank_transfer'), $this->user);

        // Assert: processor called OUTSIDE application transaction.
        // Level 1 = only the RefreshDatabase baseline transaction is active.
        // Level >= 2 would mean a nested application DB::transaction still open.
        expect($mockProcessor->transactionLevel)->toBe(1);
    });

    it('allows_multiple_payment_attempts_on_same_order', function (): void {
        // Mock processor that creates a PENDING payment record (simulating gateway redirect)
        $mockProcessor = new class implements PaymentProcessorContract
        {
            public function canHandle(PaymentMethodEnum $paymentMethod): bool
            {
                return true;
            }

            public function process(
                Order $order,
                PaymentCreateData $paymentData,
                Authenticatable $adminUser,
                int $amountToPay,
            ): PaymentProcessResultData {
                $payment = Payment::create([
                    'order_id'    => $order->id,
                    'customer_id' => $adminUser->getAuthIdentifier(),
                    'amount'      => $amountToPay,
                    'method'      => $paymentData->method,
                    'status'      => 'pending',
                ]);

                return PaymentProcessResultData::pendingWithRedirect($payment, 'https://gateway.example.com/pay');
            }

            public function requiresRedirect(): bool
            {
                return true;
            }

            public function verify(Payment $payment, array $callbackData): Payment
            {
                throw new BadMethodCallException('Mock does not support verify.');
            }
        };

        $this->app->instance(
            PaymentProcessorFactory::class,
            new PaymentProcessorFactory([$mockProcessor])
        );

        $action       = app(CreateOrderFromCartAction::class);
        $checkoutData = new CheckoutData(payment_method: 'bank_transfer');

        // 1. First checkout - creates PENDING order + payment, then deletes cart
        $cart = Cart::factory()->create(['user_id' => $this->user->id]);
        CartItem::factory()->create([
            'cart_id'                    => $cart->id,
            'product_delivery_option_id' => $this->deliveryOption->id,
            'quantity'                   => 1,
        ]);
        $firstResult = $action->handle($checkoutData, $this->user);

        // 2. Verify order is PENDING
        $this->assertDatabaseHas('orders', [
            'customer_id' => $this->user->id,
            'status'      => OrderStatusEnum::PENDING->value,
        ]);

        // 3. Create a new cart (old one was deleted after order creation)
        // Simulating user re-adding items to cart for retry
        $cart2 = Cart::factory()->create(['user_id' => $this->user->id]);
        CartItem::factory()->create([
            'cart_id'                    => $cart2->id,
            'product_delivery_option_id' => $this->deliveryOption->id,
            'quantity'                   => 1,
        ]);

        // 4. Second checkout - creates NEW order (no idempotency check anymore)
        $secondResult = $action->handle($checkoutData, $this->user);

        // 5. Two separate orders created
        expect(Order::where('customer_id', $this->user->id)->count())->toBe(2);

        // 6. Each order has its own payment
        expect($secondResult->payment->order_id)->not->toBe($firstResult->payment->order_id);
    });

    it('deletes_cart_after_order_creation', function (): void {
        // Mock processor
        $mockProcessor = new class implements PaymentProcessorContract
        {
            public function canHandle(PaymentMethodEnum $paymentMethod): bool
            {
                return true;
            }

            public function process(
                Order $order,
                PaymentCreateData $paymentData,
                Authenticatable $adminUser,
                int $amountToPay,
            ): PaymentProcessResultData {
                $payment = Payment::create([
                    'order_id'    => $order->id,
                    'customer_id' => $adminUser->getAuthIdentifier(),
                    'amount'      => $amountToPay,
                    'method'      => $paymentData->method,
                    'status'      => 'pending',
                ]);

                return PaymentProcessResultData::pendingWithRedirect($payment, 'https://gateway.example.com/pay');
            }

            public function requiresRedirect(): bool
            {
                return true;
            }

            public function verify(Payment $payment, array $callbackData): Payment
            {
                throw new BadMethodCallException('Mock does not support verify.');
            }
        };

        $this->app->instance(
            PaymentProcessorFactory::class,
            new PaymentProcessorFactory([$mockProcessor])
        );

        // Create cart with items
        $cart = Cart::factory()->create(['user_id' => $this->user->id]);
        CartItem::factory()->create([
            'cart_id'                    => $cart->id,
            'product_delivery_option_id' => $this->deliveryOption->id,
            'quantity'                   => 1,
        ]);

        $action       = app(CreateOrderFromCartAction::class);
        $checkoutData = new CheckoutData(payment_method: 'bank_transfer');

        // Checkout
        $action->handle($checkoutData, $this->user);

        // Assert: cart was deleted (no items, and cart record should be gone)
        expect($cart->fresh())->toBeNull();
    });
});
