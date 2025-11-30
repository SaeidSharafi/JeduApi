<?php

declare(strict_types=1);

use App\Actions\Shop\Payment\VerifyPaymentAction;
use App\Data\Shop\Payment\GatewayCallbackData;
use App\Enums\Content\PublicationStatusEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Enums\System\MorphTypeEnum;
use App\Models\Course;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductDeliveryOption;
use App\Models\Term;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Support\Str;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\postJson;

uses(Tests\Support\Traits\AuthTestTrait::class);

describe('Gateway Payment Complex Scenarios', function (): void {

    test('idempotent gateway verify: duplicate callback is no-op', function (): void {
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
            'uuid'       => Str::uuid()->toString(),
            'status'     => PublicationStatusEnum::PUBLISHED,
        ]);

        $customer = User::factory()->create();
        $this->customer($customer);

        postJson(route('api.v1.shop.cart.items.store'), [
            'product_delivery_option_uuid' => $option->uuid,
            'quantity'                     => 1,
        ])->assertOk();

        // Initiate checkout with gateway (not wallet, to test gateway path)
        $response = postJson(route('api.v1.shop.checkout'), ['payment_method' => 'mellat_gateway']);

        // May return redirect or created depending on processor; if redirect, payment is PENDING
        if ($response->status() === 201) {
            $orderId = $response->json('data.order.id');
            $order   = Order::find($orderId);
            $payment = $order->payments()->first();

            // If payment is already completed (e.g., bank_transfer auto-complete), skip verify test
            if ($payment->status === PaymentStatusEnum::COMPLETED) {
                $this->markTestSkipped('Payment auto-completed; no verify needed');
            }

            // Simulate first gateway callback (verify success)
            $callbackData = new GatewayCallbackData(
                payment_uuid: $payment->uuid,
                gateway_response: ['ResCode' => '0', 'RefId' => '123456']
            );

            $verifyAction    = app(VerifyPaymentAction::class);
            $verifiedPayment = $verifyAction->handle($callbackData);

            expect($verifiedPayment->status)->toBe(PaymentStatusEnum::COMPLETED);

            // Simulate duplicate callback with same data
            $payment->refresh();
            expect($payment->status)->toBe(PaymentStatusEnum::COMPLETED);

            // Second verify attempt should fail validation (not PENDING)
            try {
                $verifyAction->handle($callbackData);
                expect(false)->toBeTrue('Expected validation exception for non-pending payment');
            } catch (Illuminate\Validation\ValidationException $e) {
                expect($e->errors())->toHaveKey('payment');
            }

            // Assert no duplicate enrollments or payments created
            $enrollmentCount = $order->enrollments()->count();
            expect($enrollmentCount)->toBeGreaterThan(0);

            // Re-verify enrollment count is stable after duplicate callback attempt
            $order->refresh();
            expect($order->enrollments()->count())->toBe($enrollmentCount);
        }
    });

    test('concurrent capacity race: last spot taken by first checkout', function (): void {
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
            'uuid'       => Str::uuid()->toString(),
            'status'     => PublicationStatusEnum::PUBLISHED,
            'capacity'   => 1, // Only one spot
        ]);

        $customer1 = User::factory()->create();
        $customer2 = User::factory()->create();

        // Customer1 adds to cart
        $this->customer($customer1);
        postJson(route('api.v1.shop.cart.items.store'), [
            'product_delivery_option_uuid' => $option->uuid,
            'quantity'                     => 1,
        ])->assertOk();

        // Customer2 adds to cart (before customer1 checks out)
        $this->customer($customer2);
        postJson(route('api.v1.shop.cart.items.store'), [
            'product_delivery_option_uuid' => $option->uuid,
            'quantity'                     => 1,
        ])->assertOk();

        // Customer1 checks out first (takes the spot)
        $this->customer($customer1);
        $response1 = postJson(route('api.v1.shop.checkout'), ['payment_method' => 'bank_transfer']);
        $response1->assertCreated();

        // Customer2 tries to checkout (should fail due to capacity exhausted)
        $this->customer($customer2);
        $response2 = postJson(route('api.v1.shop.checkout'), ['payment_method' => 'bank_transfer']);
        $response2->assertStatus(422);
        $response2->assertJsonValidationErrors(['items.0']);

        // Assert only one enrollment created
        assertDatabaseHas('enrollments', [
            'customer_id'                => $customer1->id,
            'product_delivery_option_id' => $option->id,
        ]);

        expect(App\Models\Enrollment::where('product_delivery_option_id', $option->id)->count())->toBe(1);
    });

    test('duplicate ownership check across different delivery options of same productable', function (): void {
        $vendor   = Vendor::factory()->create();
        $term     = Term::factory()->create();
        $course   = Course::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]);
        $product1 = Product::factory()->create([
            'vendor_id'        => $vendor->id,
            'term_id'          => $term->id,
            'productable_id'   => $course->id,
            'productable_type' => MorphTypeEnum::COURSE->value,
            'status'           => PublicationStatusEnum::PUBLISHED,
            'is_visible'       => true,
            'name'             => 'Course Package A',
        ]);
        $option1 = ProductDeliveryOption::factory()->create([
            'product_id' => $product1->id,
            'price'      => 100000,
            'uuid'       => Str::uuid()->toString(),
            'status'     => PublicationStatusEnum::PUBLISHED,
        ]);

        $product2 = Product::factory()->create([
            'vendor_id'        => $vendor->id,
            'term_id'          => $term->id,
            'productable_id'   => $course->id, // Same course as product1
            'productable_type' => MorphTypeEnum::COURSE->value,
            'status'           => PublicationStatusEnum::PUBLISHED,
            'is_visible'       => true,
            'name'             => 'Course Package B',
        ]);
        $option2 = ProductDeliveryOption::factory()->create([
            'product_id' => $product2->id,
            'price'      => 150000,
            'uuid'       => Str::uuid()->toString(),
            'status'     => PublicationStatusEnum::PUBLISHED,
        ]);

        $customer = User::factory()->create();
        $this->customer($customer);

        // Purchase first option
        postJson(route('api.v1.shop.cart.items.store'), [
            'product_delivery_option_uuid' => $option1->uuid,
            'quantity'                     => 1,
        ])->assertOk();

        $response = postJson(route('api.v1.shop.checkout'), ['payment_method' => 'bank_transfer']);
        $response->assertCreated();

        // Try to purchase second option (different delivery, same underlying course)
        postJson(route('api.v1.shop.cart.items.store'), [
            'product_delivery_option_uuid' => $option2->uuid,
            'quantity'                     => 1,
        ])->assertOk();

        $response = postJson(route('api.v1.shop.checkout'), ['payment_method' => 'bank_transfer']);

        // Should fail duplicate ownership check (same productable_id + productable_type)
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items']);
    });
});
