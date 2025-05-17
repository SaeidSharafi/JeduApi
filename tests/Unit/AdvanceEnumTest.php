<?php

declare(strict_types=1);

use Tests\Unit\Fixtures\TestEnum;

describe('AdvanceEnum', function (): void {
    it('getAllValues returns all enum values', function (): void {
        $values = TestEnum::getAllValues();
        expect($values)->toBe(['foo', 'bar']);
    });

    it('getAllNames returns all enum names', function (): void {
        $names = TestEnum::getAllNames();
        expect($names)->toBe(['FOO', 'BAR']);
    });

    it('translate returns translation string', function (): void {
        $enum = TestEnum::FOO;
        expect($enum->translate())->toBe('enums.TestEnum.foo');
    });

    it('getKeyValuePairs returns value=>translation array', function (): void {
        $enum = TestEnum::FOO;
        $pairs = $enum->getKeyValuePairs();
        expect($pairs)->toBe([
            'foo' => 'enums.TestEnum.foo',
            'bar' => 'enums.TestEnum.bar',
        ]);
    });

    it('getValueLabel returns value-label array', function (): void {
        $enum = TestEnum::FOO;
        $labels = $enum->getValueLabel();
        expect($labels)->toBe([
            ['value' => 'foo', 'label' => 'enums.TestEnum.foo'],
            ['value' => 'bar', 'label' => 'enums.TestEnum.bar'],
        ]);
    });
});
