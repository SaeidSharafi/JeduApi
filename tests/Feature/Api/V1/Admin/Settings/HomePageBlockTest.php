<?php

declare(strict_types=1);

use App\Enums\PermissionEnum;
use App\Models\HomePageBlock;
use Plank\Mediable\Media;
use App\Enums\HomePageBlockTypeEnum;

uses(Tests\AuthTestTrait::class);
beforeEach(function () {
    Illuminate\Http\UploadedFile::fake();
    Storage::fake('public');
    $this->image = MediaUploader::fromSource(Illuminate\Http\UploadedFile::fake()->image('banner.jpg'))
        ->toDisk('public')
        ->upload();

});
describe('HomePageBlockController CRUD', function () {
    it('can list home page blocks', function () {
        $this->authorized_user([PermissionEnum::HOME_PAGE_BLOCK_VIEW_ANY]);
        HomePageBlock::factory()
            ->banner($this->image)
            ->create();
        HomePageBlock::factory()
            ->webinarBanner($this->image,1)
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
                        '*' => ['id', 'type', 'title', 'location', 'order', 'is_active', 'content']
                    ]
                ]
            ]);
        $responseData = $response->json('data.data');
        expect(count($responseData))->toBe(4);
    });
    it('can create a banner home page block successfully', function () {
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
                        'image_id', 'image_url', 'action', 'action_title', 'content', 'preset'
                    ]
                ]
            ]);
        $responseData = $response->json('data');
        expect($responseData['content']['image_url'])->toBe($this->image->getUrl());
    });
    it('admin can view a specific home page block', function () {
        $this->authorized_user([PermissionEnum::HOME_PAGE_BLOCK_VIEW]);
        $block = HomePageBlock::factory()->banner($this->image)->create();
        $response = $this->getJson(route('api.v1.admin.settings.home-page-block.show', $block->id));
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id', 'type', 'title', 'location', 'order', 'is_active', 'content' => [
                        'image_id', 'image_url', 'action', 'action_title', 'content', 'preset'
                    ]
                ]
            ]);
        $responseData = $response->json('data');
        expect($responseData['id'])->toBe($block->id);
        expect($responseData['content']['image_url'])->toBe($this->image->getUrl());
    });
    it('admin can update a home page block', function () {
        $this->authorized_user([PermissionEnum::HOME_PAGE_BLOCK_UPDATE]);
        $block = HomePageBlock::factory()->banner($this->image)->create();
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
                        'image_id', 'image_url', 'action', 'action_title', 'content', 'preset'
                    ]
                ]
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
    it('admin can delete a home page block', function () {
        $this->authorized_user([PermissionEnum::HOME_PAGE_BLOCK_DELETE]);
        $block = HomePageBlock::factory()->banner($this->image)->create();
        $block->attachMedia($this->image, 'image');
        $response = $this->deleteJson(route('api.v1.admin.settings.home-page-block.destroy', $block->id));
        $response->assertStatus(204);
        $this->assertDatabaseMissing('home_page_blocks', ['id' => $block->id]);
        $this->assertDatabaseMissing('media', ['id' => $this->image->id]);
    });
    it('unauthorized user cannot access home page block endpoints', function () {
        $this->unauthorized_user();
        $block = HomePageBlock::factory()->banner($this->image)->create();
        $blockData = HomePageBlock::factory()->banner($this->image)->make()->toArray();
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


describe('HomePageBlockController validation', function () {
    it('validation fails if required fields are missing', function () {
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
                'type', 'title', 'location', 'order', 'is_active', 'content'
            ]);
    });
    it('validation fails if type is not a valid enum value', function () {
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
    it('validation fails if order is negative', function () {
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
    it('validation fails if is_active is not boolean', function () {
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
    it('validation fails if content is missing required keys', function () {
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
    it('validation fails if image_id is not an integer', function () {
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
    it('validation fails for minimum and maximum string lengths for title', function () {
        $this->authorized_user([PermissionEnum::HOME_PAGE_BLOCK_CREATE]);
        $minTitle = '';
        $maxTitle = str_repeat('a', 256); // assuming max is 255
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
    it('validation fails if image_id does not exist', function () {
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
});

describe('HomePageBlockController additional scenarios', function () {
    it('can create a block with MAIN_CATEGORIES type', function () {
        $this->authorized_user([PermissionEnum::HOME_PAGE_BLOCK_CREATE]);
        $categories = \App\Models\Category::factory()->count(3)->create();
        $payload = [
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
                        'items', 'preset'
                    ]
                ]
            ]);

        $responseData = $response->json('data');
        expect($responseData['content']['items'])->toEqual($categories->pluck('id')->toArray());
    });
    it('can create a block with CURATED_LIST type', function () {
        $this->authorized_user([PermissionEnum::HOME_PAGE_BLOCK_CREATE]);
        $products = \App\Models\Product::factory()->count(3)->create();
        $payload = [
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
                        'items', 'preset'
                    ]
                ]
            ]);

        $responseData = $response->json('data');
        expect($responseData['content']['items'])->toEqual($products->pluck('id')->toArray());
    });
    it('can create a block with WEBINAR BANNER type', function () {
        $this->authorized_user([PermissionEnum::HOME_PAGE_BLOCK_CREATE]);
        $product = \App\Models\Product::factory()->create(
            [
                'productable_type' => \App\Enums\ProductableEnum::SEMINAR,
                'productable_id'   => \App\Models\Seminar::factory()
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
                    ]
                ]
            ]);

        $responseData = $response->json('data');
        expect($responseData['content']['product_id'])->toBe($product->id)
            ->and($responseData['content']['text'])->toBe('Join our upcoming webinar!')
            ->and($responseData['content']['image_url'])->toBe($this->image->getUrl());
    });
    it('response always includes all expected keys even if some values are null', function () {
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
                        'image_id', 'image_url', 'action', 'action_title', 'content', 'preset'
                    ]
                ]
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
    it('media is attached to the block after creation', function () {
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
        $block = HomePageBlock::find($blockId);
        $block->load('media');
        expect($block->media->count())->toBeGreaterThan(0);
        expect($block->media->first()->id)->toBe($this->image->id);
    });
});
