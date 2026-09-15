<?php

declare(strict_types=1);

use App\Data\Admin\SelectOptions\CategorySelectOptionData;
use App\Http\Controllers\Api\Admin\SelectOptions\CategorySelectOptionController;

uses(Tests\Support\Traits\AuthTestTrait::class);

covers(CategorySelectOptionController::class);
covers(CategorySelectOptionData::class);

describe('Admin Category Select Option API', function (): void {
    it('returns filtered category select options', function (): void {
        $this->authorized_user();
        App\Models\Category::factory()->count(3)->create();
        App\Models\Category::factory()->create([
            'name'     => 'TestCategory',
            'slug'     => 'test-category',
            'icon_url' => 'http://example.com/icon.png',
        ]);
        $response = $this->getJson(
            route('api.v1.admin.select-option.categories', ['q' => 'TestCategory'])
        );

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'current_page',
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'subtitle',
                        'image_url',
                    ],
                ],
                'per_page',
                'total',
            ],
        ]);
        $response->assertJsonFragment([
            'title'     => 'TestCategory',
            'subtitle'  => 'test-category',
            'image_url' => 'http://example.com/icon.png',
        ]);
    });

    it('returns empty data if no match', function (): void {
        $this->authorized_user();
        $response = $this->getJson(
            route('api.v1.admin.select-option.categories', ['q' => 'NoSuchCategory'])
        );
        $response->assertOk();
        $response->assertJsonCount(0, 'data.data');
    });

    it('matches categories by a partial name or slug', function (): void {
        $this->authorized_user();
        App\Models\Category::factory()->create(['name' => 'TestCategory Extra', 'slug' => 'test-extra']);
        App\Models\Category::factory()->create(['name' => 'Something Else', 'slug' => 'my-category-slug']);
        App\Models\Category::factory()->create(['name' => 'Unrelated', 'slug' => 'unrelated']);

        $response = $this->getJson(
            route('api.v1.admin.select-option.categories', ['q' => 'Category'])
        );

        $response->assertOk();
        $response->assertJsonCount(2, 'data.data');
        $response->assertJsonFragment(['title' => 'TestCategory Extra']);
        $response->assertJsonFragment(['subtitle' => 'my-category-slug']);
    });
});
