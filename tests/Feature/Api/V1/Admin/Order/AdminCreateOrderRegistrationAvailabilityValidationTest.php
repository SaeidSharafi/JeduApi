<?php

declare(strict_types=1);

use App\Enums\Content\PublicationStatusEnum;
use App\Enums\System\MorphTypeEnum;
use App\Models\Course;
use App\Models\Product;
use App\Models\ProductDeliveryOption;
use App\Models\Term;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Support\Str;
use App\Enums\PermissionEnum;

use function Pest\Laravel\postJson;

uses(Tests\Support\Traits\AuthTestTrait::class);

describe('Admin create order registration & availability validation', function (): void {
    $makeOption = function (array $overrides = []): ProductDeliveryOption {
        $vendor = Vendor::factory()->create();
        $term   = Term::factory()->create();
        $course = Course::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]);
        $product = Product::factory()->create([
            'vendor_id'        => $vendor->id,
            'term_id'          => $term->id,
            'productable_id'   => $course->id,
            'productable_type' => MorphTypeEnum::COURSE->value,
            'status'           => PublicationStatusEnum::PUBLISHED,
            'is_visible'       => true,
        ]);
        return ProductDeliveryOption::factory()->create(array_merge([
            'product_id'             => $product->id,
            'price'                  => 100000,
            'capacity'               => 5,
            'uuid'                   => Str::uuid()->toString(),
            'status'                 => PublicationStatusEnum::PUBLISHED,
            'registration_start_date'=> null,
            'registration_end_date'  => null,
            'available_from'         => null,
            'available_to'           => null,
        ], $overrides));
    };

    it('fails before registration start', function () use ($makeOption): void {
        $option = $makeOption(['registration_start_date' => now()->addHour()]);
        $customer = User::factory()->create();
        $this->authorized_user([PermissionEnum::ORDER_CREATE]);

        postJson(route('api.v1.admin.orders.store'), [
            'user_id' => $customer->id,
            'items' => [
                ['product_delivery_option_uuid' => $option->uuid, 'quantity' => 1],
            ],
        ])->assertStatus(422)
            ->assertJsonPath('errors.items.0.0', "Registration for '{$option->product->name}' has not started yet.");
    });

    it('fails after registration end', function () use ($makeOption): void {
        $option = $makeOption([
            'registration_start_date' => now()->subDays(2),
            'registration_end_date'   => now()->subDay(),
        ]);
        $customer = User::factory()->create();
        $this->authorized_user([PermissionEnum::ORDER_CREATE]);

        postJson(route('api.v1.admin.orders.store'), [
            'user_id' => $customer->id,
            'items' => [
                ['product_delivery_option_uuid' => $option->uuid, 'quantity' => 1],
            ],
        ])->assertStatus(422)
            ->assertJsonPath('errors.items.0.0', "Registration period for '{$option->product->name}' has ended.");
    });

    it('fails before availability start', function () use ($makeOption): void {
        $option = $makeOption(['available_from' => now()->addHour()]);
        $customer = User::factory()->create();
        $this->authorized_user([PermissionEnum::ORDER_CREATE]);

        postJson(route('api.v1.admin.orders.store'), [
            'user_id' => $customer->id,
            'items' => [
                ['product_delivery_option_uuid' => $option->uuid, 'quantity' => 1],
            ],
        ])->assertStatus(422)
            ->assertJsonPath('errors.items.0.0', "'{$option->product->name}' is not yet available for purchase.");
    });

    it('fails after availability end', function () use ($makeOption): void {
        $option = $makeOption([
            'available_from' => now()->subDays(2),
            'available_to'   => now()->subDay(),
        ]);
        $customer = User::factory()->create();
        $this->authorized_user([PermissionEnum::ORDER_CREATE]);

        postJson(route('api.v1.admin.orders.store'), [
            'user_id' => $customer->id,
            'items' => [
                ['product_delivery_option_uuid' => $option->uuid, 'quantity' => 1],
            ],
        ])->assertStatus(422)
            ->assertJsonPath('errors.items.0.0', "'{$option->product->name}' is no longer available for purchase.");
    });

    it('succeeds within valid windows', function () use ($makeOption): void {
        $option = $makeOption([
            'registration_start_date' => now()->subHour(),
            'registration_end_date'   => now()->addHour(),
            'available_from'          => now()->subHour(),
            'available_to'            => now()->addHour(),
        ]);
        $customer = User::factory()->create();
        $this->authorized_user([PermissionEnum::ORDER_CREATE]);

        postJson(route('api.v1.admin.orders.store'), [
            'user_id' => $customer->id,
            'items' => [
                ['product_delivery_option_uuid' => $option->uuid, 'quantity' => 1],
            ],
        ])->assertCreated();
    });
});
