<?php

declare(strict_types=1);

use App\Enums\Content\PublicationStatusEnum;
use App\Enums\Payment\PaymentMethodEnum;
use App\Enums\System\MorphTypeEnum;
use App\Jobs\Provisioning\ProvisionBbbEnrollmentJob;
use App\Jobs\Provisioning\ProvisionImsEnrollmentJob;
use App\Jobs\Provisioning\ProvisionMoodleEnrollmentJob;
use App\Jobs\Provisioning\ProvisionSpotPlayerEnrollmentJob;
use App\Models\Course;
use App\Models\Product;
use App\Models\ProductDeliveryOption;
use App\Models\Term;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

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
describe('Checkout registration & availability window validation', function (): void {
    /** Helper to build product + option with overrides */
    $makeOption = function (array $overrides = []): ProductDeliveryOption {
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

        return ProductDeliveryOption::factory()->create(array_merge([
            'product_id'              => $product->id,
            'price'                   => 100000,
            'capacity'                => 5,
            'uuid'                    => Str::uuid()->toString(),
            'status'                  => PublicationStatusEnum::PUBLISHED,
            'registration_start_date' => null,
            'registration_end_date'   => null,
            'available_from'          => null,
            'available_to'            => null,
        ], $overrides));
    };

    it('fails when registration has not started yet', function () use ($makeOption): void {
        $option   = $makeOption(['registration_start_date' => now()->addDay()]);
        $customer = User::factory()->create();
        $customer->wallet->update(['balance' => 500000]);
        $this->customer($customer);

        postJson(route('api.v1.shop.cart.items.store'), [
            'product_delivery_option_uuid' => $option->uuid,
            'quantity'                     => 1,
        ])->assertOk();

        $response = postJson(route('api.v1.shop.checkout'), ['payment_method' => PaymentMethodEnum::WALLET->value]);
        $response->assertStatus(422)
            ->assertJson([
                'errors' => [
                    'items.0' => ["Registration for '{$option->product->name}' has not started yet."],
                ],
            ]);
    });

    it('fails when registration period has ended', function () use ($makeOption): void {
        $option = $makeOption([
            'registration_start_date' => now()->subWeek(),
            'registration_end_date'   => now()->subDay(),
        ]);
        $customer = User::factory()->create();
        $customer->wallet->update(['balance' => 500000]);
        $this->customer($customer);

        postJson(route('api.v1.shop.cart.items.store'), [
            'product_delivery_option_uuid' => $option->uuid,
            'quantity'                     => 1,
        ])->assertOk();

        $response = postJson(route('api.v1.shop.checkout'), ['payment_method' => PaymentMethodEnum::WALLET->value]);
        $response->assertStatus(422)
            ->assertJson([
                'errors' => [
                    'items.0' => ["Registration period for '{$option->product->name}' has ended."],
                ],
            ]);
    });

    it('fails when product not yet available (available_from future)', function () use ($makeOption): void {
        $option   = $makeOption(['available_from' => now()->addDay()]);
        $customer = User::factory()->create();
        $customer->wallet->update(['balance' => 500000]);
        $this->customer($customer);

        postJson(route('api.v1.shop.cart.items.store'), [
            'product_delivery_option_uuid' => $option->uuid,
            'quantity'                     => 1,
        ])->assertOk();

        $response = postJson(route('api.v1.shop.checkout'), ['payment_method' => PaymentMethodEnum::WALLET->value]);
        $response->assertStatus(422)
            ->assertJson([
                'errors' => [
                    'items.0' => ["'{$option->product->name}' is not yet available for purchase."],
                ],
            ]);
    });

    it('fails when product availability has ended (available_to past)', function () use ($makeOption): void {
        $option = $makeOption([
            'available_from' => now()->subWeek(),
            'available_to'   => now()->subDay(),
        ]);
        $customer = User::factory()->create();
        $customer->wallet->update(['balance' => 500000]);
        $this->customer($customer);

        postJson(route('api.v1.shop.cart.items.store'), [
            'product_delivery_option_uuid' => $option->uuid,
            'quantity'                     => 1,
        ])->assertOk();

        $response = postJson(route('api.v1.shop.checkout'), ['payment_method' => PaymentMethodEnum::WALLET->value]);
        $response->assertStatus(422)
            ->assertJson([
                'errors' => [
                    'items.0' => ["'{$option->product->name}' is no longer available for purchase."],
                ],
            ]);
    });

    it('succeeds when within both registration and availability windows', function () use ($makeOption): void {
        $option = $makeOption([
            'registration_start_date' => now()->subWeek(),
            'registration_end_date'   => now()->addDay(),
            'available_from'          => now()->subWeek(),
            'available_to'            => now()->addDay(),
        ]);
        $customer = User::factory()->create();
        $customer->wallet->update(['balance' => 500000]);
        $this->customer($customer);
        postJson(route('api.v1.shop.cart.items.store'), [
            'product_delivery_option_uuid' => $option->uuid,
            'quantity'                     => 1,
        ])->assertOk();
        postJson(route('api.v1.shop.checkout'), ['payment_method' => PaymentMethodEnum::WALLET->value])
            ->assertCreated();
    });
});
