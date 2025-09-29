<?php

declare(strict_types=1);

it('get getAllMedia', function (): void {
    Illuminate\Http\UploadedFile::fake();
    Storage::fake('public');
    $this->cover = MediaUploader::fromSource(Illuminate\Http\UploadedFile::fake()->image('cover.jpg'))
        ->toDisk('public')
        ->upload();
    $categories    = App\Models\Category::factory(3)->create();
    $digitalAssets = App\Models\DigitalAsset::factory(2)->create();
    $course        = App\Models\Course::factory()->create();
    $course->categories()->sync($categories);
    $course->digitalAssets()->sync($digitalAssets);
    $course->attachMedia($this->cover, 'cover');
    expect($course->getAllMedia())
        ->toBeArray()
        ->toHaveCount(0);
    $course->refresh()->loadMediaWitVariant();
    expect($course->getAllMedia())
        ->toBeArray()
        ->and($course->getAllMedia())
        ->toHaveCount(5)
        ->and($course->getAllMedia()['gallery'])
        ->toHaveCount(0)
        ->and($course->getAllMedia()['video'])
        ->toHaveCount(0)
        ->and($course->getAllMedia()['cover'])
        ->toHaveCount(1)
        ->and($course->getAllMedia()['certificate'])
        ->toHaveCount(0);
});
it('get getAllMedia as url only list', function (): void {
    Illuminate\Http\UploadedFile::fake();
    Storage::fake('public');
    $this->cover = MediaUploader::fromSource(Illuminate\Http\UploadedFile::fake()->image('cover.jpg'))
        ->toDisk('public')
        ->upload();
    $categories    = App\Models\Category::factory(3)->create();
    $digitalAssets = App\Models\DigitalAsset::factory(2)->create();
    $course        = App\Models\Course::factory()->create();
    $course->categories()->sync($categories);
    $course->digitalAssets()->sync($digitalAssets);
    $course->attachMedia($this->cover, 'cover');

    expect($course->getAllMedia(true))
        ->toBeArray()
        ->toHaveCount(0);
    $course->refresh()->loadMediaWitVariant();
    $media = $course->getAllMedia(true);
    //should only be list of urls
    expect($media)
        ->toBeArray()
        ->and($media)
        ->toHaveCount(5)
        ->and($media['gallery'])
        ->toHaveCount(0)
        ->and($media['video'])
        ->toHaveCount(0)
        ->and($media['cover'])
        ->toHaveCount(1)
        ->and($media['cover'][0])
        ->toBeString()
        ->and($media['certificate'])
        ->toHaveCount(0);

    foreach ($media as $tag => $items) {
        foreach ($items as $item) {
            $this->assertIsString($item);
        }
    }
});
it('get cover media', function (): void {
    Illuminate\Http\UploadedFile::fake();
    Storage::fake('public');
    $this->cover = MediaUploader::fromSource(Illuminate\Http\UploadedFile::fake()->image('cover.jpg'))
        ->toDisk('public')
        ->upload();
    $course = App\Models\Course::factory()->create();
    $course->attachMedia($this->cover, 'cover');
    expect($course->getCoverMedia())
        ->toBeArray()
        ->toHaveCount(0);
    $course->refresh()->loadMediaWitVariant();
    expect($course->getCoverMedia())
        ->toBeArray()
        ->toHaveCount(1)
        ->and($course->getCoverMedia(true))
        ->toBeInstanceOf(App\Data\Admin\MediaData::class)
        ->and($course->getCoverMedia(true)->id)->toBe($this->cover->id);
});
