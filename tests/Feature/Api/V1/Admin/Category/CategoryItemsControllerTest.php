<?php

declare(strict_types=1);

use App\Enums\System\MorphTypeEnum;

uses(Tests\Support\Traits\AuthTestTrait::class);
describe('CategoryItemsController', function (): void {
    it('list and filter items', function (): void {
        $category     = App\Models\Category::factory()->create();
        $course       = App\Models\Course::factory()->count(5)->create();
        $seminar      = App\Models\Seminar::factory()->count(5)->create();
        $digitalAsset = App\Models\DigitalAsset::factory()->count(5)->create();

        $category->courses()->attach($course[0]->id, ['good_for_start' => true]);
        $category->courses()->attach($course[1]->id, ['good_for_start' => false]);
        $category->seminars()->attach($seminar[1]->id, ['good_for_start' => true]);
        $category->seminars()->attach($seminar[2]->id, ['good_for_start' => false]);
        $category->digitalAssets()->attach($digitalAsset[1]->id, ['good_for_start' => false]);
        $category->digitalAssets()->attach($digitalAsset[2]->id, ['good_for_start' => false]);

        $this->authorized_user([App\Enums\PermissionEnum::CATEGORY_VIEW]);

        // Test listing items without filters
        $response = $this->getJson("/api/v1/admin/category/{$category->id}/items");
        $response->assertStatus(200);
        $responseData = $response->json('data.data');
        expect(count($responseData))->toBe(6);
        foreach ($responseData as $item) {
            expect($item)->toHaveKey('categorizable_type')
                ->and($item)->toHaveKey('categorizable_id')
                ->and($item)->toHaveKey('good_for_start')
                ->and($item)->toHaveKey('categorizable_name');
        }

        // Test filtering by single categorizable_type
        $response = $this->getJson("/api/v1/admin/category/{$category->id}/items?filter[categorizable_type]=course");
        $response->assertStatus(200);
        $responseData = $response->json('data.data');
        expect(count($responseData))->toBe(2); // Only coursees
        foreach ($responseData as $item) {
            expect($item['categorizable_type'])->toBe(MorphTypeEnum::COURSE->translate());
        }
        // Test filtering by mutliple categorizable_type
        $response
            = $this->getJson("/api/v1/admin/category/{$category->id}/items?filter[categorizable_type][]=course&filter[categorizable_type][]=seminar");
        $response->assertStatus(200);
        $responseData = $response->json('data.data');
        expect(count($responseData))->toBe(4); // 2 courses + 2 seminars
        foreach ($responseData as $item) {
            expect(in_array($item['categorizable_type'], [
                MorphTypeEnum::COURSE->translate(), MorphTypeEnum::SEMINAR->translate(),
            ]))->toBeTrue();
        }

        // Test filtering by good_for_start
        $response = $this->getJson("/api/v1/admin/category/{$category->id}/items?filter[good_for_start]=true");
        $response->assertStatus(200);
        $responseData = $response->json('data.data');
        expect(count($responseData))->toBe(2); // 1 seminar + 1 course with good_for_start = true
        foreach ($responseData as $item) {
            expect($item['good_for_start'])->toBe(true);
        }
    });
});
