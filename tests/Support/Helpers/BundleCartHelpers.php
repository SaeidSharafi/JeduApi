<?php

declare(strict_types=1);

use App\Enums\Content\PublicationStatusEnum;
use App\Enums\Product\DeliveryMethodEnum;
use App\Enums\Product\FulfillmentTypeEnum;
use App\Enums\Product\ProductableEnum;
use App\Models\Bundle;
use App\Models\Course;
use App\Models\Product;
use App\Models\ProductDeliveryOption;

if (! function_exists('v11CoursePair')) {
    /**
     * Create a published Course Productable Product with two alternate published,
     * open, uncapped PDOs for the same underlying productable.
     *
     * @return array{product: Product, pdo_a: ProductDeliveryOption, pdo_b: ProductDeliveryOption}
     */
    function v11CoursePair(int $price = 100000): array
    {
        $course  = Course::factory()->create();
        $product = Product::factory()->create([
            'productable_type' => ProductableEnum::COURSE->value,
            'productable_id'   => $course->id,
            'status'           => PublicationStatusEnum::PUBLISHED,
            'is_visible'       => true,
        ]);

        $make = fn (): ProductDeliveryOption => ProductDeliveryOption::factory()->create([
            'product_id'              => $product->id,
            'price'                   => $price,
            'status'                  => PublicationStatusEnum::PUBLISHED,
            'capacity'                => null,
            'registration_start_date' => null,
            'registration_end_date'   => null,
            'available_from'          => null,
            'available_to'            => null,
        ]);

        return ['product' => $product, 'pdo_a' => $make(), 'pdo_b' => $make()];
    }
}

if (! function_exists('v11BundleFor')) {
    /**
     * Build a reviewed, on-sale Bundle PDO wrapping the given component PDOs.
     * The Bundle selling price equals the sum of the supplied allocations.
     *
     * @param  array<int, array{pdo: ProductDeliveryOption, allocation: int}>  $components
     */
    function v11BundleFor(array $components): ProductDeliveryOption
    {
        $bundle        = Bundle::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]);
        $bundleProduct = Product::factory()->create([
            'productable_type' => ProductableEnum::BUNDLE->value,
            'productable_id'   => $bundle->id,
            'status'           => PublicationStatusEnum::PUBLISHED,
            'is_visible'       => true,
        ]);
        $sellingPrice = (int) collect($components)->sum('allocation');
        $bundleOption = ProductDeliveryOption::factory()->create([
            'product_id'              => $bundleProduct->id,
            'fulfillment_type'        => FulfillmentTypeEnum::COMPOSITE,
            'delivery_method'         => DeliveryMethodEnum::BUNDLE,
            'details_json'            => [],
            'price'                   => $sellingPrice,
            'status'                  => PublicationStatusEnum::PUBLISHED,
            'capacity'                => null,
            'registration_start_date' => null,
            'registration_end_date'   => null,
            'available_from'          => null,
            'available_to'            => null,
        ]);

        foreach ($components as $component) {
            $bundleOption->bundleComponents()->attach($component['pdo']->id, ['allocation' => $component['allocation']]);
        }

        return $bundleOption;
    }
}
