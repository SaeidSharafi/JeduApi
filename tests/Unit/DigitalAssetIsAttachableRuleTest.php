<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);
describe('DigitalAssetIsAttachableRule', function (): void {
    it('validates an array of attachable digital asset IDs', function (): void {
        $digitalAssetIds = App\Models\DigitalAsset::factory()->count(3)->create([
            'is_attachable_to_course' => true,
        ])->pluck('id')->toArray();

        $validator = Validator::make(
            ['digital_assets' => $digitalAssetIds],
            ['digital_assets' => [new App\Rules\DigitalAssetIsAttachableRule()]]
        );

        expect($validator->passes())->toBeTrue();
    });

    it('fails validation for non-integer IDs', function (): void {
        $validator = Validator::make(
            ['digital_assets' => ['not-an-id']],
            ['digital_assets' => [new App\Rules\DigitalAssetIsAttachableRule()]]
        );

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->first('digital_assets'))
            ->toEqual(__('validation.array_of_integer', ['attribute' => 'digital_assets']));
    });

    it('fails validation for empty array', function (): void {
        $validator = Validator::make(
            ['digital_assets' => []],
            ['digital_assets' => [new App\Rules\DigitalAssetIsAttachableRule()]]
        );

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->first('digital_assets'))
            ->toEqual(__('validation.array', ['attribute' => 'digital_assets']));
    });

    it('fails validation for non-attachable digital asset IDs', function (): void {
        $nonAttachableDigitalAsset = App\Models\DigitalAsset::factory()->create([
            'is_attachable_to_course' => false,
        ]);

        $validator = Validator::make(
            ['digital_assets' => [$nonAttachableDigitalAsset->id]],
            ['digital_assets' => [new App\Rules\DigitalAssetIsAttachableRule()]]
        );

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->first('digital_assets'))
            ->toEqual(__('validation.digital_asset.is_not_attachable', ['attribute' => 'digital_assets']));
    });
});
