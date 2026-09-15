<?php

declare(strict_types=1);

use App\Data\Admin\SelectOptions\ProductableSelectOptionData;
use App\Enums\Product\ProductableEnum;
use App\Http\Controllers\Api\Admin\SelectOptions\ProductableSelectOptionController;
use App\Models\Course;
use App\Models\DigitalAsset;
use App\Models\Seminar;

uses(Tests\Support\Traits\AuthTestTrait::class);

covers(ProductableSelectOptionController::class);
covers(ProductableSelectOptionData::class);

describe('Admin Producatable Select Option API', function (): void {
    beforeEach(function (): void {
        $this->authorized_user();
    });

    it('retrieves a list of productable items', function (): void {
        Course::factory()->create([
            'full_name'  => 'Advanced PHP Programming',
            'short_name' => 'AdvPHP',
            'slug'       => 'adv-php-programming',
        ]);
        Course::factory()->count(2)->create();
        Seminar::factory()->count(2)->create();
        DigitalAsset::factory()->count(2)->create();

        $response = $this->getJson('/api/v1/admin/select-option/productables');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'current_page',
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'subtitle',
                        'type',
                    ],
                ],
                'last_page',
                'per_page',
                'total',
            ],
        ]);
        $response->assertJsonFragment([
            'title'    => 'Advanced PHP Programming',
            'subtitle' => 'adv-php-programming',
            'type'     => [
                'value' => ProductableEnum::COURSE->value,
                'label' => ProductableEnum::COURSE->translate(),
            ],
        ]);
    });

    it('filters productable items by search query', function (): void {
        Course::factory()->count(3)->create();
        Seminar::factory()->count(2)->create();
        DigitalAsset::factory()->count(4)->create();
        Course::factory()->create([
            'full_name'  => 'Advanced PHP Programming',
            'short_name' => 'AdvPHP',
        ]);
        Seminar::factory()->create([
            'full_name'  => 'Advanced Web Development Seminar',
            'short_name' => 'AdvWebDev',
        ]);
        DigitalAsset::factory()->create([
            'short_name' => 'Advanced Design Patterns',
            'full_name'  => 'Design Patterns in Software Engineering',
        ]);

        $response = $this->getJson('/api/v1/admin/select-option/productables?q=advanced');

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data.data');
        $response->assertJsonStructure([
            'data' => [
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'subtitle',
                        'type',
                    ],
                ],
            ],
        ]);
    });

    it('paginates the union across every productable type', function (): void {
        Course::factory()->count(10)->create();
        Seminar::factory()->count(10)->create();
        DigitalAsset::factory()->count(5)->create();

        $firstPage = $this->getJson('/api/v1/admin/select-option/productables?per_page=15');

        $firstPage->assertStatus(200);
        $firstPage->assertJsonCount(15, 'data.data');
        $firstPage->assertJsonPath('data.current_page', 1);
        $firstPage->assertJsonPath('data.last_page', 2);
        $firstPage->assertJsonPath('data.total', 25);

        $secondPage = $this->getJson('/api/v1/admin/select-option/productables?per_page=15&page=2');

        $secondPage->assertStatus(200);
        $secondPage->assertJsonCount(10, 'data.data');
        $secondPage->assertJsonPath('data.current_page', 2);
    });

    it('limits the number of productable items returned', function (): void {
        Course::factory()->count(10)->create();
        Seminar::factory()->count(10)->create();
        $response = $this->getJson('/api/v1/admin/select-option/productables?per_page=5');

        $response->assertStatus(200);
        $this->assertCount(5, $response->json('data.data'));
    });

    it('filters productable items by types', function (): void {
        Course::factory()->count(10)->create();
        Seminar::factory()->count(10)->create();
        DigitalAsset::factory()->count(10)->create();
        $response = $this->getJson('/api/v1/admin/select-option/productables?types[]=course&types[]=seminar');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'subtitle',
                        'type',
                    ],
                ],
            ],
        ]);
        foreach ($response->json('data.data') as $item) {
            $this->assertTrue(in_array($item['type']['value'], [ProductableEnum::COURSE->value, ProductableEnum::SEMINAR->value]));
        }
    });

    it('returns empty data when no productable items match the criteria', function (): void {
        Course::factory()->count(3)->create();
        Seminar::factory()->count(2)->create();
        DigitalAsset::factory()->count(4)->create();

        $response = $this->getJson('/api/v1/admin/select-option/productables?q=nonexistentitem');

        $response->assertStatus(200);
        $response->assertJsonCount(0, 'data.data');
    });

    it('returns an empty page when no productable types are specified', function (): void {
        Course::factory()->count(3)->create();
        Seminar::factory()->count(2)->create();
        DigitalAsset::factory()->count(4)->create();

        $response = $this->getJson('/api/v1/admin/select-option/productables?types[]=');

        $response->assertStatus(200);
        $response->assertJsonCount(0, 'data.data');
        $response->assertJsonPath('data.total', 0);
        $response->assertJsonPath('data.path', route('api.v1.admin.select-option.productables'));
    });

    it('keeps the filters in the pagination links', function (): void {
        Course::factory()->create(['full_name' => 'Advanced Course One']);
        Course::factory()->create(['full_name' => 'Advanced Course Two']);

        $response = $this->getJson('/api/v1/admin/select-option/productables?q=advanced&per_page=1');

        $response->assertStatus(200);
        $response->assertJsonPath('data.total', 2);
        $response->assertJsonPath('data.last_page', 2);
        expect($response->json('data.next_page_url'))
            ->toContain('q=advanced')
            ->toContain('per_page=1')
            ->toContain('page=2');
    });

    it('falls back to the default page size when per_page is not positive', function (): void {
        Course::factory()->count(20)->create();

        $response = $this->getJson('/api/v1/admin/select-option/productables?per_page=0');

        $response->assertStatus(200);
        $response->assertJsonCount(15, 'data.data');
        $response->assertJsonPath('data.per_page', 15);
    });
});
