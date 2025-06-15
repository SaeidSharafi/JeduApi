<?php

use App\Models\Vendor;

uses(\Tests\AuthTestTrait::class);
describe('Admin Vendor Select Option API', function () {
    it('returns filtered vendor select options', function () {
        $this->authorized_user();
        Storage::fake('public');
        $logo = MediaUploader::fromSource(Illuminate\Http\UploadedFile::fake()->image('logo.jpg'))
            ->toDisk('public')
            ->upload();
        \App\Models\Vendor::factory()->count(3)
            ->afterCreating(function (Vendor $vendor) use ($logo) {
                $vendor->attachMedia($logo->id, 'logo');
                $vendor->logo_url = $vendor->getMedia('logo')->first()->getUrl();
                $vendor->save();
            })
            ->create();
        $vendor = \App\Models\Vendor::factory()
            ->afterCreating(function (Vendor $vendor) use ($logo) {
                $vendor->attachMedia($logo->id, 'logo');
                $vendor->logo_url = $vendor->getMedia('logo')->first()->getUrl();
                $vendor->save();
            })
            ->create([
            'name' => 'TestVendor',
            'address' => 'test address',
        ])->fresh();
        $response = $this->getJson(
            route('api.v1.admin.select-option.vendor', ['q' => 'TestVendor'])
        );

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'title',
                    'subtitle',
                    'image_url',
                ],
            ],
        ]);
        $response->assertJsonFragment([
            'title' => 'TestVendor',
            'subtitle' => 'test address',
            'image_url' => $vendor->logo_url,
        ]);
    });

    it('returns empty data if no match', function () {
        $this->authorized_user();
        $response = $this->getJson(
            route('api.v1.admin.select-option.vendor', ['q' => 'NoSuchVendor'])
        );
        $response->assertOk();
        $response->assertJson(['data' => []]);
    });
});
