<?php

declare(strict_types=1);

use App\Enums\Content\PublicationStatusEnum;
use App\Enums\LearningPathStepActionStateEnum;
use App\Enums\Product\DeliveryMethodEnum;
use App\Enums\Product\FulfillmentTypeEnum;
use App\Http\Controllers\Api\Shop\LearningPath\LearningPathController;
use App\Models\Course;
use App\Models\LearningPath;
use App\Models\LearningPathStep;
use App\Models\Product;
use App\Models\Seminar;
use Tests\Support\Traits\ProductTestTrait;

covers(LearningPathController::class);

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
            'thumbnail_url' => 'https://example.test/first-path-thumbnail.png',
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

        $response = $this->getJson(route('api.v1.shop.learning-paths.index', ['per_page' => 2]));

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'data' => [
                        '*' => [
                            'title',
                            'slug',
                            'description',
                            'thumbnail_url',
                            'step_count',
                        ],
                    ],
                ],
                'metadata',
            ]);

        expect($response->json('data.data'))->toHaveCount(2)
            ->and($response->json('data.data.*.slug'))->toBe(['first-path', 'later-path'])
            ->and($response->json('data.per_page'))->toBe(2)
            ->and($response->json('data.data.0.thumbnail_url'))->toBe('https://example.test/first-path-thumbnail.png');
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
            'full_name'     => 'Coming soon course',
            'description'   => 'This fallback remains visible',
            'slug'          => 'coming-soon-course',
            'thumbnail_url' => 'https://example.test/coming-soon.png',
            'status'        => PublicationStatusEnum::DRAFT,
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
                            'position',
                            'productable_type',
                            'productable_id',
                            'title',
                            'description',
                            'product' => [
                                'productable_type',
                                'name',
                                'short_description',
                                'excerpt',
                                'slug',
                                'thumbnail_url',
                                'price',
                                'price_data',
                            ],
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
            ->and($steps[0]['product']['productable_type'])->toBe('course')
            ->and($steps[0]['product']['name'])->toBe('Current product title')
            ->and($steps[0]['product']['short_description'])->toBe('Current product excerpt')
            ->and($steps[0]['product']['excerpt'])->toBe('Current product excerpt')
            ->and($steps[0]['product']['slug'])->toBe('current-product-slug')
            ->and($steps[0]['product']['thumbnail_url'])->toBe('https://example.test/fallback.png')
            ->and($steps[0]['product']['price'])->toBe(1_200_000)
            ->and($steps[0]['product']['price_data']['min_price'])->toBe(1_200_000)
            ->and($steps[0]['action'])->toMatchArray([
                'type'  => 'view_product',
                'state' => [
                    'value' => LearningPathStepActionStateEnum::AVAILABLE->value,
                    'label' => LearningPathStepActionStateEnum::AVAILABLE->translate(),
                ],
                'enabled' => true,
            ])
            ->and($steps[1]['product']['productable_type'])->toBe('course')
            ->and($steps[1]['product']['name'])->toBe('Coming soon course')
            ->and($steps[1]['product']['short_description'])->toBe('This fallback remains visible')
            ->and($steps[1]['product']['excerpt'])->toBe('This fallback remains visible')
            ->and($steps[1]['product']['slug'])->toBe('coming-soon-course')
            ->and($steps[1]['product']['thumbnail_url'])->toBe('https://example.test/coming-soon.png')
            ->and($steps[1]['product']['price'])->toBeNull()
            ->and($steps[1]['product']['price_data'])->toBeNull()
            ->and($steps[1]['action'])->toMatchArray([
                'type'  => LearningPathStepActionStateEnum::COMING_SOON->actionType(),
                'state' => [
                    'value' => LearningPathStepActionStateEnum::COMING_SOON->value,
                    'label' => LearningPathStepActionStateEnum::COMING_SOON->translate(),
                ],
                'enabled' => false,
            ]);
    });

    it('resolves products by both productable type and id', function (): void {
        $course = Course::factory()->create([
            'full_name' => 'Course productable',
            'status'    => PublicationStatusEnum::PUBLISHED,
        ]);
        $seminar = Seminar::factory()->create([
            'full_name' => 'Seminar productable',
            'status'    => PublicationStatusEnum::PUBLISHED,
        ]);
        $courseProduct = Product::factory()
            ->withCourse($course)
            ->withDeliveryOptions(realData: [[
                'fulfillment_type' => FulfillmentTypeEnum::ONLINE_SERVICE,
                'delivery_method'  => DeliveryMethodEnum::LMS_MOODLE,
                'price'            => 900_000,
            ]])
            ->create([
                'name'       => 'Course product',
                'slug'       => 'course-product',
                'status'     => PublicationStatusEnum::PUBLISHED,
                'is_visible' => true,
            ]);
        $seminarProduct = Product::factory()
            ->withSeminar($seminar)
            ->withDeliveryOptions(realData: [[
                'fulfillment_type' => FulfillmentTypeEnum::ONLINE_SERVICE,
                'delivery_method'  => DeliveryMethodEnum::LMS_MOODLE,
                'price'            => 1_100_000,
            ]])
            ->create([
                'name'       => 'Seminar product',
                'slug'       => 'seminar-product',
                'status'     => PublicationStatusEnum::PUBLISHED,
                'is_visible' => true,
            ]);
        $this->indexProductPrice($courseProduct);
        $this->indexProductPrice($seminarProduct);

        $path = LearningPath::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]);
        LearningPathStep::factory()->create([
            'learning_path_id' => $path->id,
            'position'         => 1,
            'productable_type' => 'course',
            'productable_id'   => $course->id,
        ]);
        LearningPathStep::factory()->create([
            'learning_path_id' => $path->id,
            'position'         => 2,
            'productable_type' => 'seminar',
            'productable_id'   => $seminar->id,
        ]);

        $response = $this->getJson(route('api.v1.shop.learning-paths.show', ['slug' => $path->slug]));

        $response->assertOk();
        expect($response->json('data.steps.0.product'))->toMatchArray([
            'productable_type' => 'course',
            'name'             => 'Course product',
            'slug'             => 'course-product',
            'price'            => 900_000,
        ])
            ->and($response->json('data.steps.1.product'))->toMatchArray([
                'productable_type' => 'seminar',
                'name'             => 'Seminar product',
                'slug'             => 'seminar-product',
                'price'            => 1_100_000,
            ]);
    });

    it('returns an empty step list for a published path without steps', function (): void {
        $path = LearningPath::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]);

        $response = $this->getJson(route('api.v1.shop.learning-paths.show', ['slug' => $path->slug]));

        $response->assertOk()->assertJsonPath('data.steps', []);
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
        expect($response->json('data.steps.0.product.slug'))->toBe('upcoming-product')
            ->and($response->json('data.steps.0.product.price'))->toBe(800_000)
            ->and($response->json('data.steps.0.action'))->toMatchArray([
                'type'  => LearningPathStepActionStateEnum::UNAVAILABLE->actionType(),
                'state' => [
                    'value' => LearningPathStepActionStateEnum::UNAVAILABLE->value,
                    'label' => LearningPathStepActionStateEnum::UNAVAILABLE->translate(),
                ],
                'enabled' => false,
            ]);
    });
});
