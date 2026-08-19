<?php

declare(strict_types=1);

use App\Enums\Content\PublicationStatusEnum;
use App\Enums\Order\DiscountTypeEnum;
use App\Enums\Order\OrderStatusEnum;
use App\Enums\Payment\PaymentMethodEnum;
use App\Enums\PermissionEnum;
use App\Events\RefundCompletedEvent;
use App\Jobs\Provisioning\ProvisionBbbEnrollmentJob;
use App\Jobs\Provisioning\ProvisionImsEnrollmentJob;
use App\Jobs\Provisioning\ProvisionMoodleEnrollmentJob;
use App\Jobs\Provisioning\ProvisionSpotPlayerEnrollmentJob;
use App\Models\Course;
use App\Models\DiscountCoupon;
use App\Models\DiscountPromotion;
use App\Models\DiscountPromotionRule;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductDeliveryOption;
use App\Models\Staff;
use App\Models\Term;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Payment\MellatGatewayPaymentProcessor;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Support\Fakes\Payment\MockMultiStepProcessor;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;
use function Pest\Laravel\postJson;

uses(Tests\Support\Traits\AuthTestTrait::class);

beforeEach(function (): void {
    Queue::fake([
        ProvisionImsEnrollmentJob::class,
        ProvisionMoodleEnrollmentJob::class,
        ProvisionSpotPlayerEnrollmentJob::class,
        ProvisionBbbEnrollmentJob::class,
    ]);
});

function createLimitDeliveryOption(int $price = 100000): ProductDeliveryOption
{
    $vendor  = Vendor::factory()->create();
    $term    = Term::factory()->create();
    $course  = Course::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]);
    $product = Product::factory()->create([
        'vendor_id'        => $vendor->id,
        'term_id'          => $term->id,
        'productable_id'   => $course->id,
        'productable_type' => 'course',
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

/**
 * @return array{0: DiscountPromotion, 1: Illuminate\Support\Collection<int, DiscountCoupon>}
 */
function createLimitPromotion(string $name, ?int $perCustomerLimit, array $codes): array
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
        'usage_limit_per_customer'         => $perCustomerLimit,
    ]);
    DiscountPromotionRule::create([
        'discount_promotion_id' => $promotion->id,
        'type'                  => 'action',
        'handler'               => 'apply_percentage_off',
        'configuration'         => ['percentage' => 10],
    ]);

    $coupons = collect($codes)->map(fn (string $code): DiscountCoupon => DiscountCoupon::create([
        'discount_promotion_id' => $promotion->id,
        'code'                  => $code,
        'usage_limit'           => 100,
    ]));

    return [$promotion, $coupons];
}

function addItemAndApplyCoupon(ProductDeliveryOption $option, string $couponCode): void
{
    postJson(route('api.v1.shop.cart.items.store'), [
        'product_delivery_option_uuid' => $option->uuid,
        'quantity'                     => 1,
    ])->assertOk();
    postJson(route('api.v1.shop.cart.coupon.apply'), ['coupon_code' => $couponCode])->assertOk();
}

function checkoutWithWallet(): Illuminate\Testing\TestResponse
{
    return postJson(route('api.v1.shop.checkout'), [
        'payment_method' => PaymentMethodEnum::WALLET->value,
    ]);
}

function checkoutPendingWithGateway(): Illuminate\Testing\TestResponse
{
    test()->instance(MellatGatewayPaymentProcessor::class, new MockMultiStepProcessor());

    return postJson(route('api.v1.shop.checkout'), [
        'payment_method' => PaymentMethodEnum::MELLAT_GATEWAY->value,
    ]);
}

describe('per-customer coupon limit — shop checkout', function (): void {
    test('a completed order consumes the slot and a second checkout is rejected', function (): void {
        $firstOption           = createLimitDeliveryOption();
        $secondOption          = createLimitDeliveryOption();
        [$promotion, $coupons] = createLimitPromotion('Completed Consumes', 1, ['ONLYONCE']);
        $coupon                = $coupons->first();

        $customer = User::factory()->create();
        $customer->wallet->update(['balance' => 500000]);
        $this->customer($customer);

        // First checkout completes instantly (wallet) → slot consumed.
        addItemAndApplyCoupon($firstOption, $coupon->code);
        checkoutWithWallet()->assertCreated();

        assertDatabaseHas('discount_promotion_usages', [
            'discount_promotion_id' => $promotion->id,
            'customer_id'           => $customer->id,
        ]);

        // Second checkout with the same coupon is rejected at the coupon check.
        addItemAndApplyCoupon($secondOption, $coupon->code);
        $response = checkoutWithWallet()->assertStatus(422);
        $response->assertJsonValidationErrors(['coupon']);
        expect($response->json('errors.coupon.0'))->toBe(__('messages.checkout.coupon_usage_limit_reached'));
    });

    test('a pending order holds the slot and the rejection names the blocking order', function (): void {
        $option                = createLimitDeliveryOption();
        [$promotion, $coupons] = createLimitPromotion('Pending Holds', 1, ['HOLDIT']);
        $coupon                = $coupons->first();

        $customer = User::factory()->create();
        $this->customer($customer);

        // Gateway checkout leaves the order PENDING — the slot is held.
        addItemAndApplyCoupon($option, $coupon->code);
        $firstResponse           = checkoutPendingWithGateway()->assertCreated();
        $pendingOrderIncrementId = $firstResponse->json('data.order.increment_id');

        assertDatabaseHas('discount_promotion_usages', [
            'discount_promotion_id' => $promotion->id,
            'customer_id'           => $customer->id,
        ]);

        // Second checkout is rejected and the message names the pending order.
        addItemAndApplyCoupon($option, $coupon->code);
        $response = checkoutPendingWithGateway()->assertStatus(422);
        $response->assertJsonValidationErrors(['coupon']);
        expect($response->json('errors.coupon.0'))->toContain($pendingOrderIncrementId);
    });

    test('cancelling a pending order releases the slot', function (): void {
        $option                = createLimitDeliveryOption();
        [$promotion, $coupons] = createLimitPromotion('Cancel Releases', 1, ['CANCELIT']);
        $coupon                = $coupons->first();

        $customer = User::factory()->create();
        $this->customer($customer);

        addItemAndApplyCoupon($option, $coupon->code);
        $response    = checkoutPendingWithGateway()->assertCreated();
        $incrementId = $response->json('data.order.increment_id');

        // Cancel the pending order → slot released.
        postJson(route('api.v1.shop.student.orders.cancel', $incrementId))->assertOk();

        assertDatabaseMissing('discount_promotion_usages', [
            'discount_promotion_id' => $promotion->id,
            'customer_id'           => $customer->id,
        ]);

        // A fresh checkout with the same coupon now succeeds.
        addItemAndApplyCoupon($option, $coupon->code);
        checkoutPendingWithGateway()->assertCreated();
    });

    test('a refunded order still consumes the slot', function (): void {
        $option                = createLimitDeliveryOption();
        [$promotion, $coupons] = createLimitPromotion('Refund Keeps', 1, ['REFUNDED']);
        $coupon                = $coupons->first();

        $customer = User::factory()->create();
        $customer->wallet->update(['balance' => 500000]);
        $this->customer($customer);

        // Complete the order with the coupon (wallet) → slot consumed.
        addItemAndApplyCoupon($option, $coupon->code);
        $response = checkoutWithWallet()->assertCreated();
        $orderId  = $response->json('data.order.id');

        // Refund the whole order via admin (skip gateway) → order becomes REFUNDED.
        Event::fake([RefundCompletedEvent::class]);
        $this->user = Staff::factory()->create();
        $this->authorized_user([PermissionEnum::REFUND_CREATE->value, PermissionEnum::REFUND_SKIP_GATEWAY->value]);
        postJson(route('api.v1.admin.orders.refund', ['order' => $orderId]), [
            'skip_gateway' => true,
        ])->assertCreated();

        assertDatabaseHas('orders', [
            'id'     => $orderId,
            'status' => OrderStatusEnum::REFUNDED->value,
        ]);

        // The refund did NOT release the slot — usage row still present.
        assertDatabaseHas('discount_promotion_usages', [
            'discount_promotion_id' => $promotion->id,
            'customer_id'           => $customer->id,
        ]);

        // A second checkout with the same coupon is still rejected.
        $this->customer($customer);
        addItemAndApplyCoupon($option, $coupon->code);
        $response = checkoutWithWallet()->assertStatus(422);
        $response->assertJsonValidationErrors(['coupon']);
        expect($response->json('errors.coupon.0'))->toBe(__('messages.checkout.coupon_usage_limit_reached'));
    });

    test('the limit pools across all coupon codes of a promotion', function (): void {
        $firstOption           = createLimitDeliveryOption();
        $secondOption          = createLimitDeliveryOption();
        [$promotion, $coupons] = createLimitPromotion('Pooled Limit', 1, ['CODEONE', 'CODETWO']);
        $couponA               = $coupons[0];
        $couponB               = $coupons[1];

        $customer = User::factory()->create();
        $customer->wallet->update(['balance' => 500000]);
        $this->customer($customer);

        // Complete an order with coupon A.
        addItemAndApplyCoupon($firstOption, $couponA->code);
        checkoutWithWallet()->assertCreated();

        // Coupon B belongs to the same promotion → the limit still applies.
        addItemAndApplyCoupon($secondOption, $couponB->code);
        $response = checkoutWithWallet()->assertStatus(422);
        $response->assertJsonValidationErrors(['coupon']);
        expect($response->json('errors.coupon.0'))->toBe(__('messages.checkout.coupon_usage_limit_reached'));

        assertDatabaseCount('discount_promotion_usages', 1);
    });

    test('a promotion without a per-customer limit allows repeated use', function (): void {
        $firstOption           = createLimitDeliveryOption();
        $secondOption          = createLimitDeliveryOption();
        [$promotion, $coupons] = createLimitPromotion('Unlimited', null, ['UNLIMITED']);
        $coupon                = $coupons->first();

        $customer = User::factory()->create();
        $customer->wallet->update(['balance' => 500000]);
        $this->customer($customer);

        addItemAndApplyCoupon($firstOption, $coupon->code);
        checkoutWithWallet()->assertCreated();

        addItemAndApplyCoupon($secondOption, $coupon->code);
        checkoutWithWallet()->assertCreated();

        assertDatabaseCount('discount_promotion_usages', 2);
    });
});

describe('per-customer coupon limit — admin order creation', function (): void {
    test('admin order creation enforces the per-customer limit', function (): void {
        $firstOption           = createLimitDeliveryOption();
        $secondOption          = createLimitDeliveryOption();
        [$promotion, $coupons] = createLimitPromotion('Admin Limit', 1, ['ADMIN1']);
        $coupon                = $coupons->first();

        $customer = User::factory()->create();
        $customer->wallet->update(['balance' => 500000]);
        $this->customer($customer);

        // Customer already consumed the slot via shop checkout.
        addItemAndApplyCoupon($firstOption, $coupon->code);
        checkoutWithWallet()->assertCreated();

        // Admin tries to create an order with the same coupon for the same customer.
        $this->user = Staff::factory()->create();
        $this->authorized_user([PermissionEnum::ORDER_CREATE->value]);
        $response = $this->postJson(route('api.v1.admin.orders.store'), [
            'status'              => OrderStatusEnum::PENDING->value,
            'customer_id'         => $customer->id,
            'applied_coupon_code' => $coupon->code,
            'items'               => [
                [
                    'product_delivery_option_id' => $secondOption->id,
                    'payment_type'               => 'full_payment',
                    'qty_ordered'                => 1,
                ],
            ],
        ])->assertStatus(422);

        $response->assertJsonValidationErrors(['coupon']);
        expect($response->json('errors.coupon.0'))->toBe(__('messages.checkout.coupon_usage_limit_reached'));
    });

    test('admin flipping a pending order to failed releases the slot', function (): void {
        $option                = createLimitDeliveryOption();
        [$promotion, $coupons] = createLimitPromotion('Fail Releases', 1, ['FAILIT']);
        $coupon                = $coupons->first();

        $customer = User::factory()->create();
        $this->customer($customer);

        addItemAndApplyCoupon($option, $coupon->code);
        $response = checkoutPendingWithGateway()->assertCreated();
        $orderId  = $response->json('data.order.id');

        assertDatabaseHas('discount_promotion_usages', [
            'discount_promotion_id' => $promotion->id,
            'customer_id'           => $customer->id,
        ]);

        // Admin flips the order to FAILED → slot released.
        $this->user = Staff::factory()->create();
        $this->authorized_user([PermissionEnum::ORDER_UPDATE->value]);
        $this->putJson(route('api.v1.admin.orders.update', ['order' => $orderId]), [
            'status' => OrderStatusEnum::FAILED->value,
        ])->assertOk();

        assertDatabaseMissing('discount_promotion_usages', [
            'discount_promotion_id' => $promotion->id,
            'customer_id'           => $customer->id,
        ]);

        // The customer can checkout with the coupon again.
        $this->customer($customer);
        addItemAndApplyCoupon($option, $coupon->code);
        checkoutPendingWithGateway()->assertCreated();
    });
});
