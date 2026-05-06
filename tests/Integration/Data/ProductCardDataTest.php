<?php

declare(strict_types=1);

use App\Data\Shop\Product\ProductCardData;
use App\Data\Shop\ProductPriceData;
use App\Enums\Product\ProductableEnum;
use App\Enums\User\GenderEnum;
use App\Models\Product;

describe('ProductCardData', function () {
    it('can be created from a Product model', function () {
        // Mock a Product model (you can use a factory or create a mock manually)
        $product = new Product();
        $product->slug = 'example-product';
        $product->name = 'Example Product';
        $product->short_description = 'Example short description';
        $product->is_featured = true;
        $product->productable_type = ProductableEnum::COURSE->value;
        $product->price_data_cache = [
            'min_price'           => 100,
            'min_original_price'  => 150,
            'range'               => [100, 200],
            'has_discount'        => true,
            'discount_percentage' => 33.33,
        ];
        $product->reviews_count = 10;
        $product->average_rating = 4.5;

        // Mock the productable relationship
        $course = new class {
            public $thumbnail_url = 'http://example.com/thumbnail.jpg';

            public $default_teacher_info = ['John Doe'];
        };
        $product->setRelation('productable', $course);

        $teacherData
            = [
            'id'           => 1,
            'first_name'   => 'John',
            'last_name'    => 'Doe',
            'bio'          => 'Experienced teacher in various subjects.',
            'gender'       => 'male',
            'uuid'         => 'uuid',
            'avatar_url'   => 'http://example.com/avatar.jpg',
            'rate'         => 4.5,
            'dummy_column' => 'dummy_column',
        ];

        $deliveryOption = new \App\Models\ProductDeliveryOption([
            'available_from' => '2023-01-05',
            'available_to' => '2023-01-10',
            'registration_start_date' => '2023-01-01',
            'registration_end_date' => '2023-01-05',
        ]);
        $deliveryOption->setRelation('teachers', collect([$teacherData]));
        $product->setRelation('productDeliveryOptions', collect([$deliveryOption]));

        // Create ProductCardData from the model
        $priceData = ProductPriceData::from($product->price_data_cache);
        $productCardData = ProductCardData::fromModel($product, $priceData);

        // Assertions
        expect($productCardData->slug)->toBe('example-product')
            ->and($productCardData->name)->toBe('Example Product')
            ->and($productCardData->price)->toBe(100)
            ->and($productCardData->original_price)->toBe(150)
            ->and($productCardData->price_range)->toBe([100, 200])
            ->and($productCardData->has_discount)->toBeTrue()
            ->and($productCardData->discount_percent)->toBe(33.33)
            ->and($productCardData->is_free)->toBeFalse()
            ->and($productCardData->is_featured)->toBeTrue()
            ->and($productCardData->product_type)->toBeInstanceOf(ProductableEnum::class)
            ->and($productCardData->product_type->value)->toBe(ProductableEnum::COURSE->value)
            ->and($productCardData->thumbnail_url)->toBe('http://example.com/thumbnail.jpg')
            ->and($productCardData->available_from)->toEqual(new Verta('2023-01-05'))
            ->and($productCardData->available_to)->toEqual(new Verta('2023-01-10'))
            ->and($productCardData->registration_start_date)->toEqual(new Verta('2023-01-01'))
            ->and($productCardData->registration_end_date)->toEqual(new Verta('2023-01-05'))
            ->and($productCardData->teachers[0]->first_name)->toBe('John')
            ->and($productCardData->teachers[0]->last_name)->toBe('Doe')
            ->and($productCardData->teachers[0]->gender)->toBe(GenderEnum::MALE)
            ->and($productCardData->teachers[0]->uuid)->toBe('uuid')
            ->and($productCardData->teachers[0]->avatar_url)->toBe('http://example.com/avatar.jpg')
            ->and($productCardData->teachers[0]->rate)->toBe(4.5)
            ->and(isset($productCardData->teachers[0]->id))->toBeFalse()
            ->and(isset($productCardData->teachers[0]->bio))->toBeFalse()
            ->and(isset($productCardData->teachers[0]->dummy_column))->toBeFalse()
            ->and($productCardData->reviews_count)->toBe(10)
            ->and($productCardData->average_rating)->toBe(4.5)
            ->and($productCardData->price_data)->toBe($priceData);
    });

    it('fallback to default teacher if no teachers from delivery options', function () {
        // Mock a Product model (you can use a factory or create a mock manually)
        $product = new Product();
        $product->slug = 'example-product';
        $product->name = 'Example Product';
        $product->is_featured = true;
        $product->productable_type = ProductableEnum::COURSE->value;
        $product->price_data_cache = [
            'min_price'           => 100,
            'min_original_price'  => 150,
            'range'               => [100, 200],
            'has_discount'        => true,
            'discount_percentage' => 33.33,
        ];
        $product->reviews_count = 10;
        $product->average_rating = 4.5;

        // Mock the productable relationship
        $course = new class {
            public $thumbnail_url = 'http://example.com/thumbnail.jpg';

            public $default_teacher_info = 'John Doe';
        };
        $product->setRelation('productable', $course);

        // Mock the productDeliveryOptions relationship with no teachers
        $deliveryOption =new \App\Models\ProductDeliveryOption();

        $product->setRelation('productDeliveryOptions', collect([$deliveryOption]));

        // Create ProductCardData from the model
        $priceData = ProductPriceData::from($product->price_data_cache);
        $productCardData = ProductCardData::fromModel($product, $priceData);

        // Assertions
        expect($productCardData->teachers)->toBe(['John Doe']);
    });

    it('get  earliest available_from and latest available_to dates (same for registration dates)', function () {
        // Mock a Product model (you can use a factory or create a mock manually)
        $product = new Product();
        $product->slug = 'example-product';
        $product->name = 'Example Product';
        $product->is_featured = true;
        $product->productable_type = ProductableEnum::COURSE->value;
        $product->price_data_cache = [
            'min_price'           => 100,
            'min_original_price'  => 150,
            'range'               => [100, 200],
            'has_discount'        => true,
            'discount_percentage' => 33.33,
        ];
        $product->reviews_count = 10;
        $product->average_rating = 4.5;

        // Mock the productable relationship
        $course = new class {
            public $thumbnail_url = 'http://example.com/thumbnail.jpg';

            public $default_teacher_info = 'John Doe';
        };
        $product->setRelation('productable', $course);

        // Mock the productDeliveryOptions relationship with no teachers
        $deliveryOption1 =new \App\Models\ProductDeliveryOption(
            [
                'available_from' => '2023-01-05',
                'available_to' => '2023-01-10',
                'registration_start_date' => '2023-01-01',
                'registration_end_date' => '2023-01-05',
            ]
        );

        $deliveryOption2 =new \App\Models\ProductDeliveryOption(
            [
                'available_from' => '2023-01-12',
                'available_to' => '2023-01-16',
                'registration_start_date' => '2023-01-07',
                'registration_end_date' => '2023-01-11',
            ]
        );
        $product->setRelation('productDeliveryOptions', collect([$deliveryOption1, $deliveryOption2]));

        // Create ProductCardData from the model
        $priceData = ProductPriceData::from($product->price_data_cache);
        $productCardData = ProductCardData::fromModel($product, $priceData);

        expect($productCardData->available_from)->toEqual(new Verta('2023-01-05'))
            ->and($productCardData->available_to)->toEqual(new Verta('2023-01-16'))
            ->and($productCardData->registration_start_date)->toEqual(new Verta('2023-01-01'))
            ->and($productCardData->registration_end_date)->toEqual(new Verta('2023-01-11'));
    });
});
