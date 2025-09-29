<?php

declare(strict_types=1);

use App\Enums\Product\ProductableEnum;
use App\Models\Course;
use App\Models\DigitalAsset;
use App\Models\Seminar;

uses(Tests\AuthTestTrait::class);
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

        $response = $this->getJson('/api/v1/admin/select-option/productable');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'title',
                    'subtitle',
                    'type',
                ],
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

        $response = $this->getJson('/api/v1/admin/select-option/productable?q=advanced');

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'title',
                    'subtitle',
                    'type',
                ],
            ],
        ]);
    });

    it('limits the number of productable items returned', function (): void {
        Course::factory()->count(10)->create();
        Seminar::factory()->count(10)->create();
        $response = $this->getJson('/api/v1/admin/select-option/productable?limit=5');

        $response->assertStatus(200);
        $this->assertCount(5, $response->json('data'));
    });

    it('filters productable items by types', function (): void {
        Course::factory()->count(10)->create();
        Seminar::factory()->count(10)->create();
        DigitalAsset::factory()->count(10)->create();
        $response = $this->getJson('/api/v1/admin/select-option/productable?types[]=course&types[]=seminar');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'title',
                    'subtitle',
                    'type',
                ],
            ],
        ]);
        foreach ($response->json('data') as $item) {
            $this->assertTrue(in_array($item['type']['value'], [ProductableEnum::COURSE->value, ProductableEnum::SEMINAR->value]));
        }
    });

    it('returns empty data when no productable items match the criteria', function (): void {
        Course::factory()->count(3)->create();
        Seminar::factory()->count(2)->create();
        DigitalAsset::factory()->count(4)->create();

        $response = $this->getJson('/api/v1/admin/select-option/productable?q=nonexistentitem');

        $response->assertStatus(200);
        $response->assertJsonCount(0, 'data');
    });

    it('returns empty data when no productable types are specified', function (): void {
        Course::factory()->count(3)->create();
        Seminar::factory()->count(2)->create();
        DigitalAsset::factory()->count(4)->create();

        $response = $this->getJson('/api/v1/admin/select-option/productable?types[]=');

        $response->assertStatus(200);
        $response->assertJsonCount(0, 'data');
    });
});
