<?php

declare(strict_types=1);

use App\Models\Vendor;

uses(Tests\Support\Traits\AuthTestTrait::class);
beforeEach(function (): void {
    Illuminate\Http\UploadedFile::fake();
    Storage::fake('public');
    $this->logo = MediaUploader::fromSource(Illuminate\Http\UploadedFile::fake()->image('logo.jpg'))
        ->toDisk('public')
        ->upload();
    $this->favicon = MediaUploader::fromSource(Illuminate\Http\UploadedFile::fake()->image('favicon.png'))
        ->toDisk('public')
        ->upload();
});

describe('list filter', function (): void {
    it('filter vendors by name', function (): void {
        $this->authorized_user([App\Enums\PermissionEnum::VENDOR_VIEW_ANY]);
        Vendor::factory(10)->create();
        $vendor   = Vendor::factory()->create(['name' => 'Test Vendor']);
        $response = $this->getJson(route('api.v1.admin.vendor.index', ['filter' => ['name' => 'Test Vendor']]));

        $response->assertOk()
            ->assertJsonFragment(['name' => 'Test Vendor'])
            ->assertJsonCount(1, 'data.data');
    });
    it('filter vendors by email', function (): void {
        $this->authorized_user([App\Enums\PermissionEnum::VENDOR_VIEW_ANY]);
        Vendor::factory(10)->create();
        $vendor   = Vendor::factory()->create(['email' => 'vendor@example.com']);
        $response = $this->getJson(route('api.v1.admin.vendor.index', ['filter' => ['email' => 'vendor@example.com']]));
        $response->assertOk()
            ->assertJsonFragment(['email' => 'vendor@example.com'])
            ->assertJsonCount(1, 'data.data');
    });
    it('filter vendors by phone', function (): void {
        $this->authorized_user([App\Enums\PermissionEnum::VENDOR_VIEW_ANY]);
        Vendor::factory(10)->create();
        $vendor   = Vendor::factory()->create(['phone' => '+1234567890']);
        $response = $this->getJson(route('api.v1.admin.vendor.index', ['filter' => ['phone' => '+1234567890']]));
        $response->assertOk()
            ->assertJsonFragment(['phone' => '+1234567890'])
            ->assertJsonCount(1, 'data.data');
    });
    it('should return all vendors when no filter is applied', function (): void {
        $this->authorized_user([App\Enums\PermissionEnum::VENDOR_VIEW_ANY]);
        Vendor::factory(10)->create();
        $response = $this->getJson(route('api.v1.admin.vendor.index'));

        $response->assertOk()
            ->assertJsonCount(10, 'data.data');
    });

    it('should return an empty list when no vendors match the filter', function (): void {
        $this->authorized_user([App\Enums\PermissionEnum::VENDOR_VIEW_ANY]);
        Vendor::factory(10)->create();
        $response = $this->getJson(route('api.v1.admin.vendor.index', ['filter' => ['name' => 'Nonexistent Vendor']]));

        $response->assertOk()
            ->assertJsonCount(0, 'data.data');
    });

    it('should sort vendors by name', function (): void {
        $this->authorized_user([App\Enums\PermissionEnum::VENDOR_VIEW_ANY]);
        Vendor::factory()->create(['name' => 'B Vendor']);
        Vendor::factory()->create(['name' => 'A Vendor']);
        $response = $this->getJson(route('api.v1.admin.vendor.index', ['sort' => 'name']));

        $response->assertOk()
            ->assertJsonFragment(['name' => 'A Vendor'])
            ->assertJsonFragment(['name' => 'B Vendor'])
            ->assertJsonCount(2, 'data.data');
    });
    it('should sort vendors by created_at', function (): void {
        $this->authorized_user([App\Enums\PermissionEnum::VENDOR_VIEW_ANY]);
        $vendor1  = Vendor::factory()->create(['created_at' => now()->subDays(2)])->fresh();
        $vendor2  = Vendor::factory()->create(['created_at' => now()->subDays(1)])->fresh();
        $response = $this->getJson(route('api.v1.admin.vendor.index', ['sort' => '-created_at']));

        $response->assertOk()
            ->assertJson(function (Illuminate\Testing\Fluent\AssertableJson $json) use ($vendor2, $vendor1): void {
                $json->has('data.data', 2)
                    ->where('data.data.0.id', $vendor2->id)
                    ->where('data.data.1.id', $vendor1->id)
                    ->etc();
            });
    });

})->group('vendor');
describe('CRUD', function (): void {
    it('should return a list of vendors', function (): void {
        $this->authorized_user([App\Enums\PermissionEnum::VENDOR_VIEW_ANY]);
        $response = $this->getJson(route('api.v1.admin.vendor.index'));

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'name',
                            'email',
                            'phone',
                            'phone2',
                            'address',
                            'map_location',
                            'logo_url',
                            'favicon_url',
                            'social_links',
                            'theme_options',
                        ],
                    ],
                ],
            ]);
    });

    it('should create a new vendor', function (): void {
        $this->authorized_user([App\Enums\PermissionEnum::VENDOR_CREATE]);
        $data          = Vendor::factory()->make()->toArray();
        $data['media'] = [
            'logo'    => $this->logo->id,
            'favicon' => $this->favicon->id,
        ];

        $response = $this->postJson(route('api.v1.admin.vendor.store'), $data);
        $response->assertCreated();
        $this->assertDatabaseHas('vendors', [
            'name'         => $data['name'],
            'email'        => $data['email'],
            'phone'        => $data['phone'],
            'phone2'       => $data['phone2'],
            'address'      => $data['address'],
            'map_location' => $data['map_location'],
        ]);
        $vendor = Vendor::where('email', $data['email'])->first();
        $this->assertDatabaseHas('mediables', [
            'mediable_id'   => $vendor->id,
            'mediable_type' => \App\Enums\System\MorphTypeEnum::VENDOR->value,
            'media_id'      => $this->logo->id,
            'tag'           => 'logo',
        ]);
        $this->assertDatabaseHas('mediables', [
            'mediable_id'   => $vendor->id,
            'mediable_type' => \App\Enums\System\MorphTypeEnum::VENDOR->value,
            'media_id'      => $this->favicon->id,
            'tag'           => 'favicon',
        ]);
    });

    it('should show a vendor', function (): void {
        $this->authorized_user([App\Enums\PermissionEnum::VENDOR_VIEW]);
        $vendor = Vendor::factory()->create();
        $vendor->attachMedia($this->logo, 'logo');
        $vendor->attachMedia($this->favicon, 'favicon');

        $response = $this->getJson(route('api.v1.admin.vendor.show', ['vendor' => $vendor->id]));
        $response->assertOk()
            ->assertJsonFragment([
                'id'           => $vendor->id,
                'name'         => $vendor->name,
                'email'        => $vendor->email,
                'phone'        => $vendor->phone,
                'phone2'       => $vendor->phone2,
                'address'      => $vendor->address,
                'map_location' => $vendor->map_location,
            ]);
    });

    it('should update a vendor', function (): void {
        $this->authorized_user([App\Enums\PermissionEnum::VENDOR_UPDATE]);
        $vendor        = Vendor::factory()->create();
        $data          = Vendor::factory()->make()->toArray();
        $data['media'] = [
            'logo'    => $this->logo->id,
            'favicon' => $this->favicon->id,
        ];

        $response = $this->putJson(route('api.v1.admin.vendor.update', ['vendor' => $vendor->id]), $data);
        $response->assertOk();
        $this->assertDatabaseHas('vendors', [
            'id'           => $vendor->id,
            'name'         => $data['name'],
            'email'        => $data['email'],
            'phone'        => $data['phone'],
            'phone2'       => $data['phone2'],
            'address'      => $data['address'],
            'map_location' => $data['map_location'],
        ]);
        $this->assertDatabaseHas('mediables', [
            'mediable_id'   => $vendor->id,
            'mediable_type' => \App\Enums\System\MorphTypeEnum::VENDOR->value,
            'media_id'      => $this->logo->id,
            'tag'           => 'logo',
        ]);
        $this->assertDatabaseHas('mediables', [
            'mediable_id'   => $vendor->id,
            'mediable_type' => \App\Enums\System\MorphTypeEnum::VENDOR->value,
            'media_id'      => $this->favicon->id,
            'tag'           => 'favicon',
        ]);
    });

    it('should delete a vendor', function (): void {
        $this->authorized_user([App\Enums\PermissionEnum::VENDOR_DELETE]);
        $vendor = Vendor::factory()->create();
        $vendor->attachMedia($this->logo, 'logo');
        $vendor->attachMedia($this->favicon, 'favicon');

        $response = $this->deleteJson(route('api.v1.admin.vendor.destroy', ['vendor' => $vendor->id]));
        $response->assertNoContent();
        $this->assertDatabaseMissing('vendors', ['id' => $vendor->id]);
        $this->assertDatabaseMissing('mediables', [
            'mediable_id'   => $vendor->id,
            'mediable_type' => \App\Enums\System\MorphTypeEnum::VENDOR->value,
            'media_id'      => $this->logo->id,
        ]);
        $this->assertDatabaseMissing('mediables', [
            'mediable_id'   => $vendor->id,
            'mediable_type' => \App\Enums\System\MorphTypeEnum::VENDOR->value,
            'media_id'      => $this->favicon->id,
        ]);
        $this->assertDatabaseMissing('media', ['id' => $this->logo->id]);
        $this->assertDatabaseMissing('media', ['id' => $this->favicon->id]);
    });

    it('should not delete a vendor with related data', function (): void {
        $this->authorized_user([App\Enums\PermissionEnum::VENDOR_DELETE]);
        $vendor = Vendor::factory()->create();
        App\Models\Product::factory()->create(['vendor_id' => $vendor->id]);
        $response = $this->deleteJson(route('api.v1.admin.vendor.destroy', ['vendor' => $vendor->id]));
        $response
            ->assertStatus(422)
            ->assertJsonFragment([
                'message' => __('messages.errors.model_has_relationship_data',
                    ['related_model' => getModelLabel(App\Models\Product::class)]),
            ]);
        $this->assertDatabaseHas('vendors', ['id' => $vendor->id]);
    });

})->group('vendor');
