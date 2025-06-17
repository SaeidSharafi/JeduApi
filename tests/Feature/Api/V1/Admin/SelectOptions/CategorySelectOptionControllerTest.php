<?php

declare(strict_types=1);

uses(Tests\AuthTestTrait::class);
describe('Admin Category Select Option API', function () {
    it('returns filtered category select options', function () {
        $this->authorized_user();
        App\Models\Category::factory()->count(3)->create();
        App\Models\Category::factory()->create([
            'name'     => 'TestCategory',
            'slug'     => 'test-category',
            'icon_url' => 'http://example.com/icon.png',
        ]);
        $response = $this->getJson(
            route('api.v1.admin.select-option.category', ['q' => 'TestCategory'])
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
            'title'     => 'TestCategory',
            'subtitle'  => 'test-category',
            'image_url' => 'http://example.com/icon.png',
        ]);
    });

    it('returns empty data if no match', function () {
        $this->authorized_user();
        $response = $this->getJson(
            route('api.v1.admin.select-option.category', ['q' => 'NoSuchCategory'])
        );
        $response->assertOk();
        $response->assertJson(['data' => []]);
    });
});
