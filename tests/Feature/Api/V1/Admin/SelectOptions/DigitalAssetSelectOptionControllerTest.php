<?php

declare(strict_types=1);

use App\Data\Admin\SelectOptions\DigitalAssetSelectOptionData;
use App\Enums\Content\PublicationStatusEnum;
use App\Http\Controllers\Api\Admin\SelectOptions\DigitalAssetSelectOptionController;
use App\Models\DigitalAsset;

uses(Tests\Support\Traits\AuthTestTrait::class);
uses(Tests\Support\Traits\FakeMediaTrait::class);

covers(DigitalAssetSelectOptionController::class);
covers(DigitalAssetSelectOptionData::class);

/*
|--------------------------------------------------------------------------
| Mutation notes
|--------------------------------------------------------------------------
|
| Survivor left after `pest --mutate --parallel` for this file, and why:
|
| - `DigitalAssetSelectOptionController` line 56 `RemoveArrayItem`:
|   `withMediaAndVariants([MediaTagEnum::MAIN->value])` → `withMediaAndVariants([])`.
|   Equivalent mutant: the DTO reads the file through
|   `DigitalAsset::getMedia('main')`, which filters the loaded media by pivot tag,
|   so loading every tag instead of only `main` produces an identical response
|   (it only eager-loads media rows the response never reads).
*/

describe('Admin Digital Asset Select Option API', function (): void {
    beforeEach(function (): void {
        $this->authorized_user();
    });

    it('returns published digital assets with the file type and size as subtitle', function (): void {
        $this->fakeMedia();
        $digitalAsset = DigitalAsset::factory()->withFile()->create([
            'full_name'     => 'Advanced PHP Programming',
            'thumbnail_url' => 'https://example.com/thumb.png',
        ]);

        $response = $this->getJson(route('api.v1.admin.select-option.digital-assets'));

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
            'id'        => $digitalAsset->id,
            'title'     => 'Advanced PHP Programming',
            'subtitle'  => 'SVG · 809 B',
            'image_url' => 'https://example.com/thumb.png',
        ]);
    });

    it('returns an empty subtitle and image when the asset has no main file or thumbnail', function (): void {
        $digitalAsset = DigitalAsset::factory()->create([
            'full_name'     => 'Asset without a file',
            'thumbnail_url' => null,
        ]);

        $response = $this->getJson(route('api.v1.admin.select-option.digital-assets'));

        $response->assertOk();
        $response->assertJsonFragment([
            'id'        => $digitalAsset->id,
            'subtitle'  => '',
            'image_url' => '',
        ]);
    });

    it('searches digital assets by full name and short name', function (): void {
        DigitalAsset::factory()->create([
            'full_name'  => 'Design Patterns in PHP',
            'short_name' => 'DP',
        ]);
        DigitalAsset::factory()->create([
            'full_name'  => 'Laravel Fundamentals',
            'short_name' => 'Advanced Laravel',
        ]);
        DigitalAsset::factory()->create([
            'full_name'  => 'Unrelated Asset',
            'short_name' => 'Unrelated',
        ]);

        $byFullName = $this->getJson(route('api.v1.admin.select-option.digital-assets', ['q' => 'patterns']));
        $byFullName->assertOk();
        $byFullName->assertJsonCount(1, 'data');
        $byFullName->assertJsonPath('data.0.title', 'Design Patterns in PHP');

        $byShortName = $this->getJson(route('api.v1.admin.select-option.digital-assets', ['q' => 'laravel']));
        $byShortName->assertOk();
        $byShortName->assertJsonCount(1, 'data');
        $byShortName->assertJsonPath('data.0.title', 'Laravel Fundamentals');
    });

    it('treats percent signs in the search query as literal characters', function (): void {
        DigitalAsset::factory()->create(['full_name' => 'Advanced PHP Programming']);

        $response = $this->getJson(route('api.v1.admin.select-option.digital-assets', ['q' => '%']));

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    });

    it('excludes digital assets that are not published', function (): void {
        DigitalAsset::factory()->create([
            'full_name' => 'Published Asset',
            'status'    => PublicationStatusEnum::PUBLISHED,
        ]);
        DigitalAsset::factory()->create([
            'full_name' => 'Draft Asset',
            'status'    => PublicationStatusEnum::DRAFT,
        ]);

        $response = $this->getJson(route('api.v1.admin.select-option.digital-assets'));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.title', 'Published Asset');
    });

    it('filters digital assets by attachability only when requested', function (): void {
        $attachable    = DigitalAsset::factory()->create(['full_name' => 'Attachable Asset']);
        $nonAttachable = DigitalAsset::factory()->nonAttachable()->create(['full_name' => 'Non Attachable Asset']);

        $attachableResponse = $this->getJson(
            route('api.v1.admin.select-option.digital-assets', ['is_attachable_to_course' => true])
        );
        $attachableResponse->assertOk();
        $attachableResponse->assertJsonCount(1, 'data');
        $attachableResponse->assertJsonPath('data.0.id', $attachable->id);

        $nonAttachableResponse = $this->getJson(
            route('api.v1.admin.select-option.digital-assets', ['is_attachable_to_course' => false])
        );
        $nonAttachableResponse->assertOk();
        $nonAttachableResponse->assertJsonCount(1, 'data');
        $nonAttachableResponse->assertJsonPath('data.0.id', $nonAttachable->id);

        $unfilteredResponse = $this->getJson(route('api.v1.admin.select-option.digital-assets'));
        $unfilteredResponse->assertOk();
        $unfilteredResponse->assertJsonCount(2, 'data');

        $emptyResponse = $this->getJson(
            route('api.v1.admin.select-option.digital-assets', ['is_attachable_to_course' => ''])
        );
        $emptyResponse->assertOk();
        $emptyResponse->assertJsonCount(2, 'data');
    });

    it('limits the number of digital assets returned', function (): void {
        DigitalAsset::factory()->count(12)->create();

        $defaultResponse = $this->getJson(route('api.v1.admin.select-option.digital-assets'));
        $defaultResponse->assertOk();
        $defaultResponse->assertJsonCount(10, 'data');

        $singleResponse = $this->getJson(route('api.v1.admin.select-option.digital-assets', ['limit' => 1]));
        $singleResponse->assertOk();
        $singleResponse->assertJsonCount(1, 'data');

        $limitedResponse = $this->getJson(route('api.v1.admin.select-option.digital-assets', ['limit' => 5]));
        $limitedResponse->assertOk();
        $limitedResponse->assertJsonCount(5, 'data');
    });

    it('returns empty data when no digital asset matches the search query', function (): void {
        DigitalAsset::factory()->count(3)->create();

        $response = $this->getJson(route('api.v1.admin.select-option.digital-assets', ['q' => 'nonexistentitem']));

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    });
});
