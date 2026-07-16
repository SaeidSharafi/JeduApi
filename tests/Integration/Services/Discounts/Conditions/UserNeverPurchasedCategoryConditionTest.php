<?php

declare(strict_types=1);

use App\Data\Admin\Discounts\OrderContextData;
use App\Models\Category;
use App\Models\Enrollment;
use App\Models\Product;
use App\Models\ProductDeliveryOption;
use App\Models\User;
use App\Services\Discounts\Cart\Conditions\UserNeverPurchasedCategoryCondition;
use App\Services\Discounts\Configs\UserNeverPurchasedCategoryData;

describe('UserNeverPurchasedCategoryCondition', function (): void {
    test('it passes when user has never enrolled in the target category', function (): void {
        $condition = new UserNeverPurchasedCategoryCondition();
        $user = User::factory()->create();
        $targetCategory = Category::factory()->create();

        $config = new UserNeverPurchasedCategoryData(category_ids: [$targetCategory->id]);

        $context = OrderContextData::from([
            'customer' => $user,
            'items' => [],
            'subtotal_full_payment_items' => 0,
            'subtotal_all_items' => 0,
        ]);

        expect($condition->passes($context, $config))->toBeTrue();
    });

    test('it fails when user has an enrollment in the target category', function (): void {
        $condition = new UserNeverPurchasedCategoryCondition();
        $user = User::factory()->create();
        $targetCategory = Category::factory()->create();
        $product = Product::factory()->create();
        $product->categories()->attach($targetCategory);

        $pdo = ProductDeliveryOption::factory()->create(['product_id' => $product->id]);

        Enrollment::factory()->create([
            'customer_id' => $user->id,
            'product_delivery_option_id' => $pdo->id,
        ]);

        $config = new UserNeverPurchasedCategoryData(category_ids: [$targetCategory->id]);

        $context = OrderContextData::from([
            'customer' => $user,
            'items' => [],
            'subtotal_full_payment_items' => 0,
            'subtotal_all_items' => 0,
        ]);

        expect($condition->passes($context, $config))->toBeFalse();
    });
});
