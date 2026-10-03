<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\ProductDeliveryOption;
use App\Models\Vendor;
use App\Services\Discounts\Configs\VendorIsData;
use App\Services\Discounts\Product\Conditions\VendorIsCondition;

describe('VendorIsCondition', function (): void {
    test('it passes when the product belongs to the specified vendor', function (): void {
        $condition = new VendorIsCondition();
        $vendor    = Vendor::factory()->create();
        $product   = Product::factory()->create(['vendor_id' => $vendor->id]);
        $option    = ProductDeliveryOption::factory()->create(['product_id' => $product->id]);

        $config = new VendorIsData(vendor_ids: [$vendor->id]);

        expect($condition->passes($option, $config))->toBeTrue();
    });

    test('it fails when the product does not belong to the specified vendor', function (): void {
        $condition = new VendorIsCondition();
        $vendor1   = Vendor::factory()->create();
        $vendor2   = Vendor::factory()->create();

        $product = Product::factory()->create(['vendor_id' => $vendor2->id]);
        $option  = ProductDeliveryOption::factory()->create(['product_id' => $product->id]);

        $config = new VendorIsData(vendor_ids: [$vendor1->id]);

        expect($condition->passes($option, $config))->toBeFalse();
    });
});
