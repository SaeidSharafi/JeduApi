<?php

use App\Models\Category;
use App\Models\DiscountPromotion;
use App\Models\DiscountPromotionRule;
use App\Models\Product;
use App\Models\ProductDeliveryOption;
use App\Models\ProductDeliveryOptionDiscountPrice;
use App\Services\Discounts\ProductDeliveryOptionDiscountPriceRegenerator;
use App\Services\Discounts\DiscountHandlerRegistry;
use App\Enums\Order\DiscountTypeEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

describe('ProductDeliveryOptionDiscountPriceRegenerator', function () {

    test('it can be instantiated', function () {
        // Act
        $regenerator = app(ProductDeliveryOptionDiscountPriceRegenerator::class);

        // Assert
        expect($regenerator)->toBeInstanceOf(ProductDeliveryOptionDiscountPriceRegenerator::class);
    });

    test('it removes existing discount prices on regenerate', function () {
        // Arrange
        $product = Product::factory()->create();
        $option = ProductDeliveryOption::factory()->create([
            'product_id' => $product->id,
            'price' => 10000,
            'status' => 'published'
        ]);

        // Create a promotion first
        $promotion = DiscountPromotion::factory()->create();

        // Create existing discount price
        ProductDeliveryOptionDiscountPrice::create([
            'product_delivery_option_id' => $option->id,
            'discount_promotion_id' => $promotion->id,
            'discounted_price' => 8000,
        ]);

        $regenerator = app(ProductDeliveryOptionDiscountPriceRegenerator::class);

        // Act
        $regenerator->regenerate();

        // Assert
        expect(ProductDeliveryOptionDiscountPrice::count())->toBe(0);
    });

    test('it does nothing when no active product-specific promotions exist', function () {
        // Arrange
        $product = Product::factory()->create();
        $option = ProductDeliveryOption::factory()->create([
            'product_id' => $product->id,
            'price' => 10000,
            'status' => 'published'
        ]);

        // Create an inactive promotion
        DiscountPromotion::factory()->create([
            'type' => DiscountTypeEnum::PRODUCT_SPECIFIC,
            'is_active' => false
        ]);

        // Create a cart promotion (wrong type)
        DiscountPromotion::factory()->create([
            'type' => DiscountTypeEnum::CART_CHECKOUT,
            'is_active' => true
        ]);

        $regenerator = app(ProductDeliveryOptionDiscountPriceRegenerator::class);

        // Act
        $regenerator->regenerate();

        // Assert
        expect(ProductDeliveryOptionDiscountPrice::count())->toBe(0);
    });

    test('it creates discount price for product that meets conditions and actions', function () {
        // Arrange
        $product = Product::factory()->create();
        $option = ProductDeliveryOption::factory()->create([
            'product_id' => $product->id,
            'price' => 10000,
            'status' => 'published'
        ]);

        // Create a product-specific promotion with action (no conditions)
        $promotion = DiscountPromotion::factory()->create([
            'type' => DiscountTypeEnum::PRODUCT_SPECIFIC,
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);

        // Add an action rule (20% off)
        DiscountPromotionRule::create([
            'discount_promotion_id' => $promotion->id,
            'type' => 'action',
            'handler' => 'apply_percentage_off_product',
            'configuration' => json_encode(['percentage' => 20])
        ]);

        $regenerator = app(ProductDeliveryOptionDiscountPriceRegenerator::class);

        // Act
        $regenerator->regenerate();

        // Assert
        expect(ProductDeliveryOptionDiscountPrice::count())->toBe(1);

        $discountPrice = ProductDeliveryOptionDiscountPrice::first();
        expect($discountPrice->product_delivery_option_id)->toBe($option->id);
        expect($discountPrice->discount_promotion_id)->toBe($promotion->id);
        expect($discountPrice->discounted_price)->toBe(8000); // 20% off 10000 = 8000
    });

    test('it does not create discount price when price is not actually reduced', function () {
        // Arrange
        $product = Product::factory()->create();
        $option = ProductDeliveryOption::factory()->create([
            'product_id' => $product->id,
            'price' => 10000,
            'status' => 'published'
        ]);

        // Create a promotion with 0% discount
        $promotion = DiscountPromotion::factory()->create([
            'type' => DiscountTypeEnum::PRODUCT_SPECIFIC,
            'is_active' => true,
        ]);

        DiscountPromotionRule::create([
            'discount_promotion_id' => $promotion->id,
            'type' => 'action',
            'handler' => 'apply_percentage_off_product',
            'configuration' => json_encode(['percentage' => 0])
        ]);

        $regenerator = app(ProductDeliveryOptionDiscountPriceRegenerator::class);

        // Act
        $regenerator->regenerate();

        // Assert
        expect(ProductDeliveryOptionDiscountPrice::count())->toBe(0);
    });

    test('it skips products that do not meet conditions', function () {
        // Arrange
        $product = Product::factory()->create();
        $option = ProductDeliveryOption::factory()->create([
            'product_id' => $product->id,
            'price' => 10000,
            'status' => 'published'
        ]);

        // Create category and mock category relationship - product is NOT in required categories
        $differentCategory = Category::factory()->create();
        DB::table('categorizables')->insert([
            'categorizable_type' => 'product',
            'categorizable_id' => $product->id,
            'category_id' => $differentCategory->id
        ]);

        // Create promotion with category condition
        $promotion = DiscountPromotion::factory()->create([
            'type' => DiscountTypeEnum::PRODUCT_SPECIFIC,
            'is_active' => true,
        ]);

        // Create required categories
        $requiredCategories = Category::factory()->count(3)->create();
        $requiredCategoryIds = $requiredCategories->pluck('id')->toArray();

        // Add condition rule (must be in the required categories)
        DiscountPromotionRule::create([
            'discount_promotion_id' => $promotion->id,
            'type' => 'condition',
            'handler' => 'product_in_category',
            'configuration' => json_encode(['category_ids' => $requiredCategoryIds, 'match_policy' => 'any'])
        ]);

        // Add action rule
        DiscountPromotionRule::create([
            'discount_promotion_id' => $promotion->id,
            'type' => 'action',
            'handler' => 'apply_percentage_off_product',
            'configuration' => json_encode(['percentage' => 20])
        ]);

        $regenerator = app(ProductDeliveryOptionDiscountPriceRegenerator::class);

        // Act
        $regenerator->regenerate();

        // Assert
        expect(ProductDeliveryOptionDiscountPrice::count())->toBe(0);
    });

    test('it chooses best discount when multiple promotions apply', function () {
        // Arrange
        $product = Product::factory()->create();
        $option = ProductDeliveryOption::factory()->create([
            'product_id' => $product->id,
            'price' => 10000,
            'status' => 'published'
        ]);

        // Create first promotion (10% off)
        $promotion1 = DiscountPromotion::factory()->create([
            'type' => DiscountTypeEnum::PRODUCT_SPECIFIC,
            'is_active' => true,
        ]);
        DiscountPromotionRule::create([
            'discount_promotion_id' => $promotion1->id,
            'type' => 'action',
            'handler' => 'apply_percentage_off_product',
            'configuration' => json_encode(['percentage' => 10])
        ]);

        // Create second promotion (25% off - better discount)
        $promotion2 = DiscountPromotion::factory()->create([
            'type' => DiscountTypeEnum::PRODUCT_SPECIFIC,
            'is_active' => true,
        ]);
        DiscountPromotionRule::create([
            'discount_promotion_id' => $promotion2->id,
            'type' => 'action',
            'handler' => 'apply_percentage_off_product',
            'configuration' => json_encode(['percentage' => 25])
        ]);

        $regenerator = app(ProductDeliveryOptionDiscountPriceRegenerator::class);

        // Act
        $regenerator->regenerate();

        // Assert
        expect(ProductDeliveryOptionDiscountPrice::count())->toBe(1);

        $discountPrice = ProductDeliveryOptionDiscountPrice::first();
        expect($discountPrice->discount_promotion_id)->toBe($promotion2->id); // Better promotion
        expect($discountPrice->discounted_price)->toBe(7500); // 25% off 10000 = 7500
    });

    test('it only processes published delivery options', function () {
        // Arrange
        $product = Product::factory()->create();

        // Published option
        $publishedOption = ProductDeliveryOption::factory()->create([
            'product_id' => $product->id,
            'price' => 10000,
            'status' => 'published'
        ]);

        // Draft option (should be skipped)
        $draftOption = ProductDeliveryOption::factory()->create([
            'product_id' => $product->id,
            'price' => 10000,
            'status' => 'draft'
        ]);

        // Create promotion
        $promotion = DiscountPromotion::factory()->create([
            'type' => DiscountTypeEnum::PRODUCT_SPECIFIC,
            'is_active' => true,
        ]);
        DiscountPromotionRule::create([
            'discount_promotion_id' => $promotion->id,
            'type' => 'action',
            'handler' => 'apply_percentage_off_product',
            'configuration' => json_encode(['percentage' => 20])
        ]);

        $regenerator = app(ProductDeliveryOptionDiscountPriceRegenerator::class);

        // Act
        $regenerator->regenerate();

        // Assert
        expect(ProductDeliveryOptionDiscountPrice::count())->toBe(1);
        expect(ProductDeliveryOptionDiscountPrice::first()->product_delivery_option_id)->toBe($publishedOption->id);
    });

    test('it handles errors gracefully and logs them', function () {
        // Arrange
        $product = Product::factory()->create();
        $option = ProductDeliveryOption::factory()->create([
            'product_id' => $product->id,
            'price' => 10000,
            'status' => 'published'
        ]);

        // Create promotion with invalid handler
        $promotion = DiscountPromotion::factory()->create([
            'type' => DiscountTypeEnum::PRODUCT_SPECIFIC,
            'is_active' => true,
        ]);
        DiscountPromotionRule::create([
            'discount_promotion_id' => $promotion->id,
            'type' => 'action',
            'handler' => 'non_existent_handler',
            'configuration' => json_encode(['percentage' => 20])
        ]);

        Log::shouldReceive('warning')
            ->with('Product action handler not found: non_existent_handler')
            ->once();

        $regenerator = app(ProductDeliveryOptionDiscountPriceRegenerator::class);

        // Act - Should not throw exception
        $regenerator->regenerate();

        // Assert
        expect(ProductDeliveryOptionDiscountPrice::count())->toBe(0);
    });

    test('it handles missing condition config DTO gracefully', function () {
        // Arrange
        $product = Product::factory()->create();
        $option = ProductDeliveryOption::factory()->create([
            'product_id' => $product->id,
            'price' => 10000,
            'status' => 'published'
        ]);

        $promotion = DiscountPromotion::factory()->create([
            'type' => DiscountTypeEnum::PRODUCT_SPECIFIC,
            'is_active' => true,
        ]);
        DiscountPromotionRule::create([
            'discount_promotion_id' => $promotion->id,
            'type' => 'condition',
            'handler' => 'product_in_category',
            'configuration' => json_encode(['category_ids' => [1, 2, 3], 'match_policy' => 'any'])
        ]);

        // Mock registry to return handler but no config class
        $mockRegistry = $this->mock(App\Services\Discounts\DiscountHandlerRegistry::class);
        $mockRegistry->shouldReceive('getProductConditionHandler')
            ->andReturn('SomeHandlerClass');
        $mockRegistry->shouldReceive('getConfigClass')
            ->andReturn(null); // No config DTO

        $this->app->instance(App\Services\Discounts\DiscountHandlerRegistry::class, $mockRegistry);

        Log::shouldReceive('warning')
            ->with('Config DTO not found for handler: SomeHandlerClass')
            ->once();

        $regenerator = app(ProductDeliveryOptionDiscountPriceRegenerator::class);

        // Act - Should not throw exception
        $regenerator->regenerate();

        // Assert
        expect(ProductDeliveryOptionDiscountPrice::count())->toBe(0);
    });

    test('it handles missing action config DTO gracefully', function () {
        // Arrange
        $product = Product::factory()->create();
        $option = ProductDeliveryOption::factory()->create([
            'product_id' => $product->id,
            'price' => 10000,
            'status' => 'published'
        ]);

        $promotion = DiscountPromotion::factory()->create([
            'type' => DiscountTypeEnum::PRODUCT_SPECIFIC,
            'is_active' => true,
        ]);
        DiscountPromotionRule::create([
            'discount_promotion_id' => $promotion->id,
            'type' => 'action',
            'handler' => 'apply_percentage_off_product',
            'configuration' => json_encode(['percentage' => 20])
        ]);

        // Mock registry to return handler but no config class
        $mockRegistry = $this->mock(App\Services\Discounts\DiscountHandlerRegistry::class);
        $mockRegistry->shouldReceive('getProductActionHandler')
            ->andReturn('SomeActionHandlerClass');
        $mockRegistry->shouldReceive('getConfigClass')
            ->andReturn(null); // No config DTO

        $this->app->instance(App\Services\Discounts\DiscountHandlerRegistry::class, $mockRegistry);

        Log::shouldReceive('warning')
            ->with('Config DTO not found for handler: SomeActionHandlerClass')
            ->once();

        $regenerator = app(ProductDeliveryOptionDiscountPriceRegenerator::class);

        // Act - Should not throw exception
        $regenerator->regenerate();

        // Assert
        expect(ProductDeliveryOptionDiscountPrice::count())->toBe(0);
    });

    test('it handles condition evaluation exceptions gracefully', function () {
        // Arrange
        $product = Product::factory()->create();
        $option = ProductDeliveryOption::factory()->create([
            'product_id' => $product->id,
            'price' => 10000,
            'status' => 'published'
        ]);

        $promotion = DiscountPromotion::factory()->create([
            'type' => DiscountTypeEnum::PRODUCT_SPECIFIC,
            'is_active' => true,
        ]);
        DiscountPromotionRule::create([
            'discount_promotion_id' => $promotion->id,
            'type' => 'condition',
            'handler' => 'product_in_category',
            'configuration' => 'invalid_json' // This will cause an exception
        ]);

        Log::shouldReceive('error')
            ->withArgs(function ($message) {
                return str_contains($message, 'Error evaluating product condition:');
            })
            ->once();

        $regenerator = app(ProductDeliveryOptionDiscountPriceRegenerator::class);

        // Act - Should not throw exception
        $regenerator->regenerate();

        // Assert
        expect(ProductDeliveryOptionDiscountPrice::count())->toBe(0);
    });

    test('it handles action application exceptions gracefully', function () {
        // Arrange
        $product = Product::factory()->create();
        $option = ProductDeliveryOption::factory()->create([
            'product_id' => $product->id,
            'price' => 10000,
            'status' => 'published'
        ]);

        $promotion = DiscountPromotion::factory()->create([
            'type' => DiscountTypeEnum::PRODUCT_SPECIFIC,
            'is_active' => true,
        ]);
        DiscountPromotionRule::create([
            'discount_promotion_id' => $promotion->id,
            'type' => 'action',
            'handler' => 'apply_percentage_off_product',
            'configuration' => 'invalid_json' // This will cause an exception
        ]);

        Log::shouldReceive('error')
            ->withArgs(function ($message) {
                return str_contains($message, 'Error applying product action:');
            })
            ->once();

        $regenerator = app(ProductDeliveryOptionDiscountPriceRegenerator::class);

        // Act - Should not throw exception
        $regenerator->regenerate();

        // Assert
        expect(ProductDeliveryOptionDiscountPrice::count())->toBe(0);
    });

    test('it handles missing product condition handler gracefully', function () {
        // Arrange - Test the exact scenario that covers lines 133-134
        $product = Product::factory()->create();
        $option = ProductDeliveryOption::factory()->create([
            'product_id' => $product->id,
            'price' => 10000,
            'status' => 'published'
        ]);

        $promotion = DiscountPromotion::factory()->create([
            'type' => DiscountTypeEnum::PRODUCT_SPECIFIC,
            'is_active' => true,
        ]);
        DiscountPromotionRule::create([
            'discount_promotion_id' => $promotion->id,
            'type' => 'condition',
            'handler' => 'nonexistent_handler',
            'configuration' => json_encode(['some' => 'config'])
        ]);

        // Mock registry to return null for handler (lines 133-134)
        $mockRegistry = $this->mock(App\Services\Discounts\DiscountHandlerRegistry::class);
        $mockRegistry->shouldReceive('getProductConditionHandler')
            ->with('nonexistent_handler')
            ->andReturn(null); // This triggers lines 133-134

        $this->app->instance(App\Services\Discounts\DiscountHandlerRegistry::class, $mockRegistry);

        Log::shouldReceive('warning')
            ->with('Product condition handler not found: nonexistent_handler')
            ->once(); // This is line 133

        $service = new ProductDeliveryOptionDiscountPriceRegenerator($mockRegistry);

        // Act
        $service->regenerate();

        // Assert - Should complete without throwing exceptions
        expect(true)->toBeTrue();
    });
});
