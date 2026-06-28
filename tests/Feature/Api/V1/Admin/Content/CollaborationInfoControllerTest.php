<?php

declare(strict_types=1);

use App\Enums\PermissionEnum;
use Illuminate\Http\UploadedFile;

uses(Tests\Support\Traits\AuthTestTrait::class);

it('can get collaboration settings', function (): void {
    $this->authorized_user([PermissionEnum::SETTING_VIEW_ANY]);

    $response = $this->getJson(route('api.v1.admin.settings.collaboration.show'));

    $response->assertOk();
    $response->assertJsonStructure([
        'message',
        'data' => [
            'title',
            'content',
            'image',
        ],
        'metadata',
    ]);
});

it('can update collaboration settings', function (): void {
    $this->authorized_user([PermissionEnum::SETTING_UPDATE]);
    Storage::fake('public');
    $image = MediaUploader::fromSource(UploadedFile::fake()->image('image.jpg'))
        ->toDisk('public')
        ->upload();
    $payload = [
        'title'   => 'Updated Collaboration Title',
        'content' => '<p>Updated content for collaboration page.</p>',
        'image'   => $image->id,
    ];

    $response = $this->putJson(route('api.v1.admin.settings.collaboration.update'), $payload);

    $response->assertOk();
    $responseData = $response->json('data');
    expect($responseData['title'])->toBe($payload['title'])
        ->and($responseData['content'])->toBe($payload['content'])
        ->and($responseData['image']['id'])->toBe($image->id)
        ->and($responseData['image']['url'])->toBe($image->getUrl());

});

it('cannot update collaboration settings with invalid data', function (): void {
    $this->authorized_user([PermissionEnum::SETTING_UPDATE]);

    $payload = [
        'title'   => '', // Title is required
        'content' => '', // Content is required
        'image'   => null,
    ];

    $response = $this->putJson(route('api.v1.admin.settings.collaboration.update'), $payload);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['title', 'content']);
});

it('cannot access collaboration settings without authentication', function (): void {
    $this->authorized_user();
    $response = $this->getJson(route('api.v1.admin.settings.collaboration.show'));
    $response->assertForbidden();

    $payload = [
        'title'   => 'Updated Collaboration Title',
        'content' => '<p>Updated content for collaboration page.</p>',
        'image'   => null,
    ];
    $response = $this->putJson(route('api.v1.admin.settings.collaboration.update'), $payload);
    $response->assertForbidden();
});
