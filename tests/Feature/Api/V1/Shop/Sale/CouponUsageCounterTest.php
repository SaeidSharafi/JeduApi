<?php

declare(strict_types=1);

use App\Actions\Shop\Payment\VerifyPaymentAction;
use App\Enums\Content\PublicationStatusEnum;
use App\Enums\Order\DiscountTypeEnum;
use App\Enums\Order\OrderStatusEnum;
use App\Enums\Payment\PaymentMethodEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Enums\PermissionEnum;
use App\Enums\System\MorphTypeEnum;
use App\Jobs\Provisioning\ProvisionEnrollmentProviderJob;
use App\Models\Course;
use App\Models\DiscountCoupon;
use App\Models\DiscountPromotion;
use App\Models\DiscountPromotionRule;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductDeliveryOption;
use App\Models\Term;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Payment\MellatGatewayPaymentProcessor;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Mockery as m;
use Tests\Support\Fakes\Payment\MockMultiStepProcessor;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\postJson;

uses(Tests\Support\Traits\AuthTestTrait::class);

beforeEach(function (): void {
    Queue::fake([
        ProvisionEnrollmentProviderJob::class,
    ]);
});

function createUsageCounterPromotion(string $name, array $actionConfig, string $type = 'apply_percentage_off'): DiscountPromotion
{
    $promotion = DiscountPromotion::create([
        'name'                             => $name,
        'type'                             => DiscountTypeEnum::CART_CHECKOUT,
        'is_active'                        => true,
        'priority'                         => 1,
        'starts_at'                        => now()->subDay(),
        'ends_at'                          => now()->addDay(),
        'stop_processing_subsequent_rules' => false,
        'requires_coupon'                  => true,
    ]);
    DiscountPromotionRule::create([
        'discount_promotion_id' => $promotion->id,
        'type'                  => 'action',
        'handler'               => $type,
        'configuration'         => $actionConfig,
    ]);
    DiscountCoupon::create([
        'discount_promotion_id' => $promotion->id,
        'code'                  => Str::upper(Str::random(6)),
        'usage_limit'           => 100,
    ]);

    return $promotion;
}

function createUsageCounterDeliveryOption(int $price = 100000): ProductDeliveryOption
{
    $vendor  = Vendor::factory()->create();
    $term    = Term::factory()->create();
    $course  = Course::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]);
    $product = Product::factory()->create([
        'vendor_id'        => $vendor->id,
        'term_id'          => $term->id,
        'productable_id'   => $course->id,
        'productable_type' => MorphTypeEnum::COURSE->value,
        'status'           => PublicationStatusEnum::PUBLISHED,
        'is_visible'       => true,
    ]);

    return ProductDeliveryOption::factory()->create([
        'product_id' => $product->id,
        'price'      => $price,
        'capacity'   => 10,
        'uuid'       => Str::uuid()->toString(),
        'status'     => PublicationStatusEnum::PUBLISHED,
    ]);
}

describe('Coupon usage counters move to order completion', function (): void {

    test('creating a pending order with a coupon does not increment usage counters', function (): void {
        $option    = createUsageCounterDeliveryOption();
        $promotion = createUsageCounterPromotion('Pending Checkout', ['percentage' => 10]);
        $coupon    = $promotion->coupons->first();

        $customer = User::factory()->create();
        $this->customer($customer);

        postJson(route('api.v1.shop.cart.items.store'), [
            'product_delivery_option_uuid' => $option->uuid,
            'quantity'                     => 1,
        ])->assertOk();
        postJson(route('api.v1.shop.cart.coupon.apply'), ['coupon_code' => $coupon->code])->assertOk();

        // Multi-step gateway checkout leaves the order PENDING.
        $this->instance(MellatGatewayPaymentProcessor::class, new MockMultiStepProcessor());
        $response = postJson(route('api.v1.shop.checkout'), [
            'payment_method' => PaymentMethodEnum::MELLAT_GATEWAY->value,
        ])->assertCreated();

        $orderId = $response->json('data.order.id');
        assertDatabaseHas('orders', [
            'id'     => $orderId,
            'status' => OrderStatusEnum::PENDING->value,
        ]);

        $promotion->refresh();
        $coupon->refresh();
        expect($promotion->total_usage_count)->toBe(0);
        expect($coupon->usage_count)->toBe(0);
    });

    test('completing an order increments usage counters', function (): void {
        $option    = createUsageCounterDeliveryOption();
        $promotion = createUsageCounterPromotion('Completing Checkout', ['percentage' => 10]);
        $coupon    = $promotion->coupons->first();

        $customer = User::factory()->create();
        $customer->wallet->update(['balance' => 500000]);
        $this->customer($customer);

        postJson(route('api.v1.shop.cart.items.store'), [
            'product_delivery_option_uuid' => $option->uuid,
            'quantity'                     => 1,
        ])->assertOk();
        postJson(route('api.v1.shop.cart.coupon.apply'), ['coupon_code' => $coupon->code])->assertOk();

        // Single-step wallet checkout completes the order in the same request.
        $response = postJson(route('api.v1.shop.checkout'), [
            'payment_method' => PaymentMethodEnum::WALLET->value,
        ])->assertCreated();

        $orderId = $response->json('data.order.id');
        assertDatabaseHas('orders', [
            'id'     => $orderId,
            'status' => OrderStatusEnum::COMPLETED->value,
        ]);

        $promotion->refresh();
        $coupon->refresh();
        expect($promotion->total_usage_count)->toBe(1);
        expect($coupon->usage_count)->toBe(1);
    });

    test('cancelling a pending order leaves usage counters unchanged', function (): void {
        $option    = createUsageCounterDeliveryOption();
        $promotion = createUsageCounterPromotion('Cancelled Checkout', ['percentage' => 10]);
        $coupon    = $promotion->coupons->first();

        $customer = User::factory()->create();
        $this->customer($customer);

        postJson(route('api.v1.shop.cart.items.store'), [
            'product_delivery_option_uuid' => $option->uuid,
            'quantity'                     => 1,
        ])->assertOk();
        postJson(route('api.v1.shop.cart.coupon.apply'), ['coupon_code' => $coupon->code])->assertOk();

        $this->instance(MellatGatewayPaymentProcessor::class, new MockMultiStepProcessor());
        $response = postJson(route('api.v1.shop.checkout'), [
            'payment_method' => PaymentMethodEnum::MELLAT_GATEWAY->value,
        ])->assertCreated();

        $incrementId = $response->json('data.order.increment_id');

        postJson(route('api.v1.shop.student.orders.cancel', $incrementId))->assertOk();

        $promotion->refresh();
        $coupon->refresh();
        expect($promotion->total_usage_count)->toBe(0);
        expect($coupon->usage_count)->toBe(0);
    });

    test('failing a pending order payment leaves usage counters unchanged', function (): void {
        $option    = createUsageCounterDeliveryOption();
        $promotion = createUsageCounterPromotion('Failed Payment', ['percentage' => 10]);
        $coupon    = $promotion->coupons->first();

        $customer = User::factory()->create();
        $this->customer($customer);

        postJson(route('api.v1.shop.cart.items.store'), [
            'product_delivery_option_uuid' => $option->uuid,
            'quantity'                     => 1,
        ])->assertOk();
        postJson(route('api.v1.shop.cart.coupon.apply'), ['coupon_code' => $coupon->code])->assertOk();

        $this->instance(MellatGatewayPaymentProcessor::class, new MockMultiStepProcessor());
        $response = postJson(route('api.v1.shop.checkout'), [
            'payment_method' => PaymentMethodEnum::MELLAT_GATEWAY->value,
        ])->assertCreated();

        $orderId = $response->json('data.order.id');
        $payment = Payment::where('order_id', $orderId)->firstOrFail();

        // Gateway declines → payment FAILED, order stays PENDING.
        $verifyMock = m::mock(VerifyPaymentAction::class);
        $verifyMock->expects('handle')
            ->once()
            ->andReturn(tap($payment)->setAttribute('status', PaymentStatusEnum::FAILED));
        app()->instance(VerifyPaymentAction::class, $verifyMock);

        postJson(route('api.v1.shop.payment.gateway.callback', ['payment' => $payment->uuid]), [
            'ResCode' => '12',
            'RefId'   => 'ref123',
        ])->assertRedirect(
            config('payments.redirect.failure')
            ."?payment={$payment->uuid}&purpose={$payment->purpose->value}&order={$response->json('data.order.increment_id')}"
        );

        assertDatabaseHas('orders', [
            'id'     => $orderId,
            'status' => OrderStatusEnum::PENDING->value,
        ]);

        $promotion->refresh();
        $coupon->refresh();
        expect($promotion->total_usage_count)->toBe(0);
        expect($coupon->usage_count)->toBe(0);
    });

    test('free order completion still increments usage counters', function (): void {
        // A fixed-amount coupon covering the full price makes the order free
        // while still recording an applied cart discount.
        $option    = createUsageCounterDeliveryOption(price: 50000);
        $promotion = createUsageCounterPromotion('Free Checkout', ['amount' => 50000], 'apply_fixed_amount_off');
        $coupon    = $promotion->coupons->first();

        $customer = User::factory()->create();
        $this->customer($customer);

        postJson(route('api.v1.shop.cart.items.store'), [
            'product_delivery_option_uuid' => $option->uuid,
            'quantity'                     => 1,
        ])->assertOk();
        postJson(route('api.v1.shop.cart.coupon.apply'), ['coupon_code' => $coupon->code])->assertOk();

        // Free orders complete instantly with NO_PAYMENT — no payment_method needed.
        $response = postJson(route('api.v1.shop.checkout'))->assertCreated();

        $orderId = $response->json('data.order.id');
        assertDatabaseHas('orders', [
            'id'          => $orderId,
            'status'      => OrderStatusEnum::COMPLETED->value,
            'grand_total' => 0,
        ]);

        $promotion->refresh();
        $coupon->refresh();
        expect($promotion->total_usage_count)->toBe(1);
        expect($coupon->usage_count)->toBe(1);
    });

    test('admin approval of an order increments usage counters', function (): void {
        $this->authorized_user([PermissionEnum::ORDER_APPROVE]);

        $promotion = DiscountPromotion::factory()->create([
            'name'      => 'Approved Order',
            'type'      => DiscountTypeEnum::CART_CHECKOUT,
            'is_active' => true,
        ]);
        $coupon = DiscountCoupon::factory()->create([
            'discount_promotion_id' => $promotion->id,
            'code'                  => 'APPROVE10',
            'usage_limit'           => 10,
        ]);

        $customer = User::factory()->create();
        $order    = Order::factory()->create([
            'customer_id'                 => $customer->id,
            'status'                      => OrderStatusEnum::PENDING,
            'grand_total'                 => 90000,
            'full_value_grand_total'      => 100000,
            'applied_coupon_code'         => 'APPROVE10',
            'applied_cart_discounts_json' => [
                [
                    'promotion_id'   => $promotion->id,
                    'promotion_name' => 'Approved Order',
                    'applied_amount' => 10000,
                    'coupon_code'    => 'APPROVE10',
                ],
            ],
        ]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'price'    => 100000,
            'total'    => 90000,
            'status'   => App\Enums\Order\OrderItemStatusEnum::PENDING,
        ]);
        Payment::factory()->create([
            'order_id'    => $order->id,
            'customer_id' => $customer->id,
            'method'      => PaymentMethodEnum::MELLAT_GATEWAY,
            'amount'      => 90000,
            'status'      => PaymentStatusEnum::COMPLETED,
        ]);

        postJson("/api/v1/admin/orders/{$order->id}/approve")->assertOk();

        $order->refresh();
        expect($order->status)->toBe(OrderStatusEnum::COMPLETED);

        $promotion->refresh();
        $coupon->refresh();
        expect($promotion->total_usage_count)->toBe(1);
        expect($coupon->usage_count)->toBe(1);
    });
});
