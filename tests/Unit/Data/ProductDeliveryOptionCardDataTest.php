<?php

declare(strict_types=1);

use App\Data\Shop\Product\ProductDeliveryOptionCardData;
use App\Enums\DeliveryMethodEnum;
use App\Enums\ProductableMediaTypeEnum;
use App\Models\ProductDeliveryOption;

it('return data correctly', function () {
    $course = App\Models\Course::factory()
        ->create()
        ->fresh();

    Storage::fake('public');
    $cover = MediaUploader::fromSource(Illuminate\Http\UploadedFile::fake()->image('cover.jpg'))
        ->toDisk('public')
        ->upload();

    $course->attachMedia($cover, ProductableMediaTypeEnum::COVER->value);

    $course->loadMediaWithVariantsMatchAll();

    $product = App\Models\Product::factory()->create([
        'name'             => 'Test Product',
        'productable_type' => App\Enums\ProductableEnum::COURSE->value,
        'productable_id'   => $course->id,
    ]);
    $deliveryOption = ProductDeliveryOption::factory()
        ->create([
            'name'            => 'Test Product',
            'delivery_method' => DeliveryMethodEnum::LMS_MOODLE->value,
            'product_id'      => $product->id,
        ])->fresh();
    $deliveryOption->loadMissing('product.productableWithAllRelations');

    $data = ProductDeliveryOptionCardData::fromModel($deliveryOption);

    expect($data->name)->toBe('Test Product')
        ->and($data->short_name)->toBe($product->short_name)
        ->and($data->short_description)->toBe($product->short_description)
        ->and($data->vendor)->toBe([
            'id'   => $product->vendor?->id,
            'name' => $product->vendor?->name,
        ])
        ->and($data->term)->toBe([
            'id'   => $product->term?->id,
            'name' => $product->term?->name,
        ])
        ->and($data->price)->toBe($deliveryOption->price)
        ->and($data->fullfilment_type)->toBe([
            'value' => $deliveryOption->fulfillment_type->value,
            'label' => $deliveryOption->fulfillment_type->translate(),
        ])
        ->and($data->delivery_method)->toBe([
            'value' => $deliveryOption->delivery_method->value,
            'label' => $deliveryOption->delivery_method->translate(),
        ])
        ->and($data->status)->toBe($product->status?->value)
        ->and($data->productable_type)->toBe($product->productable_type)
        ->and($data->cover)->toBe([
            'url'       => $cover->getUrl(),
            'thumbnail' => null,
            'alt'       => $cover->getAttribute('alt'),
        ]);

});
it('cover will be null if course has no image', function () {
    $course = App\Models\Course::factory()
        ->create()
        ->fresh();

    $course->loadMediaWithVariantsMatchAll();

    $product = App\Models\Product::factory()->create([
        'name'             => 'Test Product',
        'productable_type' => App\Enums\ProductableEnum::COURSE->value,
        'productable_id'   => $course->id,
    ]);
    $deliveryOption = ProductDeliveryOption::factory()
        ->create([
            'name'            => 'Test Product',
            'delivery_method' => DeliveryMethodEnum::LMS_MOODLE->value,
            'product_id'      => $product->id,
        ])->fresh();
    $deliveryOption->loadMissing('product');

    $data = ProductDeliveryOptionCardData::fromModel($deliveryOption);

    expect($data->cover)->toBeNull();

});
