<?php

declare(strict_types=1);

use App\Enums\Content\PublicationStatusEnum;
use App\Enums\Product\DeliveryMethodEnum;
use App\Enums\Product\FulfillmentTypeEnum;
use App\Models\Course;
use App\Models\LearningPath;
use App\Models\LearningPathStep;
use App\Models\Product;
use Tests\Support\Traits\ProductTestTrait;

covers(
    App\Actions\Shop\LearningPath\ListPublishedLearningPathsAction::class,
    App\Actions\Shop\LearningPath\ShowPublishedLearningPathAction::class,
    App\Http\Controllers\Api\Shop\LearningPath\LearningPathController::class,
);

mutates(
    App\Actions\Shop\LearningPath\ListPublishedLearningPathsAction::class,
    App\Actions\Shop\LearningPath\ShowPublishedLearningPathAction::class,
);

describe('Shop Learning Path API', function (): void {
    uses(ProductTestTrait::class);

    it('lists only published paths in explicit display order', function (): void {
        $laterPath = LearningPath::factory()->create([
            'title'         => 'Later path',
            'slug'          => 'later-path',
            'display_order' => 20,
            'status'        => PublicationStatusEnum::PUBLISHED,
        ]);
        LearningPathStep::factory()->create(['learning_path_id' => $laterPath->id]);

        $firstPath = LearningPath::factory()->create([
            'title'         => 'First path',
            'slug'          => 'first-path',
            'display_order' => 10,
            'status'        => PublicationStatusEnum::PUBLISHED,
        ]);
        LearningPathStep::factory()->create(['learning_path_id' => $firstPath->id]);

        LearningPath::factory()->create([
            'slug'          => 'draft-path',
            'display_order' => 1,
            'status'        => PublicationStatusEnum::DRAFT,
        ]);
        LearningPath::factory()->create([
            'slug'          => 'archived-path',
            'display_order' => 2,
            'status'        => PublicationStatusEnum::ARCHIVED,
        ]);

        $response = $this->getJson(route('api.v1.shop.learning-paths.index'));

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'title',
                            'slug',
                            'description',
                            'media',
                            'step_count',
                        ],
                    ],
                ],
                'metadata',
            ]);

        expect($response->json('data.data'))->toHaveCount(2)
            ->and($response->json('data.data.*.slug'))->toBe(['first-path', 'later-path']);
    });

    it('returns a published path with editorial content and catalog-aware ordered steps', function (): void {
        $course = Course::factory()->create([
            'full_name'     => 'Productable fallback title',
            'description'   => 'Productable fallback excerpt',
            'thumbnail_url' => 'https://example.test/fallback.png',
            'status'        => PublicationStatusEnum::PUBLISHED,
        ]);
        $product = Product::factory()
            ->withCourse($course)
            ->withDeliveryOptions(realData: [[
                'fulfillment_type' => FulfillmentTypeEnum::ONLINE_SERVICE,
                'delivery_method'  => DeliveryMethodEnum::LMS_MOODLE,
                'price'            => 1_200_000,
            ]])
            ->create([
                'name'              => 'Current product title',
                'short_description' => 'Current product excerpt',
                'slug'              => 'current-product-slug',
                'status'            => PublicationStatusEnum::PUBLISHED,
                'is_visible'        => true,
            ]);
        $this->indexProductPrice($product);

        $unpublishedCourse = Course::factory()->create([
            'full_name'   => 'Coming soon course',
            'description' => 'This fallback remains visible',
            'status'      => PublicationStatusEnum::DRAFT,
        ]);
        $path = LearningPath::factory()->create([
            'title'                    => 'Backend path',
            'slug'                     => 'backend-path',
            'description'              => 'A curated backend route',
            'introduction_title'       => 'Start here',
            'introduction_description' => 'The path introduction',
            'conclusion_title'         => 'Finish here',
            'conclusion_description'   => 'The path conclusion',
            'meta_title'               => 'Backend SEO title',
            'meta_description'         => 'Backend SEO description',
            'meta_keywords'            => 'backend, api',
            'status'                   => PublicationStatusEnum::PUBLISHED,
        ]);
        LearningPathStep::factory()->create([
            'learning_path_id' => $path->id,
            'position'         => 2,
            'productable_type' => 'course',
            'productable_id'   => $unpublishedCourse->id,
            'title'            => 'Second step',
            'description'      => 'Second editorial description',
        ]);
        LearningPathStep::factory()->create([
            'learning_path_id' => $path->id,
            'position'         => 1,
            'productable_type' => 'course',
            'productable_id'   => $course->id,
            'title'            => 'First step',
            'description'      => 'First editorial description',
        ]);

        $response = $this->getJson(route('api.v1.shop.learning-paths.show', ['slug' => $path->slug]));

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'title',
                    'slug',
                    'description',
                    'introduction_title',
                    'introduction_description',
                    'conclusion_title',
                    'conclusion_description',
                    'meta_title',
                    'meta_description',
                    'meta_keywords',
                    'media',
                    'steps' => [
                        '*' => [
                            'id',
                            'position',
                            'productable_type',
                            'productable_id',
                            'title',
                            'description',
                            'productable' => [
                                'id',
                                'productable_type',
                                'name',
                                'short_description',
                                'excerpt',
                                'slug',
                                'thumbnail_url',
                                'media',
                            ],
                            'current_product',
                            'action' => [
                                'type',
                                'state',
                                'enabled',
                            ],
                        ],
                    ],
                ],
                'metadata',
            ]);

        $steps = $response->json('data.steps');
        expect($response->json('data.introduction_title'))->toBe('Start here')
            ->and($response->json('data.conclusion_description'))->toBe('The path conclusion')
            ->and(array_column($steps, 'position'))->toBe([1, 2])
            ->and($steps[0]['title'])->toBe('First step')
            ->and($steps[0]['productable']['name'])->toBe('Productable fallback title')
            ->and($steps[0]['productable']['short_description'])->toBe('Productable fallback excerpt')
            ->and($steps[0]['current_product']['id'])->toBe($product->id)
            ->and($steps[0]['current_product']['name'])->toBe('Current product title')
            ->and($steps[0]['current_product']['short_description'])->toBe('Current product excerpt')
            ->and($steps[0]['current_product']['slug'])->toBe('current-product-slug')
            ->and($steps[0]['current_product']['price'])->toBe(1_200_000)
            ->and($steps[0]['current_product']['price_data']['min_price'])->toBe(1_200_000)
            ->and($steps[0]['action'])->toMatchArray([
                'type'    => 'view_product',
                'state'   => 'available',
                'enabled' => true,
            ])
            ->and($steps[1]['productable']['name'])->toBe('Coming soon course')
            ->and($steps[1]['productable']['short_description'])->toBe('This fallback remains visible')
            ->and($steps[1]['current_product'])->toBeNull()
            ->and($steps[1]['action'])->toMatchArray([
                'type'    => 'coming_soon',
                'state'   => 'coming_soon',
                'enabled' => false,
            ]);
    });

    it('does not resolve draft or archived paths publicly', function (): void {
        $draft = LearningPath::factory()->create([
            'slug'   => 'draft-path',
            'status' => PublicationStatusEnum::DRAFT,
        ]);
        $archived = LearningPath::factory()->create([
            'slug'   => 'archived-path',
            'status' => PublicationStatusEnum::ARCHIVED,
        ]);

        $this->getJson(route('api.v1.shop.learning-paths.show', ['slug' => $draft->slug]))
            ->assertNotFound();
        $this->getJson(route('api.v1.shop.learning-paths.show', ['slug' => $archived->slug]))
            ->assertNotFound();
    });

    it('keeps a resolved product visible but disables its action when catalog availability is closed', function (): void {
        $course  = Course::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]);
        $product = Product::factory()
            ->withCourse($course)
            ->withDeliveryOptions(realData: [[
                'fulfillment_type' => FulfillmentTypeEnum::ONLINE_SERVICE,
                'delivery_method'  => DeliveryMethodEnum::LMS_MOODLE,
                'price'            => 800_000,
                'available_from'   => now()->addDay(),
            ]])
            ->create([
                'slug'       => 'upcoming-product',
                'status'     => PublicationStatusEnum::PUBLISHED,
                'is_visible' => true,
            ]);
        $this->indexProductPrice($product);

        $path = LearningPath::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]);
        LearningPathStep::factory()->create([
            'learning_path_id' => $path->id,
            'productable_type' => 'course',
            'productable_id'   => $course->id,
        ]);

        $response = $this->getJson(route('api.v1.shop.learning-paths.show', ['slug' => $path->slug]));

        $response->assertOk();
        expect($response->json('data.steps.0.current_product.slug'))->toBe('upcoming-product')
            ->and($response->json('data.steps.0.current_product.price'))->toBe(800_000)
            ->and($response->json('data.steps.0.action'))->toMatchArray([
                'type'    => 'view_product',
                'state'   => 'unavailable',
                'enabled' => false,
            ]);
    });
});
