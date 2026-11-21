<?php

declare(strict_types=1);

use App\Enums\Operators\MatchPolicyEnum;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductDeliveryOption;
use App\Services\Discounts\Configs\ProductCategoryConditionConfigData;
use App\Services\Discounts\Product\Conditions\ProductCategoryCondition;

describe('ProductCategoryCondition', function (): void {

    test('it passes when product is in specified category', function (): void {
        $condition = new ProductCategoryCondition();
        $category  = Category::factory()->create();
        $config    = new ProductCategoryConditionConfigData(
            category_ids: [$category->id],
            match_policy: MatchPolicyEnum::ANY
        );

        $product = Product::factory()->create();
        $product->categories()->attach($category);

        $option = ProductDeliveryOption::factory()->make(['id' => 999]);
        $option->setRelation('product', $product);

        expect($condition->passes($option, $config))->toBeTrue();
    });

    test('it fails when product is not in any specified category', function (): void {
        $condition = new ProductCategoryCondition();
        $category  = Category::factory()->create();
        $config    = new ProductCategoryConditionConfigData(
            category_ids: [$category->id],
            match_policy: MatchPolicyEnum::ANY
        );

        $product = Product::factory()->create();

        $option = ProductDeliveryOption::factory()->make(['id' => 999]);
        $option->setRelation('product', $product);

        expect($condition->passes($option, $config))->toBeFalse();
    });

    test('it passes when no categories are specified', function (): void {
        // Arrange
        $condition = new ProductCategoryCondition();
        $config    = new ProductCategoryConditionConfigData(
            category_ids: [], // Empty array
            match_policy: MatchPolicyEnum::ANY
        );

        $product = Product::factory()->create();
        $option  = ProductDeliveryOption::factory()->make(['id' => 999]);
        $option->setRelation('product', $product);

        // Act
        $result = $condition->passes($option, $config);

        // Assert
        expect($result)->toBeTrue(); // Should pass vacuously when no categories specified
    });

    test('it fails when product has no ID', function (): void {
        // Arrange
        $condition = new ProductCategoryCondition();
        $config    = new ProductCategoryConditionConfigData(
            category_ids: [1, 2, 3],
            match_policy: MatchPolicyEnum::ANY
        );

        $product = new Product(); // Product without ID
        $option  = ProductDeliveryOption::factory()->make(['id' => 999]);
        $option->setRelation('product', $product);

        // Act
        $result = $condition->passes($option, $config);

        // Assert
        expect($result)->toBeFalse();
    });

    test('it returns false for invalid configuration type', function (): void {
        // Arrange
        $condition     = new ProductCategoryCondition();
        $invalidConfig = new class extends Spatie\LaravelData\Data
        {
            public function __construct(public string $invalid = 'config') {}
        };

        $product = Product::factory()->create();
        $option  = ProductDeliveryOption::factory()->make(['id' => 999]);
        $option->setRelation('product', $product);

        // Act
        $result = $condition->passes($option, $invalidConfig);

        // Assert
        expect($result)->toBeFalse();
    });
});
