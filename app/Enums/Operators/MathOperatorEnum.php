<?php

declare(strict_types=1);

namespace App\Enums\Operators;

use App\Traits\AdvanceEnum;
use Illuminate\Support\Arr;

enum MathOperatorEnum: string
{
    /** @use AdvanceEnum<value-of<self>> */
    use AdvanceEnum;
    case EQUAL                 = '==';
    case LESS_THAN             = '<';
    case GREATER_THAN          = '>';
    case LESS_THAN_OR_EQUAL    = '<=';
    case GREATER_THAN_OR_EQUAL = '>=';
    case NOT_EQUAL             = '<>';

    /**
     * The admin metadata endpoint publishes operators under their snake_case
     * case name (`greater_than_or_equal`) while a stored rule configuration
     * holds the symbol (`>=`), because `rules[].configuration` is persisted
     * verbatim. Both spellings therefore have to resolve to the same case.
     */
    public static function fromAlias(string $value): ?self
    {
        return self::tryFrom($value)
            ?? Arr::first(self::cases(), fn (self $case): bool => in_array($value, self::spellings($case), true));
    }

    /**
     * Every spelling a stored configuration or the metadata endpoint can send.
     *
     * @return list<string>
     */
    public static function acceptedValues(): array
    {
        return collect(self::cases())
            ->flatMap(fn (self $case): array => self::spellings($case))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * One case's two spellings, so resolution and validation cannot drift apart.
     *
     * @return list<string>
     */
    private static function spellings(self $case): array
    {
        return [$case->value, mb_strtolower($case->name)];
    }
}
