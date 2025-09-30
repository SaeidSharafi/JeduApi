<?php

use App\Data\Shop\Product\ProductCardData;
use App\Data\Shop\ProductPriceData;
use App\Enums\Product\ProductableEnum;
use App\Models\Product;

describe('ProductCardData', function (){
    it('can be created from a Product model', function () {
        // Mock a Product model (you can use a factory or create a mock manually)
        $product = new Product();
        $product->slug = 'example-product';
        $product->name = 'Example Product';
        $product->is_featured = true;
        $product->productable_type = ProductableEnum::COURSE->value;
        $product->price_data_cache = [
            'min_price' => 100,
            'min_original_price' => 150,
            'range' => [100, 200],
            'has_discount' => true,
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

        // Mock the productDeliveryOptions relationship
        $deliveryOption = new class {
            public function getTeachersName() {
                return collect(['Jane Smith']);
            }
        };
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
            ->and($productCardData->teachers)->toBe(['Jane Smith'])
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
            'min_price' => 100,
            'min_original_price' => 150,
            'range' => [100, 200],
            'has_discount' => true,
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
        $deliveryOption = new class {
            public function getTeachersName() {
                return collect([]);
            }
        };
        $product->setRelation('productDeliveryOptions', collect([$deliveryOption]));

        // Create ProductCardData from the model
        $priceData = ProductPriceData::from($product->price_data_cache);
        $productCardData = ProductCardData::fromModel($product, $priceData);

        // Assertions
        expect($productCardData->teachers)->toBe(['John Doe']);
    });
});
