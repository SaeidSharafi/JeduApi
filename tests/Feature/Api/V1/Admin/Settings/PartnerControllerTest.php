<?php

declare(strict_types=1);

use App\Enums\PermissionEnum;
use App\Models\Partner;
use Plank\Mediable\Media;
use App\Enums\PartnerShowInEnum;

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
it('can list partners', function (): void {
    $this->authorized_user([PermissionEnum::PARTNER_VIEW_ANY]);
    $partner = Partner::factory()->create([
        'title'   => 'Test Partner',
        'caption' => 'Test Caption',
        'url'     => '/test',
        'show_in' => PartnerShowInEnum::HOME->value,
        'order'   => 1,
    ]);
    $response = $this->getJson(route('api.v1.admin.settings.partner.index'));
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
it('can show a partner', function (): void {
    $this->authorized_user([PermissionEnum::PARTNER_VIEW]);
    $partner = Partner::factory()->create([
        'title'     => 'Test Partner',
        'caption'   => 'Test Caption',
        'image_url' => $this->image1->getUrl(),
        'url'       => '/test',
        'show_in'   => PartnerShowInEnum::HOME->value,
        'order'     => 1,
    ]);
    $partner->attachMedia($this->image1, 'image');
    $response = $this->getJson(route('api.v1.admin.settings.partner.show', $partner));
    $response->assertStatus(200)
        ->assertJsonStructure([
            'message',
            'data' => ['id', 'title', 'caption', 'image', 'url', 'show_in', 'order', 'is_active'],
            'metadata',
        ]);
    $responseData = $response->json('data');
    expect($responseData['title'])->toBe('Test Partner')
        ->and($responseData['image']['id'])->toBe($this->image1->id)
        ->and($responseData['image']['url'])->toBe($this->image1->getUrl());
});
it('can create a partner', function (): void {
    $this->authorized_user([PermissionEnum::PARTNER_CREATE]);
    $data = [
        'title'   => 'New Partner',
        'caption' => 'New Caption',
        'image'   => $this->image1->id,
        'url'     => '/new',
        'show_in' => PartnerShowInEnum::COURSE->value,
        'order'   => 2,
        'is_active'  => true,
    ];
    $response = $this->postJson(route('api.v1.admin.settings.partner.store'), $data);
    $response->assertStatus(201)
        ->assertJsonStructure([
            'message',
            'data' => ['id', 'title', 'caption', 'image', 'url', 'show_in', 'order', 'is_active'],
            'metadata',
        ]);
    $responseData = $response->json('data');
    expect($responseData['title'])->toBe('New Partner')
        ->and($responseData['image']['id'])->toBe($this->image1->id)
        ->and($responseData['image']['url'])->toBe($this->image1->getUrl());
    $partner = Partner::query()->where('title', 'New Partner')->first();
    expect($partner->getImage())->toBeNull();
    $partner->load('media');
    expect($partner)->not->toBeNull()
        ->and($partner->caption)->toBe('New Caption')
        ->and($partner->url)->toBe('/new')
        ->and($partner->show_in)->toBe(PartnerShowInEnum::COURSE)
        ->and($partner->order)->toBe(2)
        ->and($partner->getImage()->id)->toBe($this->image1->id);
});
it('can update a partner', function (): void {
    $this->authorized_user([PermissionEnum::PARTNER_UPDATE]);
    $partner = Partner::factory()->create([
        'title'     => 'Old Partner',
        'caption'   => 'Old Caption',
        'image_url' => $this->image1->getUrl(),
        'image_alt' => 'Old Alt',
        'url'       => '/old',
        'show_in'   => PartnerShowInEnum::HOME->value,
        'order'     => 1,
    ]);
    $partner->attachMedia($this->image1, 'image');
    $data = [
        'title'   => 'Updated Partner',
        'caption' => 'Updated Caption',
        'image'   => $this->image2->id,
        'url'     => '/updated',
        'show_in' => PartnerShowInEnum::COURSE,
        'order'   => 3,
        'is_active'  => false,
    ];
    $response = $this->putJson(route('api.v1.admin.settings.partner.update', $partner), $data);
    $response->assertStatus(200)
        ->assertJsonStructure([
            'message',
            'data' => ['id', 'title', 'caption', 'image', 'url', 'show_in', 'order', 'is_active'],
            'metadata',
        ]);
    $partner->refresh();
    $partner->load('media');
    expect($partner->title)->toBe('Updated Partner')
        ->and($partner->caption)->toBe('Updated Caption')
        ->and($partner->url)->toBe('/updated')
        ->and($partner->show_in)->toBe(PartnerShowInEnum::COURSE)
        ->and($partner->order)->toBe(3)
        ->and($partner->is_active)->toBeFalse()
        ->and($partner->getImage()->id)->toBe($this->image2->id);
});
it('can delete a partner', function (): void {
    $this->authorized_user([PermissionEnum::PARTNER_DELETE]);
    $partner = Partner::factory()->create()->fresh();
    $partner->attachMedia($this->image1, 'image');
    $response = $this->deleteJson(route('api.v1.admin.settings.partner.destroy', $partner));
    $response->assertStatus(204);
    expect(Partner::query()->find($partner->id))->toBeNull()
        ->and(Media::query()->find($this->image1->id))->toBeNull();
});
it('validates required fields on create', function (): void {
    $this->authorized_user([PermissionEnum::PARTNER_CREATE]);
    $data = [
        'title' => '',
        'image' => null,
        'order' => null,
        'show_in' => null,
    ];
    $response = $this->postJson(route('api.v1.admin.settings.partner.store'), $data);
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['title', 'image', 'order', 'show_in']);
});
it('validates required fields on update', function (): void {
    $this->authorized_user([PermissionEnum::PARTNER_UPDATE]);
    $partner = Partner::factory()->create([
        'title'   => 'Partner',
        'caption' => 'Caption',
        'url'     => '/link',
        'show_in' => PartnerShowInEnum::HOME->value,
        'order'   => 1,
    ]);
    $data = [
        'title'    => '',
        'image'    => null,
        'order'    => null,
        'show_in'  => null,
    ];
    $response = $this->putJson(route('api.v1.admin.settings.partner.update', $partner), $data);
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['title', 'image', 'order', 'show_in']);
});
