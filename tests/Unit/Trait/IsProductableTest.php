<?php

declare(strict_types=1);

it('get getProductableAttachment', function (): void {
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
        'productable_type' => App\Enums\Product\ProductableEnum::COURSE->value,
    ]);

    expect($course->products)
        ->toHaveCount(1)
        ->and($course->products->first())
        ->toBeInstanceOf(App\Models\Product::class)
        ->and($course->products->first()->id)
        ->toEqual($product->id);
});

it('loades category relationship with loadProductableCategories fucntion', function (): void {
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
