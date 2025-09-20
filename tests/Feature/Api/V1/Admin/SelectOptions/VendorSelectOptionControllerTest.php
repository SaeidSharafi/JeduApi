<?php

declare(strict_types=1);

use App\Models\Vendor;

uses(Tests\AuthTestTrait::class);
describe('Admin Vendor Select Option API', function (): void {
    it('returns filtered vendor select options', function (): void {
        $this->authorized_user();
        Storage::fake('public');
        $logo = MediaUploader::fromSource(Illuminate\Http\UploadedFile::fake()->image('logo.jpg'))
            ->toDisk('public')
            ->upload();
        Vendor::factory()->count(3)
            ->afterCreating(function (Vendor $vendor) use ($logo): void {
                $vendor->attachMedia($logo->id, 'logo');
                $vendor->logo_url = $vendor->getMedia('logo')->first()->getUrl();
                $vendor->save();
            })
            ->create();
        $vendor = Vendor::factory()
            ->afterCreating(function (Vendor $vendor) use ($logo): void {
                $vendor->attachMedia($logo->id, 'logo');
                $vendor->logo_url = $vendor->getMedia('logo')->first()->getUrl();
                $vendor->save();
            })
            ->create([
                'name'    => 'TestVendor',
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
            'title'     => 'TestVendor',
            'subtitle'  => 'test address',
            'image_url' => $vendor->logo_url,
        ]);
    });

    it('returns empty data if no match', function (): void {
        $this->authorized_user();
        $response = $this->getJson(
            route('api.v1.admin.select-option.vendor', ['q' => 'NoSuchVendor'])
        );
        $response->assertOk();
        $response->assertJson(['data' => []]);
    });
});
