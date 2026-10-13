<?php

declare(strict_types=1);

use App\Enums\Operators\MathOperatorEnum;

describe('MathOperatorEnum', function (): void {
    it('resolves both the stored symbol and the metadata name to one case', function (string $value, MathOperatorEnum $expected): void {
        expect(MathOperatorEnum::fromAlias($value))->toBe($expected);
    })->with([
        ['>=', MathOperatorEnum::GREATER_THAN_OR_EQUAL],
        ['greater_than_or_equal', MathOperatorEnum::GREATER_THAN_OR_EQUAL],
        ['>', MathOperatorEnum::GREATER_THAN],
        ['greater_than', MathOperatorEnum::GREATER_THAN],
        ['<=', MathOperatorEnum::LESS_THAN_OR_EQUAL],
        ['less_than_or_equal', MathOperatorEnum::LESS_THAN_OR_EQUAL],
        ['<', MathOperatorEnum::LESS_THAN],
        ['less_than', MathOperatorEnum::LESS_THAN],
        ['==', MathOperatorEnum::EQUAL],
        ['equal', MathOperatorEnum::EQUAL],
        ['<>', MathOperatorEnum::NOT_EQUAL],
        ['not_equal', MathOperatorEnum::NOT_EQUAL],
    ]);

    it('returns null for a value that is neither a symbol nor a case name', function (): void {
        expect(MathOperatorEnum::fromAlias('approximately'))->toBeNull()
            ->and(MathOperatorEnum::fromAlias('GREATER_THAN'))->toBeNull();
    });

    it('accepts the symbol and the metadata name of every case', function (): void {
        $accepted = MathOperatorEnum::acceptedValues();

        foreach (MathOperatorEnum::cases() as $case) {
            expect($accepted)->toContain($case->value)
                ->and($accepted)->toContain(mb_strtolower($case->name));
        }
    });
});
