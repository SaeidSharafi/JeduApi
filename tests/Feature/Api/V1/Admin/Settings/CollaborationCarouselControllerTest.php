<?php

declare(strict_types=1);

use App\Enums\PermissionEnum;
use App\Models\CollaborationCarousel;
use Plank\Mediable\Media;
use App\Enums\CollaborationCarouselShowInEnum;

uses(Tests\AuthTestTrait::class);
beforeEach(function () {
    Illuminate\Http\UploadedFile::fake();
    Storage::fake('public');
    $this->image1 = MediaUploader::fromSource(Illuminate\Http\UploadedFile::fake()->image('image1.jpg'))
        ->toDisk('public')
        ->upload();
    $this->image2 = MediaUploader::fromSource(Illuminate\Http\UploadedFile::fake()->image('image2.jpg'))
        ->toDisk('public')
        ->upload();
});
it('can list collaboration carousels', function () {
    $this->authorized_user([PermissionEnum::COLLABORATION_CAROUSEL_VIEW_ANY]);
    $carousel = CollaborationCarousel::factory()->create([
        'title'   => 'Test Carousel',
        'caption' => 'Test Caption',
        'url'     => '/test',
        'show_in' => CollaborationCarouselShowInEnum::HOME->value,
        'order'   => 1,
    ]);
    $response = $this->getJson(route('api.v1.admin.settings.collaboration-carousel.index'));
    $response->assertStatus(200)
        ->assertJsonStructure([
            'message',
            'data' => [
                'data' => [
                    '*' => ['id', 'title', 'caption', 'image_url', 'url', 'show_in', 'order', 'is_active'],
                ],
            ],
            'metadata',
        ]);
});
it('can show a collaboration carousel', function () {
    $this->authorized_user([PermissionEnum::COLLABORATION_CAROUSEL_VIEW]);
    $carousel = CollaborationCarousel::factory()->create([
        'title'     => 'Test Carousel',
        'caption'   => 'Test Caption',
        'image_url' => $this->image1->getUrl(),
        'url'       => '/test',
        'show_in'   => CollaborationCarouselShowInEnum::HOME->value,
        'order'     => 1,
    ]);
    $carousel->attachMedia($this->image1, 'image');
    $response = $this->getJson(route('api.v1.admin.settings.collaboration-carousel.show', $carousel));
    $response->assertStatus(200)
        ->assertJsonStructure([
            'message',
            'data' => ['id', 'title', 'caption', 'image', 'url', 'show_in', 'order', 'is_active'],
            'metadata',
        ]);
    $responseData = $response->json('data');
    expect($responseData['title'])->toBe('Test Carousel')
        ->and($responseData['image']['id'])->toBe($this->image1->id)
        ->and($responseData['image']['url'])->toBe($this->image1->getUrl());
});
it('can create a collaboration carousel', function () {
    $this->authorized_user([PermissionEnum::COLLABORATION_CAROUSEL_CREATE]);
    $data = [
        'title'   => 'New Carousel',
        'caption' => 'New Caption',
        'image'   => $this->image1->id,
        'url'     => '/new',
        'show_in' => CollaborationCarouselShowInEnum::COURSE->value,
        'order'   => 2,
        'is_active'  => true,
    ];
    $response = $this->postJson(route('api.v1.admin.settings.collaboration-carousel.store'), $data);
    $response->assertStatus(201)
        ->assertJsonStructure([
            'message',
            'data' => ['id', 'title', 'caption', 'image', 'url', 'show_in', 'order', 'is_active'],
            'metadata',
        ]);
    $responseData = $response->json('data');
    expect($responseData['title'])->toBe('New Carousel')
        ->and($responseData['image']['id'])->toBe($this->image1->id)
        ->and($responseData['image']['url'])->toBe($this->image1->getUrl());
    $carousel = CollaborationCarousel::query()->where('title', 'New Carousel')->first();
    expect($carousel->getImage())->toBeNull();
    $carousel->load('media');
    expect($carousel)->not->toBeNull()
        ->and($carousel->caption)->toBe('New Caption')
        ->and($carousel->url)->toBe('/new')
        ->and($carousel->show_in)->toBe(CollaborationCarouselShowInEnum::COURSE)
        ->and($carousel->order)->toBe(2)
        ->and($carousel->getImage()->id)->toBe($this->image1->id);
});
it('can update a collaboration carousel', function () {
    $this->authorized_user([PermissionEnum::COLLABORATION_CAROUSEL_UPDATE]);
    $carousel = CollaborationCarousel::factory()->create([
        'title'     => 'Old Carousel',
        'caption'   => 'Old Caption',
        'image_url' => $this->image1->getUrl(),
        'image_alt' => 'Old Alt',
        'url'       => '/old',
        'show_in'   => CollaborationCarouselShowInEnum::HOME->value,
        'order'     => 1,
    ]);
    $carousel->attachMedia($this->image1, 'image');
    $data = [
        'title'   => 'Updated Carousel',
        'caption' => 'Updated Caption',
        'image'   => $this->image2->id,
        'url'     => '/updated',
        'show_in' => CollaborationCarouselShowInEnum::COURSE,
        'order'   => 3,
        'is_active'  => false,
    ];
    $response = $this->putJson(route('api.v1.admin.settings.collaboration-carousel.update', $carousel), $data);
    $response->assertStatus(200)
        ->assertJsonStructure([
            'message',
            'data' => ['id', 'title', 'caption', 'image', 'url', 'show_in', 'order', 'is_active'],
            'metadata',
        ]);
    $carousel->refresh();
    $carousel->load('media');
    expect($carousel->title)->toBe('Updated Carousel')
        ->and($carousel->caption)->toBe('Updated Caption')
        ->and($carousel->url)->toBe('/updated')
        ->and($carousel->show_in)->toBe(CollaborationCarouselShowInEnum::COURSE)
        ->and($carousel->order)->toBe(3)
        ->and($carousel->is_active)->toBeFalse()
        ->and($carousel->getImage()->id)->toBe($this->image2->id);
});
it('can delete a collaboration carousel', function () {
    $this->authorized_user([PermissionEnum::COLLABORATION_CAROUSEL_DELETE]);
    $carousel = CollaborationCarousel::factory()->create()->fresh();
    $carousel->attachMedia($this->image1, 'image');
    $response = $this->deleteJson(route('api.v1.admin.settings.collaboration-carousel.destroy', $carousel));
    $response->assertStatus(204);
    expect(CollaborationCarousel::query()->find($carousel->id))->toBeNull()
        ->and(Media::query()->find($this->image1->id))->toBeNull();
});
it('validates required fields on create', function () {
    $this->authorized_user([PermissionEnum::COLLABORATION_CAROUSEL_CREATE]);
    $data = [
        'title' => '',
        'image' => null,
        'order' => null,
        'show_in' => null,
    ];
    $response = $this->postJson(route('api.v1.admin.settings.collaboration-carousel.store'), $data);
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['title', 'image', 'order', 'show_in']);
});
it('validates required fields on update', function () {
    $this->authorized_user([PermissionEnum::COLLABORATION_CAROUSEL_UPDATE]);
    $carousel = CollaborationCarousel::factory()->create([
        'title'   => 'Carousel',
        'caption' => 'Caption',
        'url'     => '/link',
        'show_in' => CollaborationCarouselShowInEnum::HOME->value,
        'order'   => 1,
    ]);
    $data = [
        'title'    => '',
        'image'    => null,
        'order'    => null,
        'show_in'  => null,
    ];
    $response = $this->putJson(route('api.v1.admin.settings.collaboration-carousel.update', $carousel), $data);
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['title', 'image', 'order', 'show_in']);
});
