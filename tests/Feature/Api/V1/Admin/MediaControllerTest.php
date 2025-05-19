<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Plank\Mediable\Media;

uses(\Tests\AuthTestTrait::class);
describe('Admin MediaController', function () {
    it('can upload a media file and returns correct structure', function () {
        $this->authorized_user([]);
        $file = UploadedFile::fake()->image('test-image.jpg');
        $response = $this->postJson(route('api.v1.admin.media.upload'), [
            'file' => $file,
            'alt' => 'Test Alt Text',
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
    });
});
