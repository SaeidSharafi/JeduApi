<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Plank\Mediable\Media;

uses(Tests\AuthTestTrait::class);
beforeEach(function () {
    Storage::fake('local');
});
describe('Admin Private File', function (): void {
    it('can upload a private file and returns correct structure', function (): void {
        $this->authorized_user([App\Enums\PermissionEnum::FILE_VIEW_ANY->value]);
        $file     = UploadedFile::fake()->image('test-image.jpg');
        $response = $this->postJson(route('api.v1.admin.private-upload.upload'), [
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
        $fileId = $response->json('data.id');
        expect($fileId)->not()->toBeNull();

        $file = Media::find($fileId);
        expect($file)->not()->toBeNull()
            ->and($file->getAttribute('alt'))->toBe('Test Alt Text');

        $response = $this->getJson(route('api.v1.admin.private-upload.view', ['file' => $file->id]));
        $response->assertStatus(200)
            ->assertJson(function (Illuminate\Testing\Fluent\AssertableJson $json) use ($fileId): void {
                $json
                    ->has('data')
                    ->where('data.id', $fileId)
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
    it('can download a private file', function (): void {
        $file = MediaUploader::fromSource(UploadedFile::fake()->image('course.jpg'))
            ->toDisk('local')
            ->upload();

        $this->authorized_user([App\Enums\PermissionEnum::FILE_VIEW_ANY->value]);
        $response = $this->get(route('api.v1.admin.private-upload.download', ['file' => $file->id]));
        $response->assertStatus(200)
            ->assertDownload("{$file->filename}.{$file->extension}");
    });

    it('return 404 if file doesn\'t exist', function (): void {
        $this->authorized_user([App\Enums\PermissionEnum::FILE_VIEW_ANY->value]);
        $file = MediaUploader::fromSource(UploadedFile::fake()->image('course.jpg'))
            ->toDisk('local')
            ->upload();
        Storage::disk('local')->delete("{$file->filename}.{$file->extension}");
        $response = $this->get(route('api.v1.admin.private-upload.download', ['file' => $file->id]));
        $response->assertStatus(404);
    });

    it('cannot download a private file without permission', function (): void {
        $file = MediaUploader::fromSource(UploadedFile::fake()->image('course.jpg'))
            ->toDisk('local')
            ->upload();

        $this->unauthorized_user();
        $response = $this->get(route('api.v1.admin.private-upload.download', ['file' => $file->id]));
        $response->assertStatus(403);
    });
});
