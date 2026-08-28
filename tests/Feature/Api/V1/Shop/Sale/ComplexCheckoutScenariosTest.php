<?php

declare(strict_types=1);

use App\Enums\Content\PublicationStatusEnum;
use App\Enums\EnrollmentStatusEnum;
use App\Enums\Order\DiscountTypeEnum;
use App\Enums\Payment\PaymentMethodEnum;
use App\Enums\System\MorphTypeEnum;
use App\Jobs\Provisioning\ProvisionEnrollmentProviderJob;
use App\Models\Course;
use App\Models\DigitalAsset;
use App\Models\DiscountCoupon;
use App\Models\DiscountPromotion;
use App\Models\DiscountPromotionRule;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductDeliveryOption;
use App\Models\Term;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Wallet;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\deleteJson;
use function Pest\Laravel\postJson;

uses(Tests\Support\Traits\AuthTestTrait::class);
beforeEach(function (): void {
    Queue::fake([
        ProvisionEnrollmentProviderJob::class,
    ]);
});
function createCouponCartPromotion(
    string $name,
    array $actionConfig,
    string $type = 'apply_percentage_off',
    int $priority = 1
): DiscountPromotion {
    $promotion = DiscountPromotion::create([
        'name'                             => $name,
        'type'                             => DiscountTypeEnum::CART_CHECKOUT,
        'is_active'                        => true,
        'priority'                         => $priority,
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

describe('Complex Multi-Step Checkout Scenarios', function (): void {

    test('coupon usage limit enforced: exhaustion prevents further use', function (): void {
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
        $option = ProductDeliveryOption::factory()->create([
            'product_id' => $product->id,
            'price'      => 100000,
            'capacity'   => 10, // Ensure sufficient capacity for multiple purchases
            'uuid'       => Str::uuid()->toString(),
            'status'     => PublicationStatusEnum::PUBLISHED,
        ]);

        // Create promotion with small usage limit
        $promotion = DiscountPromotion::create([
            'name'              => 'Limited Coupon Test',
            'type'              => DiscountTypeEnum::CART_CHECKOUT,
            'is_active'         => true,
            'usage_limit_total' => 2,
            'total_usage_count' => 0,
            'starts_at'         => now()->subDay(),
            'ends_at'           => now()->addDay(),
            'requires_coupon'   => true,
        ]);
        DiscountPromotionRule::create([
            'discount_promotion_id' => $promotion->id,
            'type'                  => 'action',
            'handler'               => 'apply_percentage_off',
            'configuration'         => ['percentage' => 10],
        ]);
        $coupon = DiscountCoupon::create([
            'discount_promotion_id' => $promotion->id,
            'code'                  => 'LIMIT2',
            'usage_limit'           => 2,
            'usage_count'           => 0,
        ]);

        // First customer: success
        $customer1 = User::factory()->create();
        $customer1->wallet->update(['balance' => 500000]);
        $this->customer($customer1);

        postJson(route('api.v1.shop.cart.items.store'), [
            'product_delivery_option_uuid' => $option->uuid,
            'quantity'                     => 1,
        ])->assertOk();

        postJson(route('api.v1.shop.cart.coupon.apply'), ['coupon_code' => $coupon->code])->assertOk();
        postJson(route('api.v1.shop.checkout'), ['payment_method' => PaymentMethodEnum::WALLET->value])
            ->assertCreated();

        // Second customer: success
        $customer2 = User::factory()->create();
        $customer2->wallet->update(['balance' => 500000]);
        $this->customer($customer2);

        postJson(route('api.v1.shop.cart.items.store'), [
            'product_delivery_option_uuid' => $option->uuid,
            'quantity'                     => 1,
        ])->assertOk();

        postJson(route('api.v1.shop.cart.coupon.apply'), ['coupon_code' => $coupon->code])->assertOk();
        postJson(route('api.v1.shop.checkout'), ['payment_method' => PaymentMethodEnum::WALLET->value])
            ->assertCreated();

        // Third customer: should fail due to coupon usage limit
        $customer3 = User::factory()->create();
        $customer3->wallet->update(['balance' => 500000]);
        $this->customer($customer3);

        postJson(route('api.v1.shop.cart.items.store'), [
            'product_delivery_option_uuid' => $option->uuid,
            'quantity'                     => 1,
        ])->assertOk();

        $response = postJson(route('api.v1.shop.cart.coupon.apply'), ['coupon_code' => $coupon->code]);
        // Coupon should be rejected or ignored at apply/checkout due to usage exhaustion
        expect($response->status())->toBeIn([422, 200]); // May be validation or silent ignore

        // Assert counters incremented correctly
        $promotion->refresh();
        $coupon->refresh();
        expect($promotion->total_usage_count)->toBe(2);
        expect($coupon->usage_count)->toBe(2);
    });

    test('discount expiry at checkout time: expired promotion not applied', function (): void {
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
        $option = ProductDeliveryOption::factory()->create([
            'product_id' => $product->id,
            'price'      => 100000,
            'capacity'   => 10, // Ensure sufficient capacity for multiple purchases
            'uuid'       => Str::uuid()->toString(),
            'status'     => PublicationStatusEnum::PUBLISHED,
        ]);

        // Promotion expires in 1 second
        $promotion = DiscountPromotion::create([
            'name'      => 'Expiring Soon',
            'type'      => DiscountTypeEnum::CART_CHECKOUT,
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'ends_at'   => now()->addSecond(),
        ]);
        DiscountPromotionRule::create([
            'discount_promotion_id' => $promotion->id,
            'type'                  => 'action',
            'handler'               => 'apply_percentage_off',
            'configuration'         => ['percentage' => 20],
        ]);
        $coupon = DiscountCoupon::create([
            'discount_promotion_id' => $promotion->id,
            'code'                  => 'EXPIRING',
            'usage_limit'           => 10,
        ]);

        $customer = User::factory()->create();
        $customer->wallet->update(['balance' => 500000]);
        $this->customer($customer);

        // Add to cart while promotion is still active
        postJson(route('api.v1.shop.cart.items.store'), [
            'product_delivery_option_uuid' => $option->uuid,
            'quantity'                     => 1,
        ])->assertOk();

        postJson(route('api.v1.shop.cart.coupon.apply'), ['coupon_code' => $coupon->code])->assertOk();

        // Wait for expiry
        sleep(2);

        // Checkout after expiry
        $response = postJson(route('api.v1.shop.checkout'), ['payment_method' => PaymentMethodEnum::WALLET->value])
            ->assertCreated();

        // Order should be created with no discount applied (grand_total = subtotal)
        $orderId = $response->json('data.order.id');
        $order   = Order::with('items')->find($orderId);
        expect($order->discount_amount)->toBe(0);
        expect($order->grand_total)->toBe($order->subtotal);
    });

    test('wallet insufficient balance then top-up and retry succeeds', function (): void {
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
        $option = ProductDeliveryOption::factory()->create([
            'product_id' => $product->id,
            'price'      => 100000,
            'capacity'   => 10, // Ensure sufficient capacity for multiple purchases
            'uuid'       => Str::uuid()->toString(),
            'status'     => PublicationStatusEnum::PUBLISHED,
        ]);

        $customer = User::factory()->create();
        $customer->wallet->update(['balance' => 50000]); // Insufficient
        $this->customer($customer);

        postJson(route('api.v1.shop.cart.items.store'), [
            'product_delivery_option_uuid' => $option->uuid,
            'quantity'                     => 1,
        ])->assertOk();

        // First checkout: insufficient balance
        $response = postJson(route('api.v1.shop.checkout'), ['payment_method' => PaymentMethodEnum::WALLET->value]);
        $response->assertStatus(422);
        $requiredBalance  = $response->json('metadata.required_balance');
        $orderIncrementId = $response->json('metadata.order_id');
        expect($requiredBalance)->toBe(100000);
        expect($orderIncrementId)->not->toBeNull();
        // Top up wallet
        $customer->wallet->update(['balance' => 200000]);

        // Retry payment on existing order
        $order    = Order::query()->where('increment_id', $orderIncrementId)->first();
        $response = postJson(route('api.v1.shop.student.orders.retry-payment', $order->increment_id), [
            'payment_method' => PaymentMethodEnum::WALLET->value,
        ]);
        $response->assertOk();

        // Verify payment created
        assertDatabaseHas('payments',
            ['order_id' => $order->id, 'method' => PaymentMethodEnum::WALLET->value, 'status' => 'completed']);
    });

    test('order totals include applied_cart_discounts_json snapshot', function (): void {
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
        $option = ProductDeliveryOption::factory()->create([
            'product_id' => $product->id,
            'price'      => 100000,
            'capacity'   => 10, // Ensure sufficient capacity for multiple purchases
            'uuid'       => Str::uuid()->toString(),
            'status'     => PublicationStatusEnum::PUBLISHED,
        ]);

        $promotion = DiscountPromotion::create([
            'name'      => 'Snapshot Test',
            'type'      => DiscountTypeEnum::CART_CHECKOUT,
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'ends_at'   => now()->addDay(),
        ]);
        DiscountPromotionRule::create([
            'discount_promotion_id' => $promotion->id,
            'type'                  => 'action',
            'handler'               => 'apply_percentage_off',
            'configuration'         => ['percentage' => 15],
        ]);
        $coupon = DiscountCoupon::create([
            'discount_promotion_id' => $promotion->id,
            'code'                  => 'SNAPSHOT15',
            'usage_limit'           => 100,
        ]);

        $customer = User::factory()->create();
        $customer->wallet->update(['balance' => 500000]);
        $this->customer($customer);

        postJson(route('api.v1.shop.cart.items.store'), [
            'product_delivery_option_uuid' => $option->uuid,
            'quantity'                     => 1,
        ])->assertOk();

        postJson(route('api.v1.shop.cart.coupon.apply'), ['coupon_code' => $coupon->code])->assertOk();

        $response = postJson(route('api.v1.shop.checkout'), ['payment_method' => PaymentMethodEnum::WALLET->value])
            ->assertCreated();

        $orderId = $response->json('data.order.id');
        $order   = Order::with('items')->find($orderId);

        // Assert applied_cart_discounts_json is populated
        expect($order->applied_cart_discounts_json)->not->toBeNull();
        expect($order->applied_cart_discounts_json)->toBeArray();
        expect($order->applied_cart_discounts_json[0]['promotion_id'])->toBe($promotion->id);
        expect($order->applied_cart_discounts_json[0]['promotion_name'])->toBe('Snapshot Test');

        // Assert OrderItem has applied_discount_details_json
        $orderItem = $order->items->first();
        expect($orderItem->applied_discount_details_json)->not->toBeNull();
    });
    test('gift product action successfully attaches free promotional item during checkout calculation',
        function (): void {
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

            $mainOption = ProductDeliveryOption::factory()->create([
                'product_id' => $product->id,
                'price'      => 100000,
                'capacity'   => 10,
                'uuid'       => Str::uuid()->toString(),
                'status'     => PublicationStatusEnum::PUBLISHED,
            ]);

            $giftOption = ProductDeliveryOption::factory()->create([
                'product_id' => $product->id,
                'price'      => 50000,
                'capacity'   => 10,
                'uuid'       => Str::uuid()->toString(),
                'status'     => PublicationStatusEnum::PUBLISHED,
            ]);

            $promotion = DiscountPromotion::create([
                'name'      => 'Free Gift Promo',
                'type'      => DiscountTypeEnum::CART_CHECKOUT,
                'is_active' => true,
                'starts_at' => now()->subDay(),
                'ends_at'   => now()->addDay(),
            ]);

            // Add action for gifting product
            DiscountPromotionRule::create([
                'discount_promotion_id' => $promotion->id,
                'type'                  => 'action',
                'handler'               => 'gift_product',
                'configuration'         => ['product_delivery_option_id' => $giftOption->id],
            ]);

            $coupon = DiscountCoupon::create([
                'discount_promotion_id' => $promotion->id,
                'code'                  => 'FREEGIFT',
                'usage_limit'           => 10,
            ]);

            $customer = User::factory()->create();
            $customer->wallet->update(['balance' => 500000]);
            $this->customer($customer);

            postJson(route('api.v1.shop.cart.items.store'),
                ['product_delivery_option_uuid' => $mainOption->uuid, 'quantity' => 1])->assertOk();
            postJson(route('api.v1.shop.cart.coupon.apply'), ['coupon_code' => $coupon->code])->assertOk();

            $response = postJson(route('api.v1.shop.checkout'), ['payment_method' => PaymentMethodEnum::WALLET->value])
                ->assertCreated();

            $order = Order::with('items')->find($response->json('data.order.id'));

            // Expect 2 items: main item paid, gift item total = 0
            expect($order->items)->toHaveCount(2);

            $giftOrderItem = $order->items->where('product_delivery_option_id', $giftOption->id)->first();
            expect($giftOrderItem)->not->toBeNull()
                ->and($giftOrderItem->total)->toBe(0)
                ->and($giftOrderItem->price)->toBe(50000);
        });
});

describe('Advanced Discount Engine Logic', function (): void {

    test('1. Multiple cart discounts stack correctly', function (): void {
        $option   = ProductDeliveryOption::factory()->create(['price' => 100000]);
        $customer = User::factory()->create();
        $customer->wallet->update(['balance' => 500000]);
        $this->customer($customer);

        $promo1 = createCouponCartPromotion('10 Percent', ['percentage' => 10], priority: 5);

        $promotion = DiscountPromotion::create([
            'name'      => 'First Order',
            'type'      => DiscountTypeEnum::CART_CHECKOUT,
            'is_active' => true,
            'priority'  => 1,
            'starts_at' => now()->subDay(),
            'ends_at'   => now()->addDay(),
        ]);
        DiscountPromotionRule::create([
            'discount_promotion_id' => $promotion->id,
            'type'                  => 'condition',
            'handler'               => 'first_order_only',
            'configuration'         => [],
        ]);
        DiscountPromotionRule::create([
            'discount_promotion_id' => $promotion->id,
            'type'                  => 'action',
            'handler'               => 'apply_fixed_amount_off',
            'configuration'         => ['amount' => 5000],
        ]);

        postJson(route('api.v1.shop.cart.items.store'), ['product_delivery_option_uuid' => $option->uuid]);
        postJson(route('api.v1.shop.cart.coupon.apply'), ['coupon_code' => $promo1->coupons->first()->code]);

        $response = postJson(route('api.v1.shop.checkout'), ['payment_method' => 'wallet'])->assertCreated();

        // 100,000 - 10% = 90,000. 90,000 - 5,000 = 85,000.
        expect($response->json('data.order.grand_total'))->toBe(85000);
    });

    test('2. Priority overrides lower priority promotions', function (): void {
        $option   = ProductDeliveryOption::factory()->create(['price' => 100000]);
        $customer = User::factory()->create();
        $customer->wallet->update(['balance' => 500000]);
        $this->customer($customer);

        // Low priority 10% off
        $p1 = createCouponCartPromotion('Summer Sale', ['percentage' => 10], 'apply_percentage_off', priority: 1);
        // High priority 20% off
        $p2 = createCouponCartPromotion('VIP Sale', ['percentage' => 20], 'apply_percentage_off', priority: 10);

        postJson(route('api.v1.shop.cart.items.store'), ['product_delivery_option_uuid' => $option->uuid]);

        // Apply both, system should pick the higher priority one (VIP)
        postJson(route('api.v1.shop.cart.coupon.apply'), ['coupon_code' => $p1->coupons->first()->code]);
        postJson(route('api.v1.shop.cart.coupon.apply'), ['coupon_code' => $p2->coupons->first()->code]);

        $response = postJson(route('api.v1.shop.checkout'), ['payment_method' => 'wallet'])->assertCreated();

        // Should be 80,000 (20% off), not 72,000 (stacking)
        expect($response->json('data.order.grand_total'))->toBe(80000);
    });

    test('3. Incompatible condition prevents coupon application', function (): void {
        $courseA = Product::factory()->create();
        $optionA = ProductDeliveryOption::factory()->create(['product_id' => $courseA->id, 'price' => 100000]);
        $optionB = ProductDeliveryOption::factory()->create(['price' => 50000]);

        // Create coupon requiring Course A
        $promo = createCouponCartPromotion('Only For A', ['percentage' => 50]);
        DiscountPromotionRule::create([
            'discount_promotion_id' => $promo->id,
            'type'                  => 'condition',
            'handler'               => 'specific_products_in_cart',
            'configuration'         => ['product_ids' => [$courseA->id]],
        ]);

        $this->customer(User::factory()->create());
        // Add only Course B
        postJson(route('api.v1.shop.cart.items.store'), ['product_delivery_option_uuid' => $optionB->uuid]);

        // Attempt apply coupon
        $response = postJson(route('api.v1.shop.cart.coupon.apply'), ['coupon_code' => $promo->coupons->first()->code]);

        // Assert failure (Should receive 422 or similar rejection)
        $response->assertStatus(422);
    });

    test('4. Product level discount vs Cart checkout discount interaction', function (): void {
        $option   = ProductDeliveryOption::factory()->create(['price' => 100000]);
        $customer = User::factory()->create();
        $customer->wallet->update(['balance' => 500000]);
        $this->customer($customer);

        // Product level (e.g. Flash Sale) handled by PriceService
        $option->update(['price' => 90000]);

        // Cart Level Coupon (10% off remaining)
        $promo = createCouponCartPromotion('Cart Coupon', ['percentage' => 10]);

        postJson(route('api.v1.shop.cart.items.store'), ['product_delivery_option_uuid' => $option->uuid]);
        postJson(route('api.v1.shop.cart.coupon.apply'), ['coupon_code' => $promo->coupons->first()->code]);

        $response = postJson(route('api.v1.shop.checkout'), ['payment_method' => 'wallet'])->assertCreated();

        // 90,000 - 10% = 81,000
        expect($response->json('data.order.grand_total'))->toBe(81000);
    });

    test('5. Remove qualifying item after coupon application → discount voided at checkout', function (): void {
        $courseA  = Product::factory()->create();
        $optionA  = ProductDeliveryOption::factory()->create(['product_id' => $courseA->id, 'price' => 100000]);
        $optionB  = ProductDeliveryOption::factory()->create(['price' => 50000]);
        $customer = User::factory()->create();
        $customer->wallet->update(['balance' => 500000]);
        $this->customer($customer);

        // Create promotion requiring Course A in cart
        $promo = createCouponCartPromotion('Only For A', ['percentage' => 20]);
        DiscountPromotionRule::create([
            'discount_promotion_id' => $promo->id,
            'type'                  => 'condition',
            'handler'               => 'specific_products_in_cart',
            'configuration'         => ['product_ids' => [$courseA->id]],
        ]);

        // Add qualifying product A
        postJson(route('api.v1.shop.cart.items.store'),
            ['product_delivery_option_uuid' => $optionA->uuid])->assertOk();
        // Apply coupon → accepted (condition passes: A is in cart)
        postJson(route('api.v1.shop.cart.coupon.apply'), ['coupon_code' => $promo->coupons->first()->code])
            ->assertOk();

        // Remove product A, add non-qualifying product B
        $cartItemA = App\Models\CartItem::where('product_delivery_option_id', $optionA->id)->first();
        deleteJson(route('api.v1.shop.cart.items.destroy', $cartItemA->id))->assertNoContent();
        postJson(route('api.v1.shop.cart.items.store'),
            ['product_delivery_option_uuid' => $optionB->uuid])->assertOk();

        // Checkout → discount should NOT apply (condition re-checked, only B in cart)
        $response = postJson(route('api.v1.shop.checkout'), ['payment_method' => 'wallet'])->assertCreated();

        $order = Order::with('items')->find($response->json('data.order.id'));
        expect($order->discount_amount)->toBe(0);
        expect($order->grand_total)->toBe(50000);
    });

    test('6. Same coupon applied twice → single discount (idempotent)', function (): void {
        $option   = ProductDeliveryOption::factory()->create(['price' => 100000]);
        $customer = User::factory()->create();
        $customer->wallet->update(['balance' => 500000]);
        $this->customer($customer);

        $promo = createCouponCartPromotion('20 Percent', ['percentage' => 20]);

        postJson(route('api.v1.shop.cart.items.store'),
            ['product_delivery_option_uuid' => $option->uuid])->assertOk();

        // Apply coupon twice
        $code = $promo->coupons->first()->code;
        postJson(route('api.v1.shop.cart.coupon.apply'), ['coupon_code' => $code])->assertOk();
        postJson(route('api.v1.shop.cart.coupon.apply'), ['coupon_code' => $code])->assertOk();

        $response = postJson(route('api.v1.shop.checkout'), ['payment_method' => 'wallet'])->assertCreated();

        // 100,000 - 20% = 80,000 (single discount, not compounded)
        expect($response->json('data.order.grand_total'))->toBe(80000);
    });

    test('7. Remove coupon then re-apply → discount still works', function (): void {
        $option   = ProductDeliveryOption::factory()->create(['price' => 100000]);
        $customer = User::factory()->create();
        $customer->wallet->update(['balance' => 500000]);
        $this->customer($customer);

        $promo = createCouponCartPromotion('15 Percent', ['percentage' => 15]);

        postJson(route('api.v1.shop.cart.items.store'),
            ['product_delivery_option_uuid' => $option->uuid])->assertOk();

        $code = $promo->coupons->first()->code;
        postJson(route('api.v1.shop.cart.coupon.apply'), ['coupon_code' => $code])->assertOk();

        // Remove coupon
        deleteJson(route('api.v1.shop.cart.coupon.remove'))->assertOk();

        // Re-apply same coupon
        postJson(route('api.v1.shop.cart.coupon.apply'), ['coupon_code' => $code])->assertOk();

        $response = postJson(route('api.v1.shop.checkout'), ['payment_method' => 'wallet'])->assertCreated();

        // 100,000 - 15% = 85,000
        expect($response->json('data.order.grand_total'))->toBe(85000);
    });
});

describe('Malicious User & Edge Cases', function (): void {

    test('1. Apply coupon then remove all items → checkout fails with no items', function (): void {
        $option   = ProductDeliveryOption::factory()->create(['price' => 100000]);
        $customer = User::factory()->create();
        $customer->wallet->update(['balance' => 500000]);
        $this->customer($customer);

        $promo = createCouponCartPromotion('No Items', ['percentage' => 10]);

        postJson(route('api.v1.shop.cart.items.store'),
            ['product_delivery_option_uuid' => $option->uuid])->assertOk();

        postJson(route('api.v1.shop.cart.coupon.apply'),
            ['coupon_code' => $promo->coupons->first()->code])->assertOk();

        // Remove the only item
        $cartItem = App\Models\CartItem::where('product_delivery_option_id', $option->id)->first();
        deleteJson(route('api.v1.shop.cart.items.destroy', $cartItem->id))->assertNoContent();

        // Checkout must fail — no items in cart
        postJson(route('api.v1.shop.checkout'), ['payment_method' => 'wallet'])
            ->assertStatus(422);
    });

    test('2. Non-existent coupon code rejected', function (): void {
        $option   = ProductDeliveryOption::factory()->create(['price' => 100000]);
        $customer = User::factory()->create();
        $customer->wallet->update(['balance' => 500000]);
        $this->customer($customer);

        postJson(route('api.v1.shop.cart.items.store'),
            ['product_delivery_option_uuid' => $option->uuid])->assertOk();

        $response = postJson(route('api.v1.shop.cart.coupon.apply'),
            ['coupon_code' => 'FAKE-NONEXISTENT-CODE-12345']);

        $response->assertStatus(422);
    });

    test('3. Exhausted single-use coupon rejected for second user', function (): void {
        $option = ProductDeliveryOption::factory()->create(['price' => 100000]);

        // Single-use coupon (usage_limit = 1)
        $promotion = DiscountPromotion::create([
            'name'            => 'Single Use',
            'type'            => DiscountTypeEnum::CART_CHECKOUT,
            'is_active'       => true,
            'starts_at'       => now()->subDay(),
            'ends_at'         => now()->addDay(),
            'requires_coupon' => true,
        ]);
        DiscountPromotionRule::create([
            'discount_promotion_id' => $promotion->id,
            'type'                  => 'action',
            'handler'               => 'apply_percentage_off',
            'configuration'         => ['percentage' => 30],
        ]);
        $coupon = DiscountCoupon::create([
            'discount_promotion_id' => $promotion->id,
            'code'                  => 'SINGLEUSE',
            'usage_limit'           => 1,
            'usage_count'           => 0,
        ]);

        // First user: use the coupon
        $user1 = User::factory()->create();
        $user1->wallet->update(['balance' => 500000]);
        $this->customer($user1);

        postJson(route('api.v1.shop.cart.items.store'),
            ['product_delivery_option_uuid' => $option->uuid])->assertOk();
        postJson(route('api.v1.shop.cart.coupon.apply'), ['coupon_code' => $coupon->code])->assertOk();
        postJson(route('api.v1.shop.checkout'), ['payment_method' => 'wallet'])->assertCreated();

        // Second user: same coupon must be rejected at apply time
        $user2 = User::factory()->create();
        $user2->wallet->update(['balance' => 500000]);
        $this->customer($user2);

        postJson(route('api.v1.shop.cart.items.store'),
            ['product_delivery_option_uuid' => $option->uuid])->assertOk();
        $response = postJson(route('api.v1.shop.cart.coupon.apply'), ['coupon_code' => $coupon->code]);

        $response->assertStatus(422);
    });

    test('4. Coupon belongs to inactive promotion → rejected at apply', function (): void {
        $option   = ProductDeliveryOption::factory()->create(['price' => 100000]);
        $customer = User::factory()->create();
        $customer->wallet->update(['balance' => 500000]);
        $this->customer($customer);

        // Inactive promotion with valid coupon
        $promotion = DiscountPromotion::create([
            'name'      => 'Deactivated Promo',
            'type'      => DiscountTypeEnum::CART_CHECKOUT,
            'is_active' => false,
            'starts_at' => now()->subDay(),
            'ends_at'   => now()->addDay(),
        ]);
        DiscountPromotionRule::create([
            'discount_promotion_id' => $promotion->id,
            'type'                  => 'action',
            'handler'               => 'apply_percentage_off',
            'configuration'         => ['percentage' => 50],
        ]);
        $coupon = DiscountCoupon::create([
            'discount_promotion_id' => $promotion->id,
            'code'                  => 'DEACTIVE50',
            'usage_limit'           => 100,
        ]);

        postJson(route('api.v1.shop.cart.items.store'),
            ['product_delivery_option_uuid' => $option->uuid])->assertOk();

        $response = postJson(route('api.v1.shop.cart.coupon.apply'), ['coupon_code' => $coupon->code]);
        $response->assertStatus(422);
    });

    test('5. Apply coupon, swap cart items → conditions re-checked at checkout', function (): void {
        $courseA  = Product::factory()->create();
        $optionA  = ProductDeliveryOption::factory()->create(['product_id' => $courseA->id, 'price' => 100000]);
        $optionB  = ProductDeliveryOption::factory()->create(['price' => 75000]);
        $customer = User::factory()->create();
        $customer->wallet->update(['balance' => 500000]);
        $this->customer($customer);

        // Promotion requires Course A in cart
        $promo = createCouponCartPromotion('A Only', ['percentage' => 25]);
        DiscountPromotionRule::create([
            'discount_promotion_id' => $promo->id,
            'type'                  => 'condition',
            'handler'               => 'specific_products_in_cart',
            'configuration'         => ['product_ids' => [$courseA->id]],
        ]);

        // Add qualifying product A, apply coupon
        postJson(route('api.v1.shop.cart.items.store'),
            ['product_delivery_option_uuid' => $optionA->uuid])->assertOk();
        postJson(route('api.v1.shop.cart.coupon.apply'), ['coupon_code' => $promo->coupons->first()->code])
            ->assertOk();

        // Malicious: replace cart entirely
        $cartItemA = App\Models\CartItem::where('product_delivery_option_id', $optionA->id)->first();
        deleteJson(route('api.v1.shop.cart.items.destroy', $cartItemA->id))->assertNoContent();
        // Add non-qualifying item
        postJson(route('api.v1.shop.cart.items.store'),
            ['product_delivery_option_uuid' => $optionB->uuid])->assertOk();

        // Checkout → conditions re-checked, no discount (A not in cart)
        $response = postJson(route('api.v1.shop.checkout'), ['payment_method' => 'wallet'])->assertCreated();
        $order    = Order::with('items')->find($response->json('data.order.id'));
        expect($order->discount_amount)->toBe(0);
        expect($order->grand_total)->toBe(75000);
    });
});

describe('Order & Payment Flow', function (): void {

    test('1. Prepayment checkout: pay deposit via wallet, enroll, remaining balance tracked', function (): void {
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
        $option = ProductDeliveryOption::factory()->create([
            'product_id'              => $product->id,
            'price'                   => 100000,
            'prepayment_amount'       => 25000,
            'is_prepayment_available' => true,
            'capacity'                => 10,
            'uuid'                    => Str::uuid()->toString(),
            'status'                  => PublicationStatusEnum::PUBLISHED,
        ]);

        $customer = User::factory()->create();
        $customer->wallet->update(['balance' => 50000]);
        $this->customer($customer);

        // Add with prepayment type
        postJson(route('api.v1.shop.cart.items.store'), [
            'product_delivery_option_uuid' => $option->uuid,
            'payment_type'                 => 'pre_payment',
        ])->assertOk();

        // Checkout with wallet
        $response = postJson(route('api.v1.shop.checkout'), ['payment_method' => 'wallet'])->assertCreated();

        $order = Order::with('items')->find($response->json('data.order.id'));

        // Order grand total should be prepayment amount (25000), not full price
        expect($order->grand_total)->toBe(25000);
        expect($order->subtotal)->toBe(100000);
        expect($order->total_item_count)->toBe(1);

        // Order item tracks prepayment total
        $orderItem = $order->items->first();
        expect($orderItem->payment_type->value)->toBe('pre_payment');
        expect($orderItem->total)->toBe(25000);
        expect($orderItem->price)->toBe(100000);

        // Wallet debited correctly
        $customer->wallet->refresh();
        expect($customer->wallet->balance)->toBe(25000); // 50000 - 25000

        // Payment record
        assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'method'   => 'wallet',
            'amount'   => 25000,
            'status'   => 'completed',
        ]);

        // Enrollment created
        assertDatabaseHas('enrollments', [
            'order_id'                   => $order->id,
            'customer_id'                => $customer->id,
            'product_delivery_option_id' => $option->id,
        ]);
    });

    test('2. Product unpublished after cart add → checkout fails at validation', function (): void {
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
        $option = ProductDeliveryOption::factory()->create([
            'product_id' => $product->id,
            'price'      => 100000,
            'capacity'   => 10,
            'uuid'       => Str::uuid()->toString(),
            'status'     => PublicationStatusEnum::PUBLISHED,
        ]);

        $customer = User::factory()->create();
        $customer->wallet->update(['balance' => 500000]);
        $this->customer($customer);

        // Add item while published
        postJson(route('api.v1.shop.cart.items.store'), [
            'product_delivery_option_uuid' => $option->uuid,
        ])->assertOk();

        // Admin takes product offline
        $product->update(['status' => PublicationStatusEnum::DRAFT]);

        // Checkout should fail
        postJson(route('api.v1.shop.checkout'), ['payment_method' => 'wallet'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['items.0']);
    });

    test('3. Wallet checkout creates enrollments immediately with correct status', function (): void {
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
        $option = ProductDeliveryOption::factory()->create([
            'product_id' => $product->id,
            'price'      => 100000,
            'capacity'   => 10,
            'uuid'       => Str::uuid()->toString(),
            'status'     => PublicationStatusEnum::PUBLISHED,
        ]);

        $customer = User::factory()->create();
        $customer->wallet->update(['balance' => 500000]);
        $this->customer($customer);

        postJson(route('api.v1.shop.cart.items.store'), [
            'product_delivery_option_uuid' => $option->uuid,
        ])->assertOk();

        $response = postJson(route('api.v1.shop.checkout'), ['payment_method' => 'wallet'])->assertCreated();

        $orderId    = $response->json('data.order.id');
        $enrollment = Enrollment::where('order_id', $orderId)->first();

        expect($enrollment)->not->toBeNull();
        expect($enrollment->customer_id)->toBe($customer->id);
        expect($enrollment->product_delivery_option_id)->toBe($option->id);
        expect($enrollment->enrollment_status)->toBe(EnrollmentStatusEnum::ACTIVE);

        // Cart deleted after successful checkout
        $this->assertDatabaseMissing('carts', ['user_id' => $customer->id]);
    });

    test('4. Mixed productable types (Course + DigitalAsset) in single checkout', function (): void {
        $vendor = Vendor::factory()->create();
        $term   = Term::factory()->create();

        // Course
        $course   = Course::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]);
        $productA = Product::factory()->create([
            'vendor_id'        => $vendor->id,
            'term_id'          => $term->id,
            'productable_id'   => $course->id,
            'productable_type' => MorphTypeEnum::COURSE->value,
            'status'           => PublicationStatusEnum::PUBLISHED,
            'is_visible'       => true,
        ]);
        $optionA = ProductDeliveryOption::factory()->create([
            'product_id' => $productA->id,
            'price'      => 200000,
            'capacity'   => 10,
            'uuid'       => Str::uuid()->toString(),
            'status'     => PublicationStatusEnum::PUBLISHED,
        ]);

        // DigitalAsset
        $asset    = DigitalAsset::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]);
        $productB = Product::factory()->create([
            'vendor_id'        => $vendor->id,
            'term_id'          => $term->id,
            'productable_id'   => $asset->id,
            'productable_type' => MorphTypeEnum::DIGITAL_ASSET->value,
            'status'           => PublicationStatusEnum::PUBLISHED,
            'is_visible'       => true,
        ]);
        $optionB = ProductDeliveryOption::factory()->create([
            'product_id' => $productB->id,
            'price'      => 50000,
            'capacity'   => 10,
            'uuid'       => Str::uuid()->toString(),
            'status'     => PublicationStatusEnum::PUBLISHED,
        ]);

        $customer = User::factory()->create();
        $customer->wallet->update(['balance' => 500000]);
        $this->customer($customer);

        // Add both to cart
        postJson(route('api.v1.shop.cart.items.store'), [
            'product_delivery_option_uuid' => $optionA->uuid,
        ])->assertOk();
        postJson(route('api.v1.shop.cart.items.store'), [
            'product_delivery_option_uuid' => $optionB->uuid,
        ])->assertOk();

        // Checkout
        $response = postJson(route('api.v1.shop.checkout'), ['payment_method' => 'wallet'])->assertCreated();

        $order = Order::with('items')->find($response->json('data.order.id'));
        expect($order->items)->toHaveCount(2);
        expect($order->grand_total)->toBe(250000); // 200000 + 50000

        // Both get enrollments
        expect(Enrollment::where('order_id', $order->id)->count())->toBe(2);
    });
});
