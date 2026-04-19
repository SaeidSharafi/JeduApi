<?php

declare(strict_types=1);

use App\Enums\Content\PublicationStatusEnum;
use App\Enums\Order\OrderItemPaymentTypeEnum;
use App\Enums\Order\OrderStatusEnum;
use App\Enums\PermissionEnum;
use App\Enums\System\MorphTypeEnum;
use App\Models\Course;
use App\Models\Product;
use App\Models\ProductDeliveryOption;
use App\Models\Term;
use App\Models\User;
use App\Models\Vendor;
use Carbon\Carbon;
use Illuminate\Support\Str;

use function Pest\Laravel\postJson;

uses(Tests\Support\Traits\AuthTestTrait::class);

describe('Admin create order registration & availability validation', function (): void {
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

    it('fails before registration start', function () use ($makeOption): void {
        $option = $makeOption(['registration_start_date' => now()->addDay()]);
        $option->load('product');
        $customer = User::factory()->create();
        $this->authorized_user([PermissionEnum::ORDER_CREATE]);

        $response = postJson(route('api.v1.admin.order.store'), [
            'status'      => OrderStatusEnum::PENDING->value,
            'customer_id' => $customer->id,
            'items'       => [
                [
                    'product_delivery_option_id' => $option->id,
                    'payment_type'               => OrderItemPaymentTypeEnum::FULL_PAYMENT->value,
                    'qty_ordered'                => 1,
                ],
            ],
        ]);
        $response->assertStatus(422);
        $errors = $response->json('errors');
        $error  = $errors['items.0'][0];
        expect($error)->toBeString()
            ->and(str_contains($error, 'Registration for'))->toBeTrue()
            ->and(str_contains($error, 'has not started yet'))->toBeTrue();
    });

    it('fails after registration end', function () use ($makeOption): void {
        $option = $makeOption([
            'registration_start_date' => now()->subWeek(),
            'registration_end_date'   => now()->subDay(),
        ]);
        $option->load('product');
        $customer = User::factory()->create();
        $this->authorized_user([PermissionEnum::ORDER_CREATE]);

        $response = postJson(route('api.v1.admin.order.store'), [
            'status'      => OrderStatusEnum::PENDING->value,
            'customer_id' => $customer->id,
            'items'       => [
                [
                    'product_delivery_option_id' => $option->id,
                    'payment_type'               => OrderItemPaymentTypeEnum::FULL_PAYMENT->value,
                    'qty_ordered'                => 1,
                ],
            ],
        ]);
        $response->assertStatus(422);
        $errors = $response->json('errors');
        $error  = $errors['items.0'][0];
        expect($error)->toBeString()
            ->and(str_contains($error, 'Registration period for'))->toBeTrue()
            ->and(str_contains($error, 'has ended'))->toBeTrue();
    });

    it('fails before availability start', function () use ($makeOption): void {
        $option = $makeOption(['available_from' => now()->addDay()]);
        $option->load('product');
        $customer = User::factory()->create();
        $this->authorized_user([PermissionEnum::ORDER_CREATE]);

        $response = postJson(route('api.v1.admin.order.store'), [
            'status'      => OrderStatusEnum::PENDING->value,
            'customer_id' => $customer->id,
            'items'       => [
                [
                    'product_delivery_option_id' => $option->id,
                    'payment_type'               => OrderItemPaymentTypeEnum::FULL_PAYMENT->value,
                    'qty_ordered'                => 1,
                ],
            ],
        ]);
        $response->assertStatus(422);
        $errors = $response->json('errors');
        $error  = $errors['items.0'][0];
        expect($error)->toBeString()
            ->and(str_contains($error, 'is not yet available for purchase'))->toBeTrue();
    });

    it('fails after availability end', function () use ($makeOption): void {
        $option = $makeOption([
            'available_from' => now()->subWeek(),
            'available_to'   => now()->subDay(),
        ]);
        $option->load('product');
        $customer = User::factory()->create();
        $this->authorized_user([PermissionEnum::ORDER_CREATE]);

        $response = postJson(route('api.v1.admin.order.store'), [
            'status'      => OrderStatusEnum::PENDING->value,
            'customer_id' => $customer->id,
            'items'       => [
                [
                    'product_delivery_option_id' => $option->id,
                    'payment_type'               => OrderItemPaymentTypeEnum::FULL_PAYMENT->value,
                    'qty_ordered'                => 1,
                ],
            ],
        ]);
        $response->assertStatus(422);
        $errors = $response->json('errors');
        $error  = $errors['items.0'][0];
        expect($error)->toBeString()
            ->and(str_contains($error, 'is no longer available for purchase'))->toBeTrue();
    });

    it('succeeds within valid windows', function () use ($makeOption): void {
        $option = $makeOption([
            'registration_start_date' => now()->subWeek(),
            'registration_end_date'   => now()->addDay(),
            'available_from'          => now()->subWeek(),
            'available_to'            => now()->addDay(),
        ]);
        $customer = User::factory()->create();
        $this->authorized_user([PermissionEnum::ORDER_CREATE]);

        postJson(route('api.v1.admin.order.store'), [
            'status'      => OrderStatusEnum::PENDING->value,
            'customer_id' => $customer->id,
            'items'       => [
                [
                    'product_delivery_option_id' => $option->id,
                    'payment_type'               => OrderItemPaymentTypeEnum::FULL_PAYMENT->value,
                    'qty_ordered'                => 1,
                ],
            ],
        ])->assertCreated();
    });
});
