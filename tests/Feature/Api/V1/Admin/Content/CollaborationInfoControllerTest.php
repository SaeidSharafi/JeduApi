<?php

declare(strict_types=1);

use App\Data\Admin\Settings\CollaborationPageCreateData;
use App\Enums\PermissionEnum;
use Illuminate\Http\UploadedFile;

uses(Tests\Support\Traits\AuthTestTrait::class);

mutates(CollaborationPageCreateData::class);

beforeEach(function (): void {
    // A compact-level CKEditor / Word paste: a heading to downgrade, an event
    // handler, pasted typography and colour, a highlight, a scheme-less link, a
    // list, an executable subtree, an HTML-embed subtree and an image (which
    // the compact level does not allow, so it disappears).
    $this->dirtyContent = '<h1 onclick="alert(1)">عنوان</h1>'
        .'<p class="lead" style="font-family: Arial; color: red; text-align: center">متن <mark>مهم</mark> '
        .'<a href="javascript:alert(1)" target="_blank">لینک</a></p>'
        .'<ul><li>یک</li><li>دو</li></ul>'
        .'<script>alert(1)</script>'
        .'<div class="raw-html-embed"><p>arbitrary</p></div>'
        .'<img src="https://example.test/pic.png" alt="pic">';

    $this->cleanContent = '<h2>عنوان</h2>'
        .'<p style="text-align: center">متن <mark>مهم</mark> '
        .'<a target="_blank" rel="noopener noreferrer">لینک</a></p>'
        .'<ul><li>یک</li><li>دو</li></ul>';
});

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

it('stores sanitized collaboration content and leaves the rest of the payload untouched', function (): void {
    $this->authorized_user([PermissionEnum::SETTING_UPDATE]);
    Storage::fake('public');
    $image = MediaUploader::fromSource(UploadedFile::fake()->image('image.jpg'))
        ->toDisk('public')
        ->upload();

    $payload = [
        'title'   => 'فرصت <b>همکاری</b>',
        'content' => $this->dirtyContent,
        'image'   => $image->id,
    ];

    $response = $this->putJson(route('api.v1.admin.settings.collaboration.update'), $payload);
    $response->assertOk();

    expect($response->json('data.content'))->toBe($this->cleanContent)
        ->and($response->json('data.title'))->toBe('فرصت <b>همکاری</b>');

    expect(App\Models\Setting::where('key', 'collaboration')->firstOrFail()->value)->toBe([
        'title'   => 'فرصت <b>همکاری</b>',
        'content' => $this->cleanContent,
        'image'   => $image->id,
    ]);
});

it('round-trips the sanitized collaboration payload byte-identically through the read and write endpoints', function (): void {
    $this->authorized_user([PermissionEnum::SETTING_UPDATE, PermissionEnum::SETTING_VIEW_ANY]);
    Storage::fake('public');
    $image = MediaUploader::fromSource(UploadedFile::fake()->image('image.jpg'))
        ->toDisk('public')
        ->upload();

    $this->putJson(route('api.v1.admin.settings.collaboration.update'), [
        'title'   => 'فرصت همکاری',
        'content' => $this->dirtyContent,
        'image'   => $image->id,
    ])->assertOk();

    $stored = App\Models\Setting::where('key', 'collaboration')->firstOrFail()->value;
    expect($stored['content'])->toBe($this->cleanContent);

    $served = $this->getJson(route('api.v1.admin.settings.collaboration.show'))->json('data');

    $this->putJson(route('api.v1.admin.settings.collaboration.update'), [
        'title'   => $served['title'],
        'content' => $served['content'],
        'image'   => $served['image']['id'] ?? null,
    ])->assertOk();

    expect(App\Models\Setting::where('key', 'collaboration')->firstOrFail()->value)->toBe($stored);
});
