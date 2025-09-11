<?php

declare(strict_types=1);

describe('DiscountTypeEnum', function (): void {
    it('should return all enum values as an array', function (): void {
        $values = App\Enums\Order\DiscountTypeEnum::getAllValues();
        expect($values)->toBeArray()
            ->and($values)->toHaveCount(2)
            ->and($values)->toContain('product_specific')
            ->and($values)->toContain('cart_checkout');
    });

    it('should return all enum names as an array', function (): void {
        $names = App\Enums\Order\DiscountTypeEnum::getAllNames();
        expect($names)->toBeArray()
            ->and($names)->toHaveCount(2)
            ->and($names)->toContain('PRODUCT_SPECIFIC')
            ->and($names)->toContain('CART_CHECKOUT');
    });

    it('should translate enum values correctly', function (): void {
        $translation = App\Enums\Order\DiscountTypeEnum::PRODUCT_SPECIFIC->translate();
        expect($translation)->toBeString()
            ->and($translation)->not->toBeEmpty();
    });

    it('should return key-value pairs for enum', function (): void {
        $keyValuePairs = App\Enums\Order\DiscountTypeEnum::PRODUCT_SPECIFIC->getKeyValuePairs();
        expect($keyValuePairs)->toBeArray()
            ->and($keyValuePairs)->toHaveKey('product_specific')
            ->and($keyValuePairs['product_specific'])->not->toBeEmpty();
    });

    it('should return value-label array for enum', function (): void {
        $valueLabel = App\Enums\Order\DiscountTypeEnum::PRODUCT_SPECIFIC->getValueLabel();
        expect($valueLabel)->toBeArray()
            ->and($valueLabel[0])->toHaveKey('value')
            ->and($valueLabel[0])->toHaveKey('label');
    });

    it('should return list info with description', function (): void {
        $listInfo = App\Enums\Order\DiscountTypeEnum::getListInfo();
        expect($listInfo)->toBeArray()
            ->and($listInfo[0])->toHaveKey('description');
    });
});
