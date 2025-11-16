<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\DiscountPromotion;
use App\Models\DiscountPromotionRule;
use App\Models\Product;
use App\Models\ProductDeliveryOption;
use App\Models\ProductDeliveryOptionDiscountPrice;
use App\Services\Discounts\DiscountHandlerRegistry;
use App\Services\Discounts\ProductDiscountIndexer;
use App\Services\Discounts\ProductDiscountPriceCalculator;

describe('ProductDiscountIndexer', function (): void {
    beforeEach(function (): void {
        $this->registry   = app(DiscountHandlerRegistry::class);
        $this->calculator = new ProductDiscountPriceCalculator($this->registry);
        $this->indexer    = new ProductDiscountIndexer($this->registry, $this->calculator);
    });

    it('performs a full reindex and creates correct discount price records', function (): void {
        $product   = Product::factory()->create();
        $option    = ProductDeliveryOption::factory()->for($product)->create(['price' => 1000]);
        $promotion = DiscountPromotion::factory()->create(['priority' => 1]);
        DiscountPromotionRule::factory()->create([
            'discount_promotion_id' => $promotion->id,
            'type'                  => 'action',
            'handler'               => 'apply_fixed_discount_product',
            'configuration'         => ['amount' => 200],
        ]);
        $this->indexer->reIndexComplete();
        $discounted = ProductDeliveryOptionDiscountPrice::where('product_delivery_option_id', $option->id)->first();
        expect($discounted)->not()->toBeNull();
        expect($discounted->discounted_price)->toBe(800);
        expect($discounted->discount_promotion_id)->toBe($promotion->id);
    });

    it('handles multiple products and layered promotions with priorities', function (): void {
        $product1 = Product::factory()->create();
        $product2 = Product::factory()->create();
        $option1  = ProductDeliveryOption::factory()->for($product1)->create(['price' => 1000]);
        $option2  = ProductDeliveryOption::factory()->for($product2)->create(['price' => 2000]);
        $promo1   = DiscountPromotion::factory()->create(['priority' => 1]);
        $promo2   = DiscountPromotion::factory()->create(['priority' => 2]);
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
        $this->indexer->reIndexComplete();
        $d1 = ProductDeliveryOptionDiscountPrice::where('product_delivery_option_id', $option1->id)->first();
        $d2 = ProductDeliveryOptionDiscountPrice::where('product_delivery_option_id', $option2->id)->first();
        expect($d1)->not()->toBeNull();
        expect($d2)->not()->toBeNull();
        expect($d1->discounted_price)->toBe(700); // 1000-100-200
        expect($d2->discounted_price)->toBe(1700); // 2000-100-200
        expect($d1->discount_promotion_id)->toBe($promo1->id); // highest priority
    });

    it('reindexes only for a single promotion', function (): void {
        $product = Product::factory()->create();
        $option  = ProductDeliveryOption::factory()->for($product)->create(['price' => 1000]);
        $promo   = DiscountPromotion::factory()->create(['priority' => 1]);
        DiscountPromotionRule::factory()->create([
            'discount_promotion_id' => $promo->id,
            'type'                  => 'action',
            'handler'               => 'apply_fixed_discount_product',
            'configuration'         => ['amount' => 100],
        ]);
        $this->indexer->reIndexPromotion($promo);
        $discounted = ProductDeliveryOptionDiscountPrice::where('product_delivery_option_id', $option->id)->first();
        expect($discounted)->not()->toBeNull();
        expect($discounted->discounted_price)->toBe(900);
        expect($discounted->discount_promotion_id)->toBe($promo->id);
    });

    it('reindexes only for specific product delivery options', function (): void {
        $product = Product::factory()->create();
        $option  = ProductDeliveryOption::factory()->for($product)->create(['price' => 1000]);
        $promo   = DiscountPromotion::factory()->create(['priority' => 1]);
        DiscountPromotionRule::factory()->create([
            'discount_promotion_id' => $promo->id,
            'type'                  => 'action',
            'handler'               => 'apply_fixed_discount_product',
            'configuration'         => ['amount' => 100],
        ]);
        $this->indexer->reIndexProductsByDeliveryOptionIds(collect([$option->id]));
        $discounted = ProductDeliveryOptionDiscountPrice::where('product_delivery_option_id', $option->id)->first();
        expect($discounted)->not()->toBeNull();
        expect($discounted->discounted_price)->toBe(900);
        expect($discounted->discount_promotion_id)->toBe($promo->id);
    });

    it('does not create records if there are no promotions', function (): void {
        $product = Product::factory()->create();
        $option  = ProductDeliveryOption::factory()->for($product)->create(['price' => 1000]);
        $this->indexer->reIndexComplete();
        $discounted = ProductDeliveryOptionDiscountPrice::where('product_delivery_option_id', $option->id)->first();
        expect($discounted)->toBeNull();
    });

    it('does not create records for inactive or expired promotions', function (): void {
        $product  = Product::factory()->create();
        $option   = ProductDeliveryOption::factory()->for($product)->create(['price' => 1000]);
        $inactive = DiscountPromotion::factory()->create(['is_active' => false]);
        $expired  = DiscountPromotion::factory()->create(['ends_at' => now()->subDay()]);
        DiscountPromotionRule::factory()->create([
            'discount_promotion_id' => $inactive->id,
            'type'                  => 'action',
            'handler'               => 'apply_fixed_discount_product',
            'configuration'         => ['amount' => 100],
        ]);
        DiscountPromotionRule::factory()->create([
            'discount_promotion_id' => $expired->id,
            'type'                  => 'action',
            'handler'               => 'apply_fixed_discount_product',
            'configuration'         => ['amount' => 100],
        ]);
        $this->indexer->reIndexComplete();
        $discounted = ProductDeliveryOptionDiscountPrice::where('product_delivery_option_id', $option->id)->first();
        expect($discounted)->toBeNull();
    });

    it('does not create records if no discount is applied', function (): void {
        $product = Product::factory()->create();
        $option  = ProductDeliveryOption::factory()->for($product)->create(['price' => 1000]);
        $promo   = DiscountPromotion::factory()->create(['priority' => 1]);
        DiscountPromotionRule::factory()->create([
            'discount_promotion_id' => $promo->id,
            'type'                  => 'action',
            'handler'               => 'apply_fixed_discount_product',
            'configuration'         => ['amount' => 0],
        ]);
        $this->indexer->reIndexComplete();
        $discounted = ProductDeliveryOptionDiscountPrice::where('product_delivery_option_id', $option->id)->first();
        expect($discounted)->toBeNull();
    });

    it('applies promotions with conditions (e.g., product in category)', function (): void {
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
        $this->indexer->reIndexComplete();
        $discounted = ProductDeliveryOptionDiscountPrice::where('product_delivery_option_id', $option->id)->first();
        expect($discounted)->not()->toBeNull();
        expect($discounted->discounted_price)->toBe(900);
        expect($discounted->discount_promotion_id)->toBe($promo->id);
    });

    it('does not apply promotions with conditions if not met', function (): void {
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
        $this->indexer->reIndexComplete();
        $discounted = ProductDeliveryOptionDiscountPrice::where('product_delivery_option_id', $option->id)->first();
        expect($discounted)->toBeNull();
    });

    it('can clean all discount price records', function (): void {
        $product = Product::factory()->create();
        $option  = ProductDeliveryOption::factory()->for($product)->create(['price' => 1000]);
        $promo   = DiscountPromotion::factory()->create(['priority' => 1]);
        DiscountPromotionRule::factory()->create([
            'discount_promotion_id' => $promo->id,
            'type'                  => 'action',
            'handler'               => 'apply_fixed_discount_product',
            'configuration'         => ['amount' => 100],
        ]);
        $this->indexer->reIndexComplete();
        $this->indexer->cleanAllDiscountPrices();
        $discounted = ProductDeliveryOptionDiscountPrice::where('product_delivery_option_id', $option->id)->first();
        expect($discounted)->toBeNull();
    });

    it('can clean discount price records for a specific promotion', function (): void {
        $product = Product::factory()->create();
        $option  = ProductDeliveryOption::factory()->for($product)->create(['price' => 1000]);
        $promo   = DiscountPromotion::factory()->create(['priority' => 1]);
        DiscountPromotionRule::factory()->create([
            'discount_promotion_id' => $promo->id,
            'type'                  => 'action',
            'handler'               => 'apply_fixed_discount_product',
            'configuration'         => ['amount' => 100],
        ]);
        $this->indexer->reIndexComplete();
        $this->indexer->cleanPromotionIndices($promo);
        $discounted = ProductDeliveryOptionDiscountPrice::where('product_delivery_option_id', $option->id)->first();
        expect($discounted)->toBeNull();
    });

    it('does nothing if reIndexProductsByDeliveryOptionIds is called with empty collection', function (): void {
        $this->indexer->reIndexProductsByDeliveryOptionIds(collect([]));
        // Should not throw or do anything
        expect(ProductDeliveryOptionDiscountPrice::count())->toBe(0);
    });

    it('findBestApplicablePromotion returns null if no promotions apply', function (): void {
        $product = Product::factory()->create();
        $option  = ProductDeliveryOption::factory()->for($product)->create(['price' => 1000]);
        $promo   = DiscountPromotion::factory()->create(['priority' => 1]);
        // Add a condition that will never pass
        DiscountPromotionRule::factory()->create([
            'discount_promotion_id' => $promo->id,
            'type'                  => 'condition',
            'handler'               => 'nonexistent_handler',
            'configuration'         => [],
        ]);
        $reflection = new ReflectionClass($this->indexer);
        $method     = $reflection->getMethod('findBestApplicablePromotion');
        $method->setAccessible(true);
        $result = $method->invoke($this->indexer, $option, collect([$promo]));
        expect($result)->toBeNull();
    });

    it('getRepresentativePromotionId falls back to first promotion if none match', function (): void {
        $product = Product::factory()->create();
        $option  = ProductDeliveryOption::factory()->for($product)->create(['price' => 1000]);
        $promo1  = DiscountPromotion::factory()->create(['priority' => 1]);
        $promo2  = DiscountPromotion::factory()->create(['priority' => 2]);
        // Add a condition that will never pass
        DiscountPromotionRule::factory()->create([
            'discount_promotion_id' => $promo1->id,
            'type'                  => 'condition',
            'handler'               => 'nonexistent_handler',
            'configuration'         => [],
        ]);
        $reflection = new ReflectionClass($this->indexer);
        $method     = $reflection->getMethod('getRepresentativePromotionId');
        $method->setAccessible(true);
        $promos = collect([$promo1, $promo2]);
        $result = $method->invoke($this->indexer, $option, $promos);
        expect([$promo1->id, $promo2->id])->toContain($result);
    });

    it('allConditionsPass returns false if handler class not found', function (): void {
        $product = Product::factory()->create();
        $option  = ProductDeliveryOption::factory()->for($product)->create(['price' => 1000]);
        $promo   = DiscountPromotion::factory()->create(['priority' => 1]);
        DiscountPromotionRule::factory()->create([
            'discount_promotion_id' => $promo->id,
            'type'                  => 'condition',
            'handler'               => 'nonexistent_handler',
            'configuration'         => [],
        ]);
        $reflection = new ReflectionClass($this->indexer);
        $method     = $reflection->getMethod('allConditionsPass');
        $method->setAccessible(true);
        $result = $method->invoke($this->indexer, $promo, $option);
        expect($result)->toBeFalse();
    });

});
