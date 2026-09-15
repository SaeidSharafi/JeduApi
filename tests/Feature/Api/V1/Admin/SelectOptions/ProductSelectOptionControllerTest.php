<?php

declare(strict_types=1);

use App\Data\Admin\SelectOptions\ProductSelectOptionData;
use App\Enums\Product\ProductableEnum;
use App\Http\Controllers\Api\Admin\SelectOptions\ProductSelectOptionController;
use App\Models\Product;

uses(Tests\Support\Traits\AuthTestTrait::class);

covers(ProductSelectOptionController::class);
covers(ProductSelectOptionData::class);

describe('Admin Product Select Option API', function (): void {
    beforeEach(function (): void {
        $this->authorized_user();
    });

    it('retrieves a list of products', function (): void {
        Product::factory()
            ->withDeliveryOptions(1)
            ->withCourse()
            ->create([
                'short_name' => '0Advanced PHP Programming',
                'slug'       => 'adv-php-programming',
            ]);
        Product::factory()
            ->withDeliveryOptions(1)
            ->count(25)
            ->create();

        $response = $this->getJson(route('api.v1.admin.select-option.products'));

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
            'title'    => '0Advanced PHP Programming',
            'subtitle' => 'adv-php-programming',
            'type'     => [
                'value' => ProductableEnum::COURSE->value,
                'label' => ProductableEnum::COURSE->translate(),
            ],
        ]);
    });

    it('filters productable items by search query', function (): void {
        Product::factory()
            ->withDeliveryOptions(1)
            ->count(25)
            ->create();
        Product::factory()->withDeliveryOptions(1)->create([
            'name'       => 'Advanced PHP Programming',
            'short_name' => 'AdvPHP',
        ]);
        Product::factory()->withDeliveryOptions(1)->create([
            'name'       => 'Advanced Web Development Seminar',
            'short_name' => 'AdvWebDev',
        ]);
        Product::factory()->withDeliveryOptions(1)->create([
            'name'       => 'Design Patterns in Software Engineering',
            'short_name' => 'Advanced Design Patterns',
        ]);

        $response = $this->getJson(route('api.v1.admin.select-option.products', ['q' => 'advanced']));

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

    it('paginates the products returned', function (): void {
        Product::factory()->withDeliveryOptions(1)->count(20)->create();

        $defaultResponse = $this->getJson(route('api.v1.admin.select-option.products'));
        $defaultResponse->assertStatus(200);
        $defaultResponse->assertJsonCount(15, 'data.data');
        $defaultResponse->assertJsonPath('data.per_page', 15);
        $defaultResponse->assertJsonPath('data.total', 20);

        $limitedResponse = $this->getJson(route('api.v1.admin.select-option.products', ['per_page' => 5]));
        $limitedResponse->assertStatus(200);
        $limitedResponse->assertJsonCount(5, 'data.data');

        $secondPage = $this->getJson(
            route('api.v1.admin.select-option.products', ['per_page' => 15, 'page' => 2])
        );
        $secondPage->assertStatus(200);
        $secondPage->assertJsonCount(5, 'data.data');
        $secondPage->assertJsonPath('data.current_page', 2);
    });

    it('filters productable items by types', function (): void {
        Product::factory()
            ->withDeliveryOptions(1)
            ->withCourse()
            ->count(10)
            ->create();
        Product::factory()
            ->withDeliveryOptions(1)
            ->withSeminar()
            ->count(10)
            ->create();
        Product::factory()
            ->withDeliveryOptions(1)
            ->withDigitalAsset()
            ->count(10)
            ->create();
        $response = $this->getJson(route('api.v1.admin.select-option.products', ['productableType' => 'course']));
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
            expect($item['type']['value'])->toBe(ProductableEnum::COURSE->value);
        }

        $response = $this->getJson(route('api.v1.admin.select-option.products', ['productableType' => 'seminar']));
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
            expect($item['type']['value'])->toBe(ProductableEnum::SEMINAR->value);
        }

        $response = $this->getJson(route('api.v1.admin.select-option.products', ['productableType' => 'digital_asset']));
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
            expect($item['type']['value'])->toBe(ProductableEnum::DIGITAL_ASSET->value);
        }
    });

    it('returns empty data when no productable items match the criteria', function (): void {
        Product::factory()->withDeliveryOptions(1)->count(4)->create();

        $response = $this->getJson(route('api.v1.admin.select-option.products', ['q' => 'nonexistentitem']));

        $response->assertStatus(200);
        $response->assertJsonCount(0, 'data.data');
    });
});
