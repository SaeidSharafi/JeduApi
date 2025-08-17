<?php

use App\Models\Product;
use App\Models\ProductDeliveryOption;
use App\Services\Discounts\Configs\ProductCategoryConditionConfigData;
use App\Services\Discounts\Product\Conditions\ProductCategoryCondition;
use Illuminate\Support\Facades\DB;

describe('ProductCategoryCondition', function () {

    test('it passes when product is in specified category', function () {
        // Arrange
        $condition = new ProductCategoryCondition();
        $config = new ProductCategoryConditionConfigData(
            category_ids: [1, 2, 3],
            match_policy: 'any'
        );

        $product = Product::factory()->create();
        $option = ProductDeliveryOption::factory()->make(['id' => 999]);
        $option->setRelation('product', $product);

        // Mock the database query for categorizables
        DB::shouldReceive('table')
            ->with('categorizables')
            ->andReturnSelf();
        DB::shouldReceive('where')
            ->with('categorizable_type', 'product')
            ->andReturnSelf();
        DB::shouldReceive('where')
            ->with('categorizable_id', $product->id)
            ->andReturnSelf();
        DB::shouldReceive('whereIn')
            ->with('category_id', [1, 2, 3])
            ->andReturnSelf();
        DB::shouldReceive('count')
            ->andReturn(1); // Product is in at least one category

        // Act
        $result = $condition->passes($option, $config);

        // Assert
        expect($result)->toBeTrue();
    });

    test('it fails when product is not in any specified category', function () {
        // Arrange
        $condition = new ProductCategoryCondition();
        $config = new ProductCategoryConditionConfigData(
            category_ids: [1, 2, 3],
            match_policy: 'any'
        );

        $product = Product::factory()->create();
        $option = ProductDeliveryOption::factory()->make(['id' => 999]);
        $option->setRelation('product', $product);

        // Mock the database query for categorizables
        DB::shouldReceive('table')
            ->with('categorizables')
            ->andReturnSelf();
        DB::shouldReceive('where')
            ->with('categorizable_type', 'product')
            ->andReturnSelf();
        DB::shouldReceive('where')
            ->with('categorizable_id', $product->id)
            ->andReturnSelf();
        DB::shouldReceive('whereIn')
            ->with('category_id', [1, 2, 3])
            ->andReturnSelf();
        DB::shouldReceive('count')
            ->andReturn(0); // Product is not in any category

        // Act
        $result = $condition->passes($option, $config);

        // Assert
        expect($result)->toBeFalse();
    });

    test('it passes when no categories are specified', function () {
        // Arrange
        $condition = new ProductCategoryCondition();
        $config = new ProductCategoryConditionConfigData(
            category_ids: [], // Empty array
            match_policy: 'any'
        );

        $product = Product::factory()->create();
        $option = ProductDeliveryOption::factory()->make(['id' => 999]);
        $option->setRelation('product', $product);

        // Act
        $result = $condition->passes($option, $config);

        // Assert
        expect($result)->toBeTrue(); // Should pass vacuously when no categories specified
    });

    test('it fails when product has no ID', function () {
        // Arrange
        $condition = new ProductCategoryCondition();
        $config = new ProductCategoryConditionConfigData(
            category_ids: [1, 2, 3],
            match_policy: 'any'
        );

        $product = new Product(); // Product without ID
        $option = ProductDeliveryOption::factory()->make(['id' => 999]);
        $option->setRelation('product', $product);

        // Act
        $result = $condition->passes($option, $config);

        // Assert
        expect($result)->toBeFalse();
    });

    test('it returns false for invalid configuration type', function () {
        // Arrange
        $condition = new ProductCategoryCondition();
        $invalidConfig = new class extends \Spatie\LaravelData\Data {
            public function __construct(public string $invalid = 'config') {}
        };

        $product = Product::factory()->create();
        $option = ProductDeliveryOption::factory()->make(['id' => 999]);
        $option->setRelation('product', $product);

        // Act
        $result = $condition->passes($option, $invalidConfig);

        // Assert
        expect($result)->toBeFalse();
    });
});
