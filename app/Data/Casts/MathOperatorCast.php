<?php

declare(strict_types=1);

namespace App\Data\Casts;

use App\Enums\Operators\MathOperatorEnum;
use Spatie\LaravelData\Casts\Cast;
use Spatie\LaravelData\Casts\Uncastable;
use Spatie\LaravelData\Support\Creation\CreationContext;
use Spatie\LaravelData\Support\DataProperty;

/**
 * Casts both spellings an operator can arrive in: the symbol stored in a rule
 * configuration (`>=`) and the snake_case name the admin metadata endpoint
 * publishes (`greater_than_or_equal`). Spatie's default enum cast only accepts
 * the symbol, so the documented metadata value would otherwise blow up at
 * evaluation time.
 */
final class MathOperatorCast implements Cast
{
    public function cast(DataProperty $property, mixed $value, array $properties, CreationContext $context): MathOperatorEnum|Uncastable
    {
        if ($value instanceof MathOperatorEnum) {
            return $value;
        }

        if (! is_string($value)) {
            return Uncastable::create();
        }

        return MathOperatorEnum::fromAlias($value) ?? Uncastable::create();
    }
}
