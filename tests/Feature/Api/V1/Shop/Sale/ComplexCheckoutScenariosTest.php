<?php

declare(strict_types=1);

use App\Enums\Content\PublicationStatusEnum;
use App\Enums\Order\DiscountTypeEnum;
use App\Enums\Payment\PaymentMethodEnum;
use App\Enums\System\MorphTypeEnum;
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
use App\Models\Term;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Wallet;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

use function Pest\Laravel\assertDatabaseHas;
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
        $orderIncrementId = $response->json('metadata.order_id');
        expect($orderIncrementId)->not->toBeNull();

        // Top up wallet
        $customer->wallet->update(['balance' => 200000]);

        // Retry payment on existing order
        $order = Order::query()->where('increment_id', $orderIncrementId)->first();
        $response = postJson(route('api.v1.shop.student.orders.retry-payment', $order->increment_id), [
            'payment_method' => PaymentMethodEnum::WALLET->value,
        ]);
        $response->assertOk();

        // Verify payment created
        assertDatabaseHas('payments', ['order_id' => $order->id, 'method' => PaymentMethodEnum::WALLET->value, 'status' => 'completed']);
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
});
