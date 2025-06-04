<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Plank\Mediable\Media;

uses(Tests\AuthTestTrait::class);
beforeEach(function () {
    Storage::fake('public');
});
describe('Staff MediaController', function (): void {
    it('can upload a media file and returns correct structure', function (): void {
        $this->authorized_user([]);
        $file     = UploadedFile::fake()->image('test-image.jpg');
        $response = $this->postJson(route('api.v1.admin.media.upload'), [
            'file' => $file,
            'alt'  => 'Test Alt Text',
        ]);
        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'url',
                    'size',
                    'file_name',
                    'alt',
                    'mime_type',
                    'extension',
                    'tag',
                ],
            ]);
        $mediaId = $response->json('data.id');
        expect($mediaId)->not()->toBeNull();
        $media = Media::find($mediaId);
        expect($media)->not()->toBeNull()
            ->and($media->getAttribute('alt'))->toBe('Test Alt Text');

        $response = $this->getJson(route('api.v1.admin.media.view', ['media' => $mediaId]));
        $response->assertStatus(200)
            ->assertJson(function (Illuminate\Testing\Fluent\AssertableJson $json) use ($mediaId): void {
                $json
                    ->has('data')
                    ->where('data.id', $mediaId)
                    ->where('data.alt', 'Test Alt Text')
                    ->has('data.url')
                    ->has('data.size')
                    ->has('data.file_name')
                    ->has('data.mime_type')
                    ->has('data.extension')
                    ->has('data.tag')
                    ->etc();
            });
    });
});
