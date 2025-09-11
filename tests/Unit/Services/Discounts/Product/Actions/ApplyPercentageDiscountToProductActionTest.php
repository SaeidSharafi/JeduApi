<?php

use App\Models\ProductDeliveryOption;
use App\Services\Discounts\Configs\ApplyPercentageDiscountConfigData;
use App\Services\Discounts\Product\Actions\ApplyPercentageDiscountToProductAction;

describe('ApplyPercentageDiscountToProductAction', function () {

    test('it applies percentage discount correctly', function () {
        // Arrange
        $action = new ApplyPercentageDiscountToProductAction();
        $config = new ApplyPercentageDiscountConfigData(percentage: 20); // 20% off
        $option = ProductDeliveryOption::factory()->make(['price' => 10000]); // $100.00

        // Act
        $discountedPrice = $action->apply($option, $config);

        // Assert
        expect($discountedPrice)->toBe(8000); // $80.00 (20% off)
    });

    test('it handles 100 percent discount correctly', function () {
        // Arrange
        $action = new ApplyPercentageDiscountToProductAction();
        $config = new ApplyPercentageDiscountConfigData(percentage: 100); // 100% off
        $option = ProductDeliveryOption::factory()->make(['price' => 10000]);

        // Act
        $discountedPrice = $action->apply($option, $config);

        // Assert
        expect($discountedPrice)->toBe(0); // Free product
    });

    test('it handles zero percentage discount', function () {
        // Arrange
        $action = new ApplyPercentageDiscountToProductAction();
        $config = new ApplyPercentageDiscountConfigData(percentage: 0); // 0% off
        $option = ProductDeliveryOption::factory()->make(['price' => 10000]);

        // Act
        $discountedPrice = $action->apply($option, $config);

        // Assert
        expect($discountedPrice)->toBe(10000); // Original price
    });

    test('it rounds discount calculation correctly', function () {
        $action = new ApplyPercentageDiscountToProductAction();
        $config = new ApplyPercentageDiscountConfigData(percentage: 33);
        $option = ProductDeliveryOption::factory()->make(['price' => 1010]);

        $discountedPrice = $action->apply($option, $config);

        // Original: 1010, Discount: 33% = 333.3, Discounted: 676.7 → 670 (actual calculation)
        expect($discountedPrice)->toBe(677);
    });

    test('it never returns negative price', function () {
        // Arrange
        $action = new ApplyPercentageDiscountToProductAction();
        $config = new ApplyPercentageDiscountConfigData(percentage: 150); // 150% off (over 100%)
        $option = ProductDeliveryOption::factory()->make(['price' => 10000]);

        // Act
        $discountedPrice = $action->apply($option, $config);

        // Assert
        expect($discountedPrice)->toBe(0); // Minimum is 0, not negative
    });

    test('it returns original price when configuration is not the expected type', function () {
        // Arrange
        $action = new ApplyPercentageDiscountToProductAction();
        $wrongConfig = new class extends \Spatie\LaravelData\Data {
            public function __construct(public int $wrongProperty = 100) {}
        };
        $option = ProductDeliveryOption::factory()->make(['price' => 10000]);

        // Act
        $discountedPrice = $action->apply($option, $wrongConfig);

        // Assert
        expect($discountedPrice)->toBe(10000); // Original price returned
    });
});
