<?php

declare(strict_types=1);

use App\Enums\Product\DeliveryMethodEnum;
use App\Enums\Product\FulfillmentTypeEnum;
use App\Enums\Product\ProductableEnum;
use App\Models\Bundle;

it('models bundles as composite delivery options', function (): void {
    expect(FulfillmentTypeEnum::COMPOSITE->getDeliveryMethods())
        ->toBe([DeliveryMethodEnum::BUNDLE]);
    expect(DeliveryMethodEnum::BUNDLE->getFulfillmentType())
        ->toBe(FulfillmentTypeEnum::COMPOSITE);
});

it('rejects virtuality checks for structural bundle delivery options', function (): void {
    expect(fn (): bool => DeliveryMethodEnum::BUNDLE->isVirtual())
        ->toThrow(LogicException::class);
});

it('registers bundle as a productable model', function (): void {
    expect(ProductableEnum::BUNDLE->getModelClass())->toBe(Bundle::class)
        ->and(ProductableEnum::BUNDLE->value)->toBe('bundle');
});
