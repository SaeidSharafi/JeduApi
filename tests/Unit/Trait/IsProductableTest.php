<?php

declare(strict_types=1);

it('get getProductableMedia', function () {
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
    expect($course->getProductableMedia())
        ->toBeArray()
        ->toHaveCount(0);
    $course->refresh()->loadProductableMedia();
    expect($course->getProductableMedia())
        ->toBeArray()
        ->and($course->getProductableMedia())
        ->toHaveCount(4)
        ->and($course->getProductableMedia()['gallery'])
        ->toHaveCount(0)
        ->and($course->getProductableMedia()['video'])
        ->toHaveCount(0)
        ->and($course->getProductableMedia()['cover'])
        ->toHaveCount(1)
        ->and($course->getProductableMedia()['certificate'])
        ->toHaveCount(0);
});

it('get getProductableAttachment', function () {
    Illuminate\Http\UploadedFile::fake();
    Storage::fake('public');
    $this->main = MediaUploader::fromSource(Illuminate\Http\UploadedFile::fake()->image('main.jpg'))
        ->toDisk('public')
        ->upload();
    $course = App\Models\DigitalAsset::factory()->create();
    $course->attachMedia($this->main, 'main');
    expect($course->getProductableAttachment())
        ->toBeArray()
        ->toHaveCount(0);
    $course->refresh()->load('media');
    expect($course->getProductableAttachment())
        ->toBeArray()
        ->and($course->getProductableAttachment())
        ->toHaveCount(2)
        ->and($course->getProductableAttachment()['main'])
        ->toHaveCount(1)
        ->and($course->getProductableAttachment()['preview'])
        ->toHaveCount(0);
});

test('relation products', function (): void {
    $course  = App\Models\Course::factory()->create();
    $product = App\Models\Product::factory()->create([
        'productable_id'   => $course->id,
        'productable_type' => App\Enums\ProductableEnum::COURSE->value,
    ]);

    expect($course->products)
        ->toHaveCount(1)
        ->and($course->products->first())
        ->toBeInstanceOf(App\Models\Product::class)
        ->and($course->products->first()->id)
        ->toEqual($product->id);
});

it('loades category relationship with loadProductableCategories fucntion', function () {
    $course     = App\Models\Course::factory()->create();
    $categories = App\Models\Category::factory(3)->create();
    $course->categories()->sync($categories);

    $courseData = $course->toArray();
    expect(data_get($courseData, 'categories'))
        ->toBeNull();

    $course->loadProductableCategories();
    $courseData = $course->toArray();
    expect(data_get($courseData, 'categories'))
        ->toHaveCount(3);
});

it('get Productable cover', function () {
    Illuminate\Http\UploadedFile::fake();
    Storage::fake('public');
    $this->cover = MediaUploader::fromSource(Illuminate\Http\UploadedFile::fake()->image('cover.jpg'))
        ->toDisk('public')
        ->upload();
    $course = App\Models\Course::factory()->create();
    $course->attachMedia($this->cover, 'cover');
    expect($course->getProductableCover())
        ->toBeArray()
        ->toHaveCount(0);
    $course->refresh()->loadProductableMedia();
    expect($course->getProductableCover())
        ->toBeArray()
        ->toHaveCount(1);
});
