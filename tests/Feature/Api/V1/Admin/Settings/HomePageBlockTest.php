<?php

declare(strict_types=1);

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

it('admin can create a banner home page block successfully', function () {
    $this->authorized_user();
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

it('validation fails if required fields are missing', function () {
    $this->authorized_user();
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
    $this->authorized_user();
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
    $this->authorized_user();
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
    $this->authorized_user();
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
    $this->authorized_user();
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
    $this->authorized_user();
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

it('media is attached to the block after creation', function () {
    $this->authorized_user();
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

it('validation fails for minimum and maximum string lengths for title', function () {
    $this->authorized_user();
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

it('can create a block with MAIN_CATEGORIES type', function () {
    $this->authorized_user();
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
        ->assertJsonStructure(['data' => [
            'id', 'type', 'title', 'location', 'order', 'is_active', 'content' => [
                'items', 'preset'
            ]
        ]]);

    $responseData = $response->json('data');
    expect($responseData['content']['items'])->toEqual($categories->pluck('id')->toArray());
});

it('can create a block with CURATED_LIST type', function () {
    $this->authorized_user();
    $products = \App\Models\Product::factory()->count(3)->create();
    $payload = [
        'type'      => HomePageBlockTypeEnum::CURATED_LIST->value,
        'title'  => 'Curated Products',
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
        ->assertJsonStructure(['data' => [
            'id', 'type', 'title', 'location', 'order', 'is_active', 'content' => [
                'items', 'preset'
            ]
        ]]);

    $responseData = $response->json('data');
    expect($responseData['content']['items'])->toEqual($products->pluck('id')->toArray());
});

it('can create a block with WEBINAR BANNER type', function () {
    $this->authorized_user();
    $product = \App\Models\Product::factory()->create(
        [
            'productable_type' => \App\Enums\ProductableEnum::SEMINAR,
            'productable_id' => \App\Models\Seminar::factory()
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
        ->assertJsonStructure(['data' => [
            'id', 'type', 'title', 'location', 'order', 'is_active', 'content' => [
                'product_id', 'text', 'image_id', 'image_url',
            ]
        ]]);

    $responseData = $response->json('data');
    expect($responseData['content']['product_id'])->toBe($product->id)
        ->and($responseData['content']['text'])->toBe('Join our upcoming webinar!')
        ->and($responseData['content']['image_url'])->toBe($this->image->getUrl());
});
it('can delete a block and media remains intact', function () {
    $this->authorized_user();
    $payload = [
        'type'      => HomePageBlockTypeEnum::BANNER->value,
        'title'     => 'Banner Block',
        'location'  => 'homepage_top',
        'order'     => 4,
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
    $blockId = $response->json('data.id');
    $block = HomePageBlock::find($blockId);
    $block->delete();
    $media = Media::find($this->image->id);
    expect($media)->not->toBeNull();
});

it('response always includes all expected keys even if some values are null', function () {
    $this->authorized_user();
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
        ->assertJsonStructure(['data' => [
            'id', 'type', 'title', 'location', 'order', 'is_active', 'content' => [
                'image_id', 'image_url', 'action', 'action_title', 'content', 'preset'
            ]
        ]]);
    $responseData = $response->json('data');
    expect(array_key_exists('content', $responseData))->toBeTrue();
    expect(array_key_exists('image_id', $responseData['content']))->toBeTrue();
    expect(array_key_exists('image_url', $responseData['content']))->toBeTrue();
    expect(array_key_exists('action', $responseData['content']))->toBeTrue();
    expect(array_key_exists('action_title', $responseData['content']))->toBeTrue();
    expect(array_key_exists('content', $responseData['content']))->toBeTrue();
    expect(array_key_exists('preset', $responseData['content']))->toBeTrue();
});

it('validation fails if image_id does not exist', function () {
    $this->authorized_user();
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
