<?php

use App\Enums\Order\DiscountTypeEnum;
use App\Models\DiscountPromotion;
use App\Models\DiscountPromotionRule;
use App\Models\Product;
use App\Models\ProductDeliveryOption;
use App\Models\ProductDeliveryOptionDiscountPrice;
use App\Services\Discounts\ProductDiscountIndexer;
use App\Services\Discounts\ProductDiscountPriceCalculator;

describe('LayeredPromotionSystem', function () {

    beforeEach(function () {
        // Clean slate for each test
        ProductDeliveryOptionDiscountPrice::truncate();
        DiscountPromotion::query()->delete();
    });

    test('it applies multiple promotions sequentially by priority', function () {
        // Arrange - Create a product priced at $100
        $product = Product::factory()->create();
        $option = ProductDeliveryOption::factory()->create([
            'product_id' => $product->id,
            'price' => 10000, // $100.00
            'status' => 'published'
        ]);

        // Create Promotion A: VIP 15% Off (priority: 1)
        $promotionA = DiscountPromotion::factory()->create([
            'name' => 'VIP 15% Off',
            'type' => DiscountTypeEnum::PRODUCT_SPECIFIC,
            'is_active' => true,
            'priority' => 1,
            'stop_processing_subsequent_rules' => false,
        ]);

        DiscountPromotionRule::create([
            'discount_promotion_id' => $promotionA->id,
            'type' => 'action',
            'handler' => 'apply_percentage_off_product',
            'configuration' => ['percentage' => 15],
        ]);

        // Create Promotion B: Clearance Sale $10 Off (priority: 2)
        $promotionB = DiscountPromotion::factory()->create([
            'name' => 'Clearance Sale $10 Off',
            'type' => DiscountTypeEnum::PRODUCT_SPECIFIC,
            'is_active' => true,
            'priority' => 2,
            'stop_processing_subsequent_rules' => false,
        ]);

        DiscountPromotionRule::create([
            'discount_promotion_id' => $promotionB->id,
            'type' => 'action',
            'handler' => 'apply_fixed_discount_product',
            'configuration' => ['amount' => 1000], // $10.00
        ]);

        // Act - Calculate the layered discount
        $calculator = app(ProductDiscountPriceCalculator::class);
        $promotions = collect([$promotionA, $promotionB]);

        $finalPrice = $calculator->calculateFinalDiscountedPrice($option, $promotions);

        // Assert - Expected calculation:
        // Step 1: $100 * (100 - 15) / 100 = $85
        // Step 2: $85 - $10 = $75
        expect($finalPrice)->toBe(7500); // $75.00
    });

    test('it stops processing when end_other_rules is true', function () {
        // Arrange - Create a product priced at $100
        $product = Product::factory()->create();
        $option = ProductDeliveryOption::factory()->create([
            'product_id' => $product->id,
            'price' => 10000, // $100.00
            'status' => 'published'
        ]);

        // Create Promotion A: VIP 20% Off (priority: 1, stops processing)
        $promotionA = DiscountPromotion::factory()->create([
            'name' => 'VIP 20% Off',
            'type' => DiscountTypeEnum::PRODUCT_SPECIFIC,
            'is_active' => true,
            'priority' => 1,
            'stop_processing_subsequent_rules' => true, // This should stop further processing
        ]);

        DiscountPromotionRule::create([
            'discount_promotion_id' => $promotionA->id,
            'type' => 'action',
            'handler' => 'apply_percentage_off_product',
            'configuration' => ['percentage' => 20],
        ]);

        // Create Promotion B: Flash Sale 10% Off (priority: 2, should NOT apply)
        $promotionB = DiscountPromotion::factory()->create([
            'name' => 'Flash Sale 10% Off',
            'type' => DiscountTypeEnum::PRODUCT_SPECIFIC,
            'is_active' => true,
            'priority' => 2,
            'stop_processing_subsequent_rules' => false,
        ]);

        DiscountPromotionRule::create([
            'discount_promotion_id' => $promotionB->id,
            'type' => 'action',
            'handler' => 'apply_percentage_off_product',
            'configuration' => ['percentage' => 10],
        ]);

        // Act - Calculate the layered discount
        $calculator = app(ProductDiscountPriceCalculator::class);
        $promotions = collect([$promotionA, $promotionB]);

        $finalPrice = $calculator->calculateFinalDiscountedPrice($option, $promotions);

        // Assert - Expected calculation:
        // Step 1: $100 * (100 - 20) / 100 = $80
        // Step 2: Processing stopped, no further discounts applied
        expect($finalPrice)->toBe(8000); // $80.00 (only Promotion A applied)
    });

    test('it applies promotions in priority order regardless of creation order', function () {
        // Arrange - Create a product priced at $100
        $product = Product::factory()->create();
        $option = ProductDeliveryOption::factory()->create([
            'product_id' => $product->id,
            'price' => 10000, // $100.00
            'status' => 'published'
        ]);

        // Create promotions in reverse priority order to test sorting

        // Create Promotion with priority 10 (lower priority)
        $promotionLow = DiscountPromotion::factory()->create([
            'name' => 'New Customer 5% Off',
            'type' => DiscountTypeEnum::PRODUCT_SPECIFIC,
            'is_active' => true,
            'priority' => 10,
            'stop_processing_subsequent_rules' => false,
        ]);

        DiscountPromotionRule::create([
            'discount_promotion_id' => $promotionLow->id,
            'type' => 'action',
            'handler' => 'apply_percentage_off_product',
            'configuration' => ['percentage' => 5],
        ]);

        // Create Promotion with priority 5 (medium priority)
        $promotionMid = DiscountPromotion::factory()->create([
            'name' => 'Category Sale 15% Off',
            'type' => DiscountTypeEnum::PRODUCT_SPECIFIC,
            'is_active' => true,
            'priority' => 5,
            'stop_processing_subsequent_rules' => false,
        ]);

        DiscountPromotionRule::create([
            'discount_promotion_id' => $promotionMid->id,
            'type' => 'action',
            'handler' => 'apply_percentage_off_product',
            'configuration' => ['percentage' => 15],
        ]);

        // Create Promotion with priority 1 (highest priority)
        $promotionHigh = DiscountPromotion::factory()->create([
            'name' => 'VIP Member $20 Off',
            'type' => DiscountTypeEnum::PRODUCT_SPECIFIC,
            'is_active' => true,
            'priority' => 1,
            'stop_processing_subsequent_rules' => false,
        ]);

        DiscountPromotionRule::create([
            'discount_promotion_id' => $promotionHigh->id,
            'type' => 'action',
            'handler' => 'apply_fixed_discount_product',
            'configuration' => ['amount' => 2000], // $20.00
        ]);

        // Act - Calculate with promotions in random order
        $calculator = app(ProductDiscountPriceCalculator::class);
        $promotions = collect([$promotionLow, $promotionMid, $promotionHigh]);

        $finalPrice = $calculator->calculateFinalDiscountedPrice($option, $promotions);

        // Assert - Expected calculation (by priority order: 1, 5, 10):
        // Step 1 (Priority 1): $100 - $20 = $80
        // Step 2 (Priority 5): $80 * (100 - 15) / 100 = $68
        // Step 3 (Priority 10): $68 * (100 - 5) / 100 = $64.60
        expect($finalPrice)->toBe(6460); // $64.60
    });

    test('it indexes discount prices using the new job-based system', function () {
        // Arrange - Create a product priced at $100
        $product = Product::factory()->create();
        $option = ProductDeliveryOption::factory()->create([
            'product_id' => $product->id,
            'price' => 10000, // $100.00
            'status' => 'published'
        ]);

        // Create a simple promotion
        $promotion = DiscountPromotion::factory()->create([
            'name' => 'Test Promotion',
            'type' => DiscountTypeEnum::PRODUCT_SPECIFIC,
            'is_active' => true,
            'priority' => 1,
        ]);

        DiscountPromotionRule::create([
            'discount_promotion_id' => $promotion->id,
            'type' => 'action',
            'handler' => 'apply_percentage_off_product',
            'configuration' => ['percentage' => 10],
        ]);

        // Act - Use the indexer to calculate and store discount prices
        $indexer = app(ProductDiscountIndexer::class);
        $indexer->reIndexComplete();

        // Assert - Check that discount price was stored
        $discountPrice = ProductDeliveryOptionDiscountPrice::where('product_delivery_option_id', $option->id)->first();

        expect($discountPrice)->not->toBeNull()
            ->and($discountPrice->discounted_price)->toBe(9000) // $90.00 (10% off $100)
            ->and($discountPrice->discount_promotion_id)->toBe($promotion->id);
    });

    test('it demonstrates the complete example from the documentation', function () {
        // Arrange - Create a course priced at $100
        $product = Product::factory()->create(['name' => 'Advanced Laravel Course']);
        $option = ProductDeliveryOption::factory()->create([
            'product_id' => $product->id,
            'price' => 10000, // $100.00
            'status' => 'published'
        ]);

        // Create Promotion A: "VIP 15% Off" (priority: 1, ends_other_rules: false)
        $promotionA = DiscountPromotion::factory()->create([
            'name' => 'VIP 15% Off',
            'type' => DiscountTypeEnum::PRODUCT_SPECIFIC,
            'is_active' => true,
            'priority' => 1,
            'stop_processing_subsequent_rules' => false,
        ]);

        DiscountPromotionRule::create([
            'discount_promotion_id' => $promotionA->id,
            'type' => 'action',
            'handler' => 'apply_percentage_off_product',
            'configuration' => ['percentage' => 15],
        ]);

        // Create Promotion B: "Clearance Sale $10 Off" (priority: 2)
        $promotionB = DiscountPromotion::factory()->create([
            'name' => 'Clearance Sale $10 Off',
            'type' => DiscountTypeEnum::PRODUCT_SPECIFIC,
            'is_active' => true,
            'priority' => 2,
            'stop_processing_subsequent_rules' => false,
        ]);

        DiscountPromotionRule::create([
            'discount_promotion_id' => $promotionB->id,
            'type' => 'action',
            'handler' => 'apply_fixed_discount_product',
            'configuration' => ['amount' => 1000], // $10.00
        ]);

        // Act - Process through the complete indexing system
        $indexer = app(ProductDiscountIndexer::class);
        $indexer->reIndexComplete();

        // Assert - Verify the final stored discount price
        $discountPrice = ProductDeliveryOptionDiscountPrice::where('product_delivery_option_id', $option->id)->first();

        expect($discountPrice)->not->toBeNull()
            ->and($discountPrice->discounted_price)->toBe(7500); // $75.00 (final price after both promotions)

        // Additional verification: manually calculate to ensure consistency
        $calculator = app(ProductDiscountPriceCalculator::class);
        $promotions = collect([$promotionA, $promotionB]);
        $calculatedPrice = $calculator->calculateFinalDiscountedPrice($option, $promotions);

        expect($calculatedPrice)->toBe($discountPrice->discounted_price);
    });

})->group('layered-promotions', 'discount-system');
