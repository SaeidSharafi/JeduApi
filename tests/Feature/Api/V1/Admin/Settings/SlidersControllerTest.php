<?php

declare(strict_types=1);

use App\Models\Slider;
use Plank\Mediable\Media;

uses(Tests\AuthTestTrait::class);
beforeEach(function (): void {
    Illuminate\Http\UploadedFile::fake();
    Storage::fake('public');
    $this->image1 = MediaUploader::fromSource(Illuminate\Http\UploadedFile::fake()->image('image1.jpg'))
        ->toDisk('public')
        ->upload();
    $this->image2 = MediaUploader::fromSource(Illuminate\Http\UploadedFile::fake()->image('image2.jpg'))
        ->toDisk('public')
        ->upload();
});
it('can list sliders', function (): void {
    $this->authorized_user([App\Enums\PermissionEnum::SLIDER_VIEW_ANY->value]);
    $slider = Slider::factory()->create([
        'title'   => 'Test Slider',
        'caption' => 'Test Caption',
        'link'    => '/test',
        'order'   => 1,
    ]);
    $response = $this->getJson(route('api.v1.admin.settings.slider.index'));

    $response->assertStatus(200)
        ->assertJsonStructure(
            [
                'message',
                'data' => [
                    'data' => [
                        '*' => ['id', 'title', 'caption', 'image_url', 'status', 'link', 'order'],
                    ],
                ],
                'metadata',
            ]
        );
});

it('can show a slider', function (): void {
    $this->authorized_user([App\Enums\PermissionEnum::SLIDER_VIEW->value]);
    $slider = Slider::factory()->create([
        'title'     => 'Test Slider',
        'caption'   => 'Test Caption',
        'image_url' => $this->image1->getUrl(),
        'link'      => '/test',
        'order'     => 1,
    ]);
    $slider->attachMedia($this->image1, 'image');

    $response = $this->getJson(route('api.v1.admin.settings.slider.show', $slider));

    $response->assertStatus(200)
        ->assertJsonStructure([
            'message',
            'data' => ['id', 'title', 'caption', 'status', 'image', 'link', 'order'],
            'metadata',
        ]);
    $responseData = $response->json('data');
    expect($responseData['title'])->toBe('Test Slider')
        ->and($responseData['image']['id'])->toBe($this->image1->id)
        ->and($responseData['image']['url'])->toBe($this->image1->getUrl());
});

it('can create a slider', function (): void {
    $this->authorized_user([App\Enums\PermissionEnum::SLIDER_CREATE->value]);
    $data = [
        'title'   => 'New Slider',
        'caption' => 'New Caption',
        'image'   => $this->image1->id,
        'status'  => App\Enums\PublicationStatusEnum::PUBLISHED->value,
        'link'    => '/new',
        'order'   => 2,
    ];
    $response = $this->postJson(route('api.v1.admin.settings.slider.store'), $data);
    $response->assertStatus(201)
        ->assertJsonStructure([
            'message',
            'data' => ['id', 'title', 'caption', 'status', 'image', 'link', 'order'],
            'metadata',
        ]);
    $responseData = $response->json('data');

    expect($responseData['title'])->toBe('New Slider')
        ->and($responseData['image']['id'])->toBe($this->image1->id)
        ->and($responseData['image']['url'])->toBe($this->image1->getUrl());

    $slider = Slider::query()->where('title', 'New Slider')->first();
    expect($slider->getImage())->toBeNull();
    $slider->load('media');
    expect($slider)->not->toBeNull()
        ->and($slider->caption)->toBe('New Caption')
        ->and($slider->link)->toBe('/new')
        ->and($slider->order)->toBe(2)
        ->and($slider->getImage()->id)->toBe($this->image1->id);

});

it('can update a slider', function (): void {
    $this->authorized_user([App\Enums\PermissionEnum::SLIDER_UPDATE->value]);

    $slider = Slider::factory()->create([
        'title'     => 'Old Slider',
        'caption'   => 'Old Caption',
        'image_url' => $this->image1->getUrl(),
        'image_alt' => 'Old Alt',
        'link'      => '/old',
        'order'     => 1,
    ]);
    $slider->attachMedia($this->image1, 'image');
    $data = [
        'title'   => 'Updated Slider',
        'caption' => 'Updated Caption',
        'status'  => App\Enums\PublicationStatusEnum::DRAFT->value,
        'image'   => $this->image2->id,
        'link'    => '/updated',
        'order'   => 3,
    ];
    $response = $this->putJson(route('api.v1.admin.settings.slider.update', $slider), $data);
    $response->assertStatus(200)
        ->assertJsonStructure([
            'message',
            'data' => ['id', 'title', 'caption', 'status', 'image', 'link', 'order'],
            'metadata',
        ]);
    $slider->refresh();
    $slider->load('media');
    expect($slider->title)->toBe('Updated Slider')
        ->and($slider->caption)->toBe('Updated Caption')
        ->and($slider->link)->toBe('/updated')
        ->and($slider->order)->toBe(3)
        ->and($slider->getImage()->id)->toBe($this->image2->id);
});

it('can delete a slider', function (): void {
    $this->authorized_user([App\Enums\PermissionEnum::SLIDER_DELETE->value]);
    $slider = Slider::factory()->create()->fresh();
    $slider->attachMedia($this->image1, 'image');
    $response = $this->deleteJson(route('api.v1.admin.settings.slider.destroy', $slider));
    $response->assertStatus(204);
    expect(Slider::query()->find($slider->id))->toBeNull()
        ->and(Media::query()->find($this->image1->id))->toBeNull();
});

it('validates required fields on create', function (): void {
    $this->authorized_user([App\Enums\PermissionEnum::SLIDER_CREATE->value]);
    $data = [
        'title' => '',
        'image' => null,
        'order' => null,
    ];
    $response = $this->postJson(route('api.v1.admin.settings.slider.store'), $data);
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['title', 'image', 'order']);
});

it('validates required fields on update', function (): void {
    $this->authorized_user([App\Enums\PermissionEnum::SLIDER_UPDATE->value]);
    $slider = Slider::factory()->create([
        'title'   => 'Slider',
        'caption' => 'Caption',
        'link'    => '/link',
        'order'   => 1,
    ]);
    $data = [
        'title'    => '',
        'image_id' => null,
        'order'    => null,
    ];
    $response = $this->putJson(route('api.v1.admin.settings.slider.update', $slider), $data);
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['title', 'image', 'order']);
});

it('update slider status', function () {
    $this->authorized_user([App\Enums\PermissionEnum::SLIDER_UPDATE->value]);

    $slider = Slider::factory()->create([
        'title'     => 'Status Slider',
        'caption'   => 'Status Caption',
        'image_url' => $this->image1->getUrl(),
        'image_alt' => 'Status Alt',
        'link'      => '/status',
        'order'     => 1,
        'status'    => App\Enums\PublicationStatusEnum::PUBLISHED,
    ]);
    $slider->attachMedia($this->image1, 'image');
    $data = [
        'status' => App\Enums\PublicationStatusEnum::DRAFT->value,
    ];
    $response = $this->patchJson(route('api.v1.admin.settings.slider.status', $slider), $data);
    $response->assertStatus(200)
        ->assertJsonStructure([
            'message',
            'data' => ['id', 'title', 'caption', 'status', 'image', 'link', 'order'],
            'metadata',
        ]);
    $slider->refresh();
    expect($slider->status)->toBe(App\Enums\PublicationStatusEnum::DRAFT);
});
