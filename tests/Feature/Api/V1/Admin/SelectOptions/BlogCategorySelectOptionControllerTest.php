<?php

declare(strict_types=1);

use App\Data\Admin\SelectOptions\BlogCategorySelectOptionData;
use App\Http\Controllers\Api\Admin\SelectOptions\BlogCategorySelectOptionController;
use App\Models\Blog\BlogCategory;

uses(Tests\Support\Traits\AuthTestTrait::class);

covers(BlogCategorySelectOptionController::class);
covers(BlogCategorySelectOptionData::class);

describe('Admin Blog Category Select Option API', function (): void {
    beforeEach(function (): void {
        $this->authorized_user();
    });

    it('returns blog category select options', function (): void {
        BlogCategory::factory()->count(3)->create();
        BlogCategory::factory()->create([
            'name' => 'TestBlogCategory',
            'slug' => 'test-blog-category',
            'icon' => 'http://example.com/icon.png',
        ]);

        $response = $this->getJson(
            route('api.v1.admin.select-option.blog-categories', ['q' => 'TestBlogCategory'])
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
                        'icon_url',
                    ],
                ],
                'last_page',
                'per_page',
                'total',
            ],
        ]);
        $response->assertJsonFragment([
            'title'    => 'TestBlogCategory',
            'subtitle' => 'test-blog-category',
            'icon_url' => 'http://example.com/icon.png',
        ]);
    });

    it('paginates the blog categories returned', function (): void {
        BlogCategory::factory()->count(20)->create();

        $response = $this->getJson(route('api.v1.admin.select-option.blog-categories'));

        $response->assertOk();
        $response->assertJsonCount(15, 'data.data');
        $response->assertJsonPath('data.per_page', 15);
        $response->assertJsonPath('data.total', 20);
        $response->assertJsonPath('data.last_page', 2);
    });

    it('returns empty data if no match', function (): void {
        $response = $this->getJson(
            route('api.v1.admin.select-option.blog-categories', ['q' => 'NoSuchBlogCategory'])
        );

        $response->assertOk();
        $response->assertJsonCount(0, 'data.data');
        $response->assertJsonPath('data.total', 0);
    });
});
