<?php

declare(strict_types=1);

use App\Actions\Admin\Discounts\CreateDiscountPromotionAction;
use App\Actions\Admin\Discounts\UpdateDiscountPromotionAction;
use App\Actions\Admin\Discounts\DeleteDiscountPromotionAction;
use App\Data\Admin\Discounts\DiscountPromotionCreateData;
use App\Data\Admin\Discounts\DiscountPromotionRuleCreateData;
use App\Data\Admin\Discounts\DiscountCouponCreateData;
use App\Enums\Order\DiscountTypeEnum;
use App\Models\DiscountPromotion;
use Spatie\LaravelData\DataCollection;

describe('Discount Promotion Actions', function (): void {
    uses()->group('unit', 'actions', 'discounts');

    test('CreateDiscountPromotionAction creates promotion with rules and coupons', function (): void {
        // Arrange
        $data = DiscountPromotionCreateData::from([
            'name' => 'Summer Sale',
            'description' => 'Great summer discounts',
            'type' => DiscountTypeEnum::CART_CHECKOUT->value,
            'is_active' => true,
            'priority' => 100,
            'usage_limit_total' => 1000,
            'stop_processing_subsequent_rules' => false,
            'rules' => [
                [
                    'type' => 'condition',
                    'handler' => 'cart_value_over',
                    'configuration' => ['value' => 10000, 'operator' => 'greater_than_or_equal'],
                ],
                [
                    'type' => 'action',
                    'handler' => 'apply_percentage_off',
                    'configuration' => ['percentage' => 20],
                ],
            ],
            'coupons' => [
                [
                    'code' => 'SUMMER2024',
                    'is_active' => true,
                    'usage_limit' => 100,
                ]
            ],
        ]);

        $action = app(CreateDiscountPromotionAction::class);

        // Act
        $promotion = $action->execute($data);

        // Assert
        expect($promotion)->toBeInstanceOf(DiscountPromotion::class)
            ->and($promotion->name)->toBe('Summer Sale')
            ->and($promotion->description)->toBe('Great summer discounts')
            ->and($promotion->type)->toBe(DiscountTypeEnum::CART_CHECKOUT)
            ->and($promotion->is_active)->toBeTrue()
            ->and($promotion->priority)->toBe(100)
            ->and($promotion->usage_limit_total)->toBe(1000)
            ->and($promotion->total_usage_count)->toBe(0)
            ->and($promotion->rules)->toHaveCount(2)
            ->and($promotion->coupons)->toHaveCount(1);

        // Check rules
        $conditionRule = $promotion->rules->where('type', 'condition')->first();
        expect($conditionRule->handler)->toBe('cart_value_over')
            ->and($conditionRule->configuration)->toBe(['value' => 10000, 'operator' => 'greater_than_or_equal']);

        $actionRule = $promotion->rules->where('type', 'action')->first();
        expect($actionRule->handler)->toBe('apply_percentage_off')
            ->and($actionRule->configuration)->toBe(['percentage' => 20]);

        // Check coupon
        $coupon = $promotion->coupons->first();
        expect($coupon->code)->toBe('SUMMER2024')
            ->and($coupon->is_active)->toBeTrue()
            ->and($coupon->usage_limit)->toBe(100)
            ->and($coupon->usage_count)->toBe(0);
    });

    test('CreateDiscountPromotionAction creates promotion without coupons', function (): void {
        // Arrange
        $data = DiscountPromotionCreateData::from([
            'name' => 'Auto Discount',
            'description' => 'Automatic cart discount',
            'type' => DiscountTypeEnum::CART_CHECKOUT->value,
            'is_active' => true,
            'starts_at' => now()->format('Y-m-d H:i:s'),
            'ends_at' => now()->addDays(30)->format('Y-m-d H:i:s'),
            'priority' => 1,
            'stop_processing_subsequent_rules' => false,
            'usage_limit_total' => null,
            'usage_limit_per_customer' => null,
            'rules' => [
                [
                    'type' => 'action',
                    'handler' => 'apply_percentage_off',
                    'configuration' => ['percentage' => 10],
                ],
            ],
            'coupons' => [],
        ]);

        $action = app(CreateDiscountPromotionAction::class);

        // Act
        $promotion = $action->execute($data);

        // Assert
        expect($promotion->coupons)->toHaveCount(0);
    });

    test('UpdateDiscountPromotionAction updates existing promotion', function (): void {
        // Arrange
        $promotion = DiscountPromotion::factory()->create([
            'name' => 'Old Name',
            'description' => 'Old Description',
        ]);

        $promotion->rules()->create([
            'type' => 'action',
            'handler' => 'old_handler',
            'configuration' => ['old' => 'config'],
        ]);

        $updateData = DiscountPromotionCreateData::from([
            'name' => 'Updated Name',
            'description' => 'Updated Description',
            'type' => DiscountTypeEnum::CART_CHECKOUT->value,
            'is_active' => true,
            'starts_at' => now()->format('Y-m-d H:i:s'),
            'ends_at' => now()->addDays(30)->format('Y-m-d H:i:s'),
            'priority' => 1,
            'stop_processing_subsequent_rules' => false,
            'usage_limit_total' => null,
            'usage_limit_per_customer' => null,
            'rules' => [
                [
                    'type' => 'action',
                    'handler' => 'apply_percentage_off',
                    'configuration' => ['percentage' => 25],
                ],
            ],
            'coupons' => [],
        ]);

        $action = app(UpdateDiscountPromotionAction::class);

        // Act
        $updatedPromotion = $action->execute($promotion, $updateData);

        // Assert
        expect($updatedPromotion->name)->toBe('Updated Name')
            ->and($updatedPromotion->description)->toBe('Updated Description')
            ->and($updatedPromotion->rules)->toHaveCount(1);

        $rule = $updatedPromotion->rules->first();
        expect($rule->handler)->toBe('apply_percentage_off')
            ->and($rule->configuration)->toBe(['percentage' => 25]);
    });

    test('UpdateDiscountPromotionAction replaces all rules and coupons', function (): void {
        // Arrange
        $promotion = DiscountPromotion::factory()->create();

        $promotion->rules()->createMany([
            ['type' => 'condition', 'handler' => 'old1', 'configuration' => []],
            ['type' => 'action', 'handler' => 'old2', 'configuration' => []],
        ]);

        $promotion->coupons()->create(['code' => 'OLDCODE', 'is_active' => true]);

        $updateData = DiscountPromotionCreateData::from([
            'name' => $promotion->name,
            'description' => $promotion->description,
            'type' => $promotion->type->value,
            'is_active' => $promotion->is_active,
            'starts_at' => $promotion->starts_at ? $promotion->starts_at->format('Y-m-d H:i:s') : now()->format('Y-m-d H:i:s'),
            'ends_at' => $promotion->ends_at ? $promotion->ends_at->format('Y-m-d H:i:s') : now()->addDays(30)->format('Y-m-d H:i:s'),
            'priority' => $promotion->priority,
            'stop_processing_subsequent_rules' => false,
            'usage_limit_total' => $promotion->usage_limit_total,
            'usage_limit_per_customer' => $promotion->usage_limit_per_customer,
            'rules' => [
                [
                    'type' => 'condition',
                    'handler' => 'new_condition',
                    'configuration' => ['new' => 'config'],
                ],
            ],
            'coupons' => [
                ['code' => 'NEWCODE', 'is_active' => true, 'usage_limit' => 100],
            ],
        ]);

        $action = app(UpdateDiscountPromotionAction::class);

        // Act
        $updatedPromotion = $action->execute($promotion, $updateData);

        // Assert
        expect($updatedPromotion->rules)->toHaveCount(1)
            ->and($updatedPromotion->coupons)->toHaveCount(1);

        $rule = $updatedPromotion->rules->first();
        expect($rule->handler)->toBe('new_condition');

        $coupon = $updatedPromotion->coupons->first();
        expect($coupon->code)->toBe('NEWCODE');
    });

    test('DeleteDiscountPromotionAction removes promotion and related data', function (): void {
        // Arrange
        $promotion = DiscountPromotion::factory()->create();

        $promotion->rules()->create([
            'type' => 'action',
            'handler' => 'test_handler',
            'configuration' => [],
        ]);

        $promotion->coupons()->create([
            'code' => 'TESTCODE',
            'is_active' => true,
        ]);

        $promotionId = $promotion->id;
        $action = app(DeleteDiscountPromotionAction::class);

        // Act
        $action->execute($promotion);

        // Assert
        expect(DiscountPromotion::find($promotionId))->toBeNull();

        // Verify related data is also deleted
        $this->assertDatabaseMissing('discount_promotion_rules', [
            'discount_promotion_id' => $promotionId,
        ]);

        $this->assertDatabaseMissing('discount_coupons', [
            'discount_promotion_id' => $promotionId,
        ]);
    });

    test('CreateDiscountPromotionAction handles transaction rollback on failure', function (): void {
        // Arrange
        $data = DiscountPromotionCreateData::from([
            'name' => 'Test Promotion',
            'description' => 'Test Description',
            'type' => DiscountTypeEnum::CART_CHECKOUT->value,
            'is_active' => true,
            'starts_at' => now()->format('Y-m-d H:i:s'),
            'ends_at' => now()->addDays(30)->format('Y-m-d H:i:s'),
            'priority' => 1,
            'stop_processing_subsequent_rules' => false,
            'usage_limit_total' => null,
            'usage_limit_per_customer' => null,
            'rules' => [
                [
                    'type' => 'action',
                    'handler' => 'apply_percentage_off',
                    'configuration' => ['percentage' => 15],
                ],
            ],
            'coupons' => [
                [
                    'code' => 'DUPLICATE',
                    'is_active' => true,
                    'usage_limit' => 100,
                ],
                [
                    'code' => 'DUPLICATE', // This will cause a constraint violation
                    'is_active' => true,
                    'usage_limit' => 100,
                ],
            ],
        ]);

        $action = app(CreateDiscountPromotionAction::class);

        // Act & Assert
        expect(fn() => $action->execute($data))->toThrow(Exception::class);

        // Verify no partial data was saved
        $this->assertDatabaseMissing('discount_promotions', [
            'name' => 'Test Promotion',
        ]);
    });
});
