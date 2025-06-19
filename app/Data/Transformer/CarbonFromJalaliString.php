<?php

declare(strict_types=1);

namespace App\Data\Transformer;

use DateTimeInterface;
use Hekmatinasser\Verta\Verta;
use InvalidArgumentException;
use Spatie\LaravelData\Casts\Cast;
use Spatie\LaravelData\Support\Creation\CreationContext;
use Spatie\LaravelData\Support\DataProperty;

final class CarbonFromJalaliString implements Cast
{
    public function cast(DataProperty $property, mixed $value, array $properties, CreationContext $context): mixed
    {
        if ($value instanceof Verta) {
            return $value->toCarbon();
        }
        if ($value instanceof DateTimeInterface) {
            return verta($value)->toCarbon();
        }

        if (is_string($value)
            && (
                preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value)
                || preg_match('/^\d{4}\/\d{2}\/\d{2} \d{2}:\d{2}:\d{2}$/', $value)
                || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)
                ||  preg_match('/^\d{4}\/\d{2}\/d{2}$/', $value)
            )
        ) {
            // If the value is a Jalali date string in the format 'Y-m-d H:i:s'
            return Verta::parse($value)->toCarbon();
        }

        throw new InvalidArgumentException(
            'Cannot cast value to Carbon from Jalali string: '.gettype($value)
        );
    }
}
