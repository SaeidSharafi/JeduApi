<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\DiscountPromotion;
use App\Models\DiscountPromotionRule;
use App\Models\Product;
use App\Models\ProductDeliveryOption;
use App\Services\Discounts\DiscountHandlerRegistry;
use App\Services\Discounts\ProductDiscountPriceCalculator;

describe('ProductDiscountPriceCalculator', function (): void {
    beforeEach(function (): void {
        $this->registry   = app(DiscountHandlerRegistry::class);
        $this->calculator = new ProductDiscountPriceCalculator($this->registry);
    });

    it('returns original price if no promotions apply', function (): void {
        $product    = Product::factory()->create();
        $option     = ProductDeliveryOption::factory()->for($product)->create(['price' => 1000]);
        $promotions = collect();
        $result     = $this->calculator->calculateFinalDiscountedPrice($option, $promotions);
        expect($result)->toBe(1000);
    });

    it('applies a single fixed discount promotion', function (): void {
        $product   = Product::factory()->create();
        $option    = ProductDeliveryOption::factory()->for($product)->create(['price' => 1000]);
        $promotion = DiscountPromotion::factory()->create(['priority' => 1]);
        DiscountPromotionRule::factory()->create([
            'discount_promotion_id' => $promotion->id,
            'type'                  => 'action',
            'handler'               => 'apply_fixed_discount_product',
            'configuration'         => ['amount' => 200],
        ]);
        $promotions = DiscountPromotion::query()->get();
        $result     = $this->calculator->calculateFinalDiscountedPrice($option, $promotions);
        expect($result)->toBe(800);
    });

    it('applies multiple promotions in order (layered)', function (): void {
        $product = Product::factory()->create();
        $option  = ProductDeliveryOption::factory()->for($product)->create(['price' => 1000]);
        $promo1  = DiscountPromotion::factory()->create(['priority' => 1]);
        $promo2  = DiscountPromotion::factory()->create(['priority' => 2]);
        DiscountPromotionRule::factory()->create([
            'discount_promotion_id' => $promo1->id,
            'type'                  => 'action',
            'handler'               => 'apply_fixed_discount_product',
            'configuration'         => ['amount' => 100],
        ]);
        DiscountPromotionRule::factory()->create([
            'discount_promotion_id' => $promo2->id,
            'type'                  => 'action',
            'handler'               => 'apply_fixed_discount_product',
            'configuration'         => ['amount' => 200],
        ]);
        $promotions = DiscountPromotion::query()->get();
        $result     = $this->calculator->calculateFinalDiscountedPrice($option, $promotions);
        // 1000 - 100 = 900, then 900 - 200 = 700
        expect($result)->toBe(700);
    });

    it('stops applying further promotions if stop_processing_subsequent_rules is true', function (): void {
        $product = Product::factory()->create();
        $option  = ProductDeliveryOption::factory()->for($product)->create(['price' => 1000]);
        $promo1  = DiscountPromotion::factory()->create(['priority' => 1, 'stop_processing_subsequent_rules' => true]);
        $promo2  = DiscountPromotion::factory()->create(['priority' => 2]);
        DiscountPromotionRule::factory()->create([
            'discount_promotion_id' => $promo1->id,
            'type'                  => 'action',
            'handler'               => 'apply_fixed_discount_product',
            'configuration'         => ['amount' => 100],
        ]);
        DiscountPromotionRule::factory()->create([
            'discount_promotion_id' => $promo2->id,
            'type'                  => 'action',
            'handler'               => 'apply_fixed_discount_product',
            'configuration'         => ['amount' => 200],
        ]);
        $promotions = DiscountPromotion::query()->get();
        $result     = $this->calculator->calculateFinalDiscountedPrice($option, $promotions);
        // Only promo1 applies, promo2 is skipped
        expect($result)->toBe(900);
    });

    it('finds applied promotions for a given price', function (): void {
        $product = Product::factory()->create();
        $option  = ProductDeliveryOption::factory()->for($product)->create(['price' => 1000]);
        $promo1  = DiscountPromotion::factory()->create(['priority' => 1]);
        $promo2  = DiscountPromotion::factory()->create(['priority' => 2]);
        DiscountPromotionRule::factory()->create([
            'discount_promotion_id' => $promo1->id,
            'type'                  => 'action',
            'handler'               => 'apply_fixed_discount_product',
            'configuration'         => ['amount' => 100],
        ]);
        DiscountPromotionRule::factory()->create([
            'discount_promotion_id' => $promo2->id,
            'type'                  => 'action',
            'handler'               => 'apply_fixed_discount_product',
            'configuration'         => ['amount' => 200],
        ]);
        $promotions = DiscountPromotion::query()->get();
        $finalPrice = $this->calculator->calculateFinalDiscountedPrice($option, $promotions);
        $applied    = $this->calculator->findAppliedPromotionsForPrice($option, $promotions, $finalPrice);
        expect($applied->pluck('id')->all())->toEqual([$promo1->id, $promo2->id]);
    });

    it('applies condition: only applies if product in category', function (): void {
        $category = Category::factory()->create();
        $product  = Product::factory()->create();
        $product->categories()->attach($category);
        $option = ProductDeliveryOption::factory()->for($product)->create(['price' => 1000]);
        $promo  = DiscountPromotion::factory()->create(['priority' => 1]);
        DiscountPromotionRule::factory()->create([
            'discount_promotion_id' => $promo->id,
            'type'                  => 'condition',
            'handler'               => 'product_in_category',
            'configuration'         => ['category_ids' => [$category->id], 'match_policy' => 'any'],
        ]);
        DiscountPromotionRule::factory()->create([
            'discount_promotion_id' => $promo->id,
            'type'                  => 'action',
            'handler'               => 'apply_fixed_discount_product',
            'configuration'         => ['amount' => 100],
        ]);
        $promotions = DiscountPromotion::query()->get();
        $result     = $this->calculator->calculateFinalDiscountedPrice($option, $promotions);
        expect($result)->toBe(900);
    });

    it('does not apply condition if product not in category', function (): void {
        $category = Category::factory()->create();
        $product  = Product::factory()->create();
        $option   = ProductDeliveryOption::factory()->for($product)->create(['price' => 1000]);
        $promo    = DiscountPromotion::factory()->create(['priority' => 1]);
        DiscountPromotionRule::factory()->create([
            'discount_promotion_id' => $promo->id,
            'type'                  => 'condition',
            'handler'               => 'product_in_category',
            'configuration'         => ['category_ids' => [$category->id], 'match_policy' => 'any'],
        ]);
        DiscountPromotionRule::factory()->create([
            'discount_promotion_id' => $promo->id,
            'type'                  => 'action',
            'handler'               => 'apply_fixed_discount_product',
            'configuration'         => ['amount' => 100],
        ]);
        $promotions = DiscountPromotion::query()->get();
        $result     = $this->calculator->calculateFinalDiscountedPrice($option, $promotions);
        // Not in category, so no discount
        expect($result)->toBe(1000);
    });

    it('stops applying further promotions if a middle promotion has stop_processing_subsequent_rules = true', function (): void {
        $product = Product::factory()->create();
        $option  = ProductDeliveryOption::factory()->for($product)->create(['price' => 1000]);
        $promo1  = DiscountPromotion::factory()->create(['priority' => 1]);
        $promo2  = DiscountPromotion::factory()->create(['priority' => 2, 'stop_processing_subsequent_rules' => true]);
        $promo3  = DiscountPromotion::factory()->create(['priority' => 3]);
        DiscountPromotionRule::factory()->create([
            'discount_promotion_id' => $promo1->id,
            'type'                  => 'action',
            'handler'               => 'apply_fixed_discount_product',
            'configuration'         => ['amount' => 100],
        ]);

        DiscountPromotionRule::factory()->create([
            'discount_promotion_id' => $promo2->id,
            'type'                  => 'action',
            'handler'               => 'apply_fixed_discount_product',
            'configuration'         => ['amount' => 200],
        ]);
        DiscountPromotionRule::factory()->create([
            'discount_promotion_id' => $promo3->id,
            'type'                  => 'action',
            'handler'               => 'apply_fixed_discount_product',
            'configuration'         => ['amount' => 300],
        ]);
        $promotions = DiscountPromotion::query()->orderBy('priority')->get();
        $result     = $this->calculator->calculateFinalDiscountedPrice($option, $promotions);
        // 1000 - 100 = 900, then 900 - 200 = 700, then should STOP, so promo3 is not applied
        expect($result)->toBe(700);
    });
});
