<?php

declare(strict_types=1);

use App\Enums\Content\DynamicListEntityTypeEnum;
use App\Enums\Content\DynamicListSortByEnum;
use App\Enums\Content\HomePageBlockTypeEnum;
use App\Enums\PermissionEnum;
use App\Enums\Product\ProductableEnum;
use App\Models\HomePageBlock;

uses(Tests\Support\Traits\AuthTestTrait::class);
beforeEach(function (): void {
    Illuminate\Http\UploadedFile::fake();
    Storage::fake('public');
    $this->image = MediaUploader::fromSource(Illuminate\Http\UploadedFile::fake()->image('banner.jpg'))
        ->toDisk('public')
        ->upload();

});
describe('HomePageBlockController CRUD', function (): void {
    it('can list home page blocks', function (): void {
        $this->authorized_user([PermissionEnum::HOME_PAGE_BLOCK_VIEW_ANY]);
        HomePageBlock::factory()
            ->banner($this->image)
            ->create();
        HomePageBlock::factory()
            ->webinarBanner($this->image, 1)
            ->create();
        HomePageBlock::factory()
            ->curatedList()
            ->create();
        HomePageBlock::factory()
            ->curatedList(typeEnum: HomePageBlockTypeEnum::MAIN_CATEGORIES)
            ->create();
        $response = $this->getJson(route('api.v1.admin.settings.home-page-block.index'));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'data' => [
                        '*' => ['id', 'type', 'title', 'location', 'order', 'is_active', 'content'],
                    ],
                ],
            ]);
        $responseData = $response->json('data.data');
        expect(count($responseData))->toBe(4);
    });
    it('can create a banner home page block successfully', function (): void {
        $this->authorized_user([PermissionEnum::HOME_PAGE_BLOCK_CREATE]);
        $payload = [
            'type'      => HomePageBlockTypeEnum::BANNER->value,
            'title'     => 'Banner Block',
            'location'  => 'homepage_top',
            'order'     => 1,
            'is_active' => true,
            'content'   => [
                'image_id'     => $this->image->id,
                'action'       => 'go_to_shop',
                'action_title' => 'Shop Now',
                'content'      => 'Banner Content',
                'preset'       => 'default',
            ],
        ];
        $response = $this->postJson(route('api.v1.admin.settings.home-page-block.store'), $payload);
        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id', 'type', 'title', 'location', 'order', 'is_active', 'content' => [
                        'image_id', 'image_url', 'action', 'action_title', 'content', 'preset',
                    ],
                ],
            ]);
        $responseData = $response->json('data');
        expect($responseData['content']['image_url'])->toBe($this->image->getUrl());
    });
    it('admin can view a specific home page block', function (): void {
        $this->authorized_user([PermissionEnum::HOME_PAGE_BLOCK_VIEW]);
        $block    = HomePageBlock::factory()->banner($this->image)->create();
        $response = $this->getJson(route('api.v1.admin.settings.home-page-block.show', $block->id));
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id', 'type', 'title', 'location', 'order', 'is_active', 'content' => [
                        'image_id', 'image_url', 'action', 'action_title', 'content', 'preset',
                    ],
                ],
            ]);
        $responseData = $response->json('data');
        expect($responseData['id'])->toBe($block->id);
        expect($responseData['content']['image_url'])->toBe($this->image->getUrl());
    });
    it('admin can update a home page block', function (): void {
        $this->authorized_user([PermissionEnum::HOME_PAGE_BLOCK_UPDATE]);
        $block    = HomePageBlock::factory()->banner($this->image)->create();
        $newImage = MediaUploader::fromSource(Illuminate\Http\UploadedFile::fake()->image('new_banner.jpg'))
            ->toDisk('public')
            ->upload();
        $payload = [
            'type'      => HomePageBlockTypeEnum::BANNER->value,
            'title'     => 'Updated Banner Block',
            'location'  => 'homepage_bottom',
            'order'     => 10,
            'is_active' => false,
            'content'   => [
                'image_id'     => $newImage->id,
                'action'       => 'visit_us',
                'action_title' => 'Visit Now',
                'content'      => 'Updated Content',
                'preset'       => 'alternative',
            ],
        ];
        $response = $this->putJson(route('api.v1.admin.settings.home-page-block.update', $block->id), $payload);
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id', 'type', 'title', 'location', 'order', 'is_active', 'content' => [
                        'image_id', 'image_url', 'action', 'action_title', 'content', 'preset',
                    ],
                ],
            ]);
        $responseData = $response->json('data');
        expect($responseData['id'])->toBe($block->id)
            ->and($responseData['title'])->toBe('Updated Banner Block')
            ->and($responseData['location'])->toBe('homepage_bottom')
            ->and($responseData['order'])->toBe(10)
            ->and($responseData['is_active'])->toBeFalse()
            ->and($responseData['content']['image_id'])->toBe($newImage->id)
            ->and($responseData['content']['image_url'])->toBe($newImage->getUrl())
            ->and($responseData['content']['action'])->toBe('visit_us')
            ->and($responseData['content']['action_title'])->toBe('Visit Now')
            ->and($responseData['content']['content'])->toBe('Updated Content')
            ->and($responseData['content']['preset'])->toBe('alternative');
    });
    it('admin can delete a home page block', function (): void {
        $this->authorized_user([PermissionEnum::HOME_PAGE_BLOCK_DELETE]);
        $block = HomePageBlock::factory()->banner($this->image)->create();
        $block->attachMedia($this->image, 'image');
        $response = $this->deleteJson(route('api.v1.admin.settings.home-page-block.destroy', $block->id));
        $response->assertStatus(204);
        $this->assertDatabaseMissing('home_page_blocks', ['id' => $block->id]);
        $this->assertDatabaseMissing('media', ['id' => $this->image->id]);
    });
    it('unauthorized user cannot access home page block endpoints', function (): void {
        $this->unauthorized_user();
        $block         = HomePageBlock::factory()->banner($this->image)->create();
        $blockData     = HomePageBlock::factory()->banner($this->image)->make()->toArray();
        $responseIndex = $this->getJson(route('api.v1.admin.settings.home-page-block.index'));
        $responseIndex->assertStatus(403);
        $responseStore = $this->postJson(route('api.v1.admin.settings.home-page-block.store'), $blockData);
        $responseStore->assertStatus(403);
        $responseShow = $this->getJson(route('api.v1.admin.settings.home-page-block.show', $block->id));
        $responseShow->assertStatus(403);
        $responseUpdate = $this->putJson(route('api.v1.admin.settings.home-page-block.update', $block->id), $blockData);
        $responseUpdate->assertStatus(403);
        $responseDelete = $this->deleteJson(route('api.v1.admin.settings.home-page-block.destroy', $block->id));
        $responseDelete->assertStatus(403);
    });
});

describe('HomePageBlockController validation', function (): void {
    it('validation fails if required fields are missing', function (): void {
        $this->authorized_user([PermissionEnum::HOME_PAGE_BLOCK_CREATE]);
        $payload = [
            'type'      => '',
            'title'     => '',
            'location'  => '',
            'order'     => null,
            'is_active' => null,
            'content'   => null,
        ];
        $response = $this->postJson(route('api.v1.admin.settings.home-page-block.store'), $payload);
        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'type', 'title', 'location', 'order', 'is_active', 'content',
            ]);
    });
    it('validation fails if type is not a valid enum value', function (): void {
        $this->authorized_user([PermissionEnum::HOME_PAGE_BLOCK_CREATE]);
        $payload = [
            'type'      => 'INVALID_TYPE',
            'title'     => 'Banner Block',
            'location'  => 'homepage_top',
            'order'     => 1,
            'is_active' => true,
            'content'   => [
                'image_id'     => $this->image->id,
                'action'       => 'go_to_shop',
                'action_title' => 'Shop Now',
                'content'      => 'Banner Content',
                'preset'       => 'default',
            ],
        ];
        $response = $this->postJson(route('api.v1.admin.settings.home-page-block.store'), $payload);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['type']);
    });
    it('validation fails if order is negative', function (): void {
        $this->authorized_user([PermissionEnum::HOME_PAGE_BLOCK_CREATE]);
        $payload = [
            'type'      => HomePageBlockTypeEnum::BANNER->value,
            'title'     => 'Banner Block',
            'location'  => 'homepage_top',
            'order'     => -1,
            'is_active' => true,
            'content'   => [
                'image_id'     => $this->image->id,
                'action'       => 'go_to_shop',
                'action_title' => 'Shop Now',
                'content'      => 'Banner Content',
                'preset'       => 'default',
            ],
        ];
        $response = $this->postJson(route('api.v1.admin.settings.home-page-block.store'), $payload);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['order']);
    });
    it('validation fails if is_active is not boolean', function (): void {
        $this->authorized_user([PermissionEnum::HOME_PAGE_BLOCK_CREATE]);
        $payload = [
            'type'      => HomePageBlockTypeEnum::BANNER->value,
            'title'     => 'Banner Block',
            'location'  => 'homepage_top',
            'order'     => 1,
            'is_active' => 'not_a_boolean',
            'content'   => [
                'image_id'     => $this->image->id,
                'action'       => 'go_to_shop',
                'action_title' => 'Shop Now',
                'content'      => 'Banner Content',
                'preset'       => 'default',
            ],
        ];
        $response = $this->postJson(route('api.v1.admin.settings.home-page-block.store'), $payload);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['is_active']);
    });
    it('validation fails if content is missing required keys', function (): void {
        $this->authorized_user([PermissionEnum::HOME_PAGE_BLOCK_CREATE]);
        $payload = [
            'type'      => HomePageBlockTypeEnum::BANNER->value,
            'title'     => 'Banner Block',
            'location'  => 'homepage_top',
            'order'     => 1,
            'is_active' => true,
            'content'   => [
                // 'image_id' missing
                'action'       => 'go_to_shop',
                'action_title' => 'Shop Now',
                'content'      => 'Banner Content',
                'preset'       => 'default',
            ],
        ];
        $response = $this->postJson(route('api.v1.admin.settings.home-page-block.store'), $payload);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['content.image_id']);
    });
    it('validation fails if image_id is not an integer', function (): void {
        $this->authorized_user([PermissionEnum::HOME_PAGE_BLOCK_CREATE]);
        $payload = [
            'type'      => HomePageBlockTypeEnum::BANNER->value,
            'title'     => 'Banner Block',
            'location'  => 'homepage_top',
            'order'     => 1,
            'is_active' => true,
            'content'   => [
                'image_id'     => 'not_an_integer',
                'action'       => 'go_to_shop',
                'action_title' => 'Shop Now',
                'content'      => 'Banner Content',
                'preset'       => 'default',
            ],
        ];
        $response = $this->postJson(route('api.v1.admin.settings.home-page-block.store'), $payload);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['content.image_id']);
    });
    it('validation fails for minimum and maximum string lengths for title', function (): void {
        $this->authorized_user([PermissionEnum::HOME_PAGE_BLOCK_CREATE]);
        $minTitle   = '';
        $maxTitle   = str_repeat('a', 256); // assuming max is 255
        $payloadMin = [
            'type'      => HomePageBlockTypeEnum::BANNER->value,
            'title'     => $minTitle,
            'location'  => 'homepage_top',
            'order'     => 1,
            'is_active' => true,
            'content'   => [
                'image_id'     => $this->image->id,
                'action'       => 'go_to_shop',
                'action_title' => 'Shop Now',
                'content'      => 'Banner Content',
                'preset'       => 'default',
            ],
        ];
        $payloadMax = [
            'type'      => HomePageBlockTypeEnum::BANNER->value,
            'title'     => $maxTitle,
            'location'  => 'homepage_top',
            'order'     => 1,
            'is_active' => true,
            'content'   => [
                'image_id'     => $this->image->id,
                'action'       => 'go_to_shop',
                'action_title' => 'Shop Now',
                'content'      => 'Banner Content',
                'preset'       => 'default',
            ],
        ];
        $responseMin = $this->postJson(route('api.v1.admin.settings.home-page-block.store'), $payloadMin);
        $responseMin->assertStatus(422)
            ->assertJsonValidationErrors(['title']);
        $responseMax = $this->postJson(route('api.v1.admin.settings.home-page-block.store'), $payloadMax);
        $responseMax->assertStatus(422)
            ->assertJsonValidationErrors(['title']);
    });
    it('validation fails if image_id does not exist', function (): void {
        $this->authorized_user([PermissionEnum::HOME_PAGE_BLOCK_CREATE]);
        $payload = [
            'type'      => HomePageBlockTypeEnum::BANNER->value,
            'title'     => 'Banner Block',
            'location'  => 'homepage_top',
            'order'     => 1,
            'is_active' => true,
            'content'   => [
                'image_id'     => 999999,
                'action'       => 'go_to_shop',
                'action_title' => 'Shop Now',
                'content'      => 'Banner Content',
                'preset'       => 'default',
            ],
        ];
        $response = $this->postJson(route('api.v1.admin.settings.home-page-block.store'), $payload);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['content.image_id']);
    });
    it('validation fails for dynamic list with invalid entity_type', function (): void {
        $this->authorized_user([PermissionEnum::HOME_PAGE_BLOCK_CREATE]);
        $payload = [
            'type'      => HomePageBlockTypeEnum::DYNAMIC_LIST->value,
            'title'     => 'Dynamic List Block',
            'location'  => 'homepage_middle',
            'order'     => 4,
            'is_active' => true,
            'content'   => [
                'entity_type' => 'invalid_entity',
                'sort_by'     => 'created_at:desc',
                'limit'       => 5,
                'preset'      => 'grid',
            ],
        ];
        $response = $this->postJson(route('api.v1.admin.settings.home-page-block.store'), $payload);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['content.entity_type']);
    });
    it('validation fails for dynamic list with invalid sort_by', function (): void {
        $this->authorized_user([PermissionEnum::HOME_PAGE_BLOCK_CREATE]);
        $payload = [
            'type'      => HomePageBlockTypeEnum::DYNAMIC_LIST->value,
            'title'     => 'Dynamic List Block',
            'location'  => 'homepage_middle',
            'order'     => 4,
            'is_active' => true,
            'content'   => [
                'entity_type' => 'seminar_products',
                'sort_by'     => 'invalid_sort',
                'limit'       => 5,
                'preset'      => 'grid',
            ],
        ];
        $response = $this->postJson(route('api.v1.admin.settings.home-page-block.store'), $payload);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['content.sort_by']);
    });
    it('validation fails for dynamic list with limit out of range', function (): void {
        $this->authorized_user([PermissionEnum::HOME_PAGE_BLOCK_CREATE]);
        $payload = [
            'type'      => HomePageBlockTypeEnum::DYNAMIC_LIST->value,
            'title'     => 'Dynamic List Block',
            'location'  => 'homepage_middle',
            'order'     => 4,
            'is_active' => true,
            'content'   => [
                'entity_type' => 'digital_asset_products',
                'sort_by'     => 'created_at:desc',
                'limit'       => 25, // exceeds max of 20
                'preset'      => 'grid',
            ],
        ];
        $response = $this->postJson(route('api.v1.admin.settings.home-page-block.store'), $payload);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['content.limit']);
    });
});

describe('HomePageBlockController additional scenarios', function (): void {
    it('can create a block with MAIN_CATEGORIES type', function (): void {
        $this->authorized_user([PermissionEnum::HOME_PAGE_BLOCK_CREATE]);
        $categories = App\Models\Category::factory()->count(3)->create();
        $payload    = [
            'type'      => HomePageBlockTypeEnum::MAIN_CATEGORIES->value,
            'title'     => 'Main Categories Block',
            'location'  => 'homepage_top',
            'order'     => 3,
            'is_active' => true,
            'content'   => [
                'items'  => $categories->pluck('id')->toArray(),
                'preset' => 'default',
            ],
        ];
        $response = $this->postJson(route('api.v1.admin.settings.home-page-block.store'), $payload);
        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id', 'type', 'title', 'location', 'order', 'is_active', 'content' => [
                        'items', 'preset',
                    ],
                ],
            ]);

        $responseData = $response->json('data');
        expect($responseData['content']['items'])->toEqual($categories->pluck('id')->toArray());
    });
    it('can create a block with CURATED_LIST type', function (): void {
        $this->authorized_user([PermissionEnum::HOME_PAGE_BLOCK_CREATE]);
        $products = App\Models\Product::factory()->count(3)->create();
        $payload  = [
            'type'      => HomePageBlockTypeEnum::CURATED_LIST->value,
            'title'     => 'Curated Products',
            'location'  => 'homepage_top',
            'order'     => 3,
            'is_active' => true,
            'content'   => [
                'preset' => 'default',
                'items'  => $products->pluck('id')->toArray(),
            ],
        ];
        $response = $this->postJson(route('api.v1.admin.settings.home-page-block.store'), $payload);
        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id', 'type', 'title', 'location', 'order', 'is_active', 'content' => [
                        'items', 'preset',
                    ],
                ],
            ]);

        $responseData = $response->json('data');
        expect($responseData['content']['items'])->toEqual($products->pluck('id')->toArray());
    });
    it('can create a block with WEBINAR BANNER type', function (): void {
        $this->authorized_user([PermissionEnum::HOME_PAGE_BLOCK_CREATE]);
        $product = App\Models\Product::factory()->create(
            [
                'productable_type' => ProductableEnum::SEMINAR->value,
                'productable_id'   => App\Models\Seminar::factory(),
            ]
        );
        $payload = [
            'type'      => HomePageBlockTypeEnum::WEBINAR_BANNER->value,
            'title'     => 'Webinar Block',
            'location'  => 'homepage_top',
            'order'     => 3,
            'is_active' => true,
            'content'   => [
                'product_id' => $product->id,
                'text'       => 'Join our upcoming webinar!',
                'image_id'   => $this->image->id,
                'preset'     => 'default',
            ],
        ];
        $response = $this->postJson(route('api.v1.admin.settings.home-page-block.store'), $payload);
        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id', 'type', 'title', 'location', 'order', 'is_active', 'content' => [
                        'product_id', 'text', 'image_id', 'image_url',
                    ],
                ],
            ]);

        $responseData = $response->json('data');
        expect($responseData['content']['product_id'])->toBe($product->id)
            ->and($responseData['content']['text'])->toBe('Join our upcoming webinar!')
            ->and($responseData['content']['image_url'])->toBe($this->image->getUrl());
    });
    it('can create a block with DYNAMIC_LIST type', function (): void {
        $this->authorized_user([PermissionEnum::HOME_PAGE_BLOCK_CREATE]);
        $categories = App\Models\Category::factory()->count(2)->create();
        $payload    = [
            'type'      => HomePageBlockTypeEnum::DYNAMIC_LIST->value,
            'title'     => 'Dynamic Product List',
            'location'  => 'homepage_middle',
            'order'     => 4,
            'is_active' => true,
            'content'   => [
                'entity_type'  => 'course_products',
                'sort_by'      => 'created_at:desc',
                'limit'        => 5,
                'preset'       => 'grid',
                'category_ids' => $categories->pluck('id')->toArray(),
            ],
        ];
        $response = $this->postJson(route('api.v1.admin.settings.home-page-block.store'), $payload);
        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id', 'type', 'title', 'location', 'order', 'is_active', 'content' => [
                        'entity_type', 'sort_by', 'limit', 'preset', 'category_ids',
                    ],
                ],
            ]);

        $responseData = $response->json('data');
        expect($responseData['content']['entity_type'])->toBe('course_products')
            ->and($responseData['content']['sort_by'])->toBe('created_at:desc')
            ->and($responseData['content']['limit'])->toBe(5)
            ->and($responseData['content']['preset'])->toBe('grid')
            ->and($responseData['content']['category_ids'])->toEqual($categories->pluck('id')->toArray());
    });
    it('response always includes all expected keys even if some values are null', function (): void {
        $this->authorized_user([PermissionEnum::HOME_PAGE_BLOCK_CREATE]);
        $payload = [
            'type'      => HomePageBlockTypeEnum::BANNER->value,
            'title'     => 'Banner Block',
            'location'  => 'homepage_top',
            'order'     => 5,
            'is_active' => true,
            'content'   => [
                'image_id'     => $this->image->id,
                'action'       => 'go_to_shop',
                'action_title' => 'Shop Now',
                'content'      => null,
                'preset'       => null,
            ],
        ];
        $response = $this->postJson(route('api.v1.admin.settings.home-page-block.store'), $payload);
        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id', 'type', 'title', 'location', 'order', 'is_active', 'content' => [
                        'image_id', 'image_url', 'action', 'action_title', 'content', 'preset',
                    ],
                ],
            ]);
        $responseData = $response->json('data');
        expect(array_key_exists('content', $responseData))->toBeTrue();
        expect(array_key_exists('image_id', $responseData['content']))->toBeTrue();
        expect(array_key_exists('image_url', $responseData['content']))->toBeTrue();
        expect(array_key_exists('action', $responseData['content']))->toBeTrue();
        expect(array_key_exists('action_title', $responseData['content']))->toBeTrue();
        expect(array_key_exists('content', $responseData['content']))->toBeTrue();
        expect(array_key_exists('preset', $responseData['content']))->toBeTrue();
    });
    it('media is attached to the block after creation', function (): void {
        $this->authorized_user([PermissionEnum::HOME_PAGE_BLOCK_CREATE]);
        $payload = [
            'type'      => HomePageBlockTypeEnum::BANNER->value,
            'title'     => 'Banner Block',
            'location'  => 'homepage_top',
            'order'     => 2,
            'is_active' => true,
            'content'   => [
                'image_id'     => $this->image->id,
                'action'       => 'go_to_shop',
                'action_title' => 'Shop Now',
                'content'      => 'Banner Content',
                'preset'       => 'default',
            ],
        ];
        $response = $this->postJson(route('api.v1.admin.settings.home-page-block.store'), $payload);
        $response->assertStatus(201);
        $blockId = $response->json('data.id');
        $block   = HomePageBlock::find($blockId);
        $block->load('media');
        expect($block->media->count())->toBeGreaterThan(0);
        expect($block->media->first()->id)->toBe($this->image->id);
    });

    it('can create dynamic list block using factory', function (): void {
        $this->authorized_user([PermissionEnum::HOME_PAGE_BLOCK_VIEW_ANY]);
        $categories = App\Models\Category::factory()->count(3)->create();

        $block = HomePageBlock::factory()->dynamicList(
            DynamicListEntityTypeEnum::ALL_PRODUCTS,
            DynamicListSortByEnum::CREATED_AT_DESC,
            8, $categories->pluck('id')->toArray())->create();

        expect($block->type)->toBe(HomePageBlockTypeEnum::DYNAMIC_LIST)
            ->and($block->content['entity_type'])->toBe('all_products')
            ->and($block->content['sort_by'])->toBe('created_at:desc')
            ->and($block->content['limit'])->toBe(8)
            ->and($block->content['category_ids'])->toEqual($categories->pluck('id')->toArray());
    });

    it('can create different types of dynamic lists for different product types', function (): void {
        $this->authorized_user([PermissionEnum::HOME_PAGE_BLOCK_CREATE]);

        // Test course products
        $coursePayload = [
            'type'      => HomePageBlockTypeEnum::DYNAMIC_LIST->value,
            'title'     => 'Latest Courses',
            'location'  => 'homepage_top',
            'order'     => 1,
            'is_active' => true,
            'content'   => [
                'entity_type' => 'course_products',
                'sort_by'     => 'created_at:desc',
                'limit'       => 3,
                'preset'      => 'course_grid',
            ],
        ];

        $response = $this->postJson(route('api.v1.admin.settings.home-page-block.store'), $coursePayload);
        $response->assertStatus(201);
        expect($response->json('data.content.entity_type'))->toBe('course_products');

        // Test seminar products
        $seminarPayload = [
            'type'      => HomePageBlockTypeEnum::DYNAMIC_LIST->value,
            'title'     => 'Popular Seminars',
            'location'  => 'homepage_middle',
            'order'     => 2,
            'is_active' => true,
            'content'   => [
                'entity_type' => 'seminar_products',
                'sort_by'     => 'popular',
                'limit'       => 4,
                'preset'      => 'seminar_cards',
            ],
        ];

        $response = $this->postJson(route('api.v1.admin.settings.home-page-block.store'), $seminarPayload);
        $response->assertStatus(201);
        expect($response->json('data.content.entity_type'))->toBe('seminar_products');

        // Test blog posts
        $blogPayload = [
            'type'      => HomePageBlockTypeEnum::DYNAMIC_LIST->value,
            'title'     => 'Recent Articles',
            'location'  => 'homepage_bottom',
            'order'     => 3,
            'is_active' => true,
            'content'   => [
                'entity_type' => 'blog_post',
                'sort_by'     => 'created_at:desc',
                'limit'       => 6,
                'preset'      => 'blog_list',
            ],
        ];

        $response = $this->postJson(route('api.v1.admin.settings.home-page-block.store'), $blogPayload);
        $response->assertStatus(201);
        expect($response->json('data.content.entity_type'))->toBe('blog_post');
    });
});
