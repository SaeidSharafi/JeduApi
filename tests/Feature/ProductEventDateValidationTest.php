<?php

declare(strict_types=1);

use App\Enums\Content\PublicationStatusEnum;
use App\Enums\PermissionEnum;
use App\Enums\Product\ProductableEnum;
use App\Models\Category;
use App\Models\Course;
use App\Models\Product;
use App\Models\Term;
use App\Models\Vendor;

use function Pest\Laravel\assertDatabaseHas;

uses(Tests\Support\Traits\AuthTestTrait::class);

describe('Product Event Date Validation', function (): void {
    beforeEach(function (): void {
        $this->category = Category::factory()->create();
        $this->vendor   = Vendor::factory()->create();
        $this->term     = Term::factory()->create();
        $this->course   = Course::factory()->create();
    });

    function validProductCreateData(): array
    {
        return [
            'force_create'      => true,
            'productable_type'  => ProductableEnum::COURSE->value,
            'productable_id'    => test()->course->id,
            'vendor_id'         => test()->vendor->id,
            'term_id'           => test()->term->id,
            'status'            => PublicationStatusEnum::DRAFT->value,
            'is_visible'        => true,
            'is_featured'       => false,
            'name'              => 'Event Date Test Product',
            'short_name'        => 'Event Test',
            'short_description' => 'Testing event date validation',
            'categories'        => [test()->category->id],
            'details_json'      => [],
        ];
    }

    it('fails when only event_start_at is provided', function (): void {
        $this->authorized_user([PermissionEnum::PRODUCT_CREATE]);
        $data                   = validProductCreateData();
        $data['event_start_at'] = '1405-03-11 00:00:00';
        $data['name']           = 'Missing End Date';
        $response               = $this->postJson(route('api.v1.admin.products.store'), $data);
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['event_ended_at']);
    });

    it('fails when only event_ended_at is provided', function (): void {
        $this->authorized_user([PermissionEnum::PRODUCT_CREATE]);
        $data                   = validProductCreateData();
        $data['event_ended_at'] = '1405-04-09 00:00:00';
        $data['name']           = 'Missing Start Date';
        $response               = $this->postJson(route('api.v1.admin.products.store'), $data);
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['event_start_at']);
    });

    it('fails when event_start_at is after event_ended_at', function (): void {
        $this->authorized_user([PermissionEnum::PRODUCT_CREATE]);
        $data                   = validProductCreateData();
        $data['event_start_at'] = '1405-04-09 00:00:00';
        $data['event_ended_at'] = '1405-03-11 00:00:00';
        $data['name']           = 'Reversed Dates';
        $response               = $this->postJson(route('api.v1.admin.products.store'), $data);
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['event_ended_at']);
    });

    it('stores product in database with valid event_start_at and event_ended_at', function (): void {
        $this->authorized_user([PermissionEnum::PRODUCT_CREATE]);
        $data                   = validProductCreateData();
        $data['event_start_at'] = '1405-03-11 00:00:00';
        $data['event_ended_at'] = '1405-04-09 23:59:00';
        $data['name']           = 'Event Product';
        $response               = $this->postJson(route('api.v1.admin.products.store'), $data);
        $response->assertCreated();
        assertDatabaseHas('products', [
            'name'           => 'Event Product',
            'event_start_at' => '2026-06-01 00:00:00',
            'event_ended_at' => '2026-06-30 23:59:00',
        ]);
    });

    it('stores product in database when event_start_at equals event_ended_at', function (): void {
        $this->authorized_user([PermissionEnum::PRODUCT_CREATE]);
        $data                   = validProductCreateData();
        $data['event_start_at'] = '1405-03-25 00:00:00';
        $data['event_ended_at'] = '1405-03-25 00:00:00';
        $data['name']           = 'Same Day Event';
        $response               = $this->postJson(route('api.v1.admin.products.store'), $data);
        $response->assertCreated();
        assertDatabaseHas('products', [
            'name'           => 'Same Day Event',
            'event_start_at' => '2026-06-15 00:00:00',
            'event_ended_at' => '2026-06-15 00:00:00',
        ]);
    });

    it('updates product in database with valid event dates', function (): void {
        $this->authorized_user([PermissionEnum::PRODUCT_UPDATE]);
        $product = Product::factory()->create();
        $data    = [
            'vendor_id'      => $this->vendor->id,
            'term_id'        => $this->term->id,
            'status'         => PublicationStatusEnum::PUBLISHED,
            'is_visible'     => true,
            'is_featured'    => false,
            'name'           => 'Updated Event Product',
            'short_name'     => 'Updated Event',
            'categories'     => [$this->category->id],
            'details_json'   => [],
            'event_start_at' => '1405-04-10 00:00:00',
            'event_ended_at' => '1405-05-09 00:00:00',
        ];
        $response = $this->putJson(route('api.v1.admin.products.update', ['product' => $product->id]), $data);
        $response->assertOk();
        assertDatabaseHas('products', [
            'id'             => $product->id,
            'name'           => 'Updated Event Product',
            'event_start_at' => '2026-07-01 00:00:00',
            'event_ended_at' => '2026-07-31 00:00:00',
        ]);
    });
});
