<?php

declare(strict_types=1);

namespace App\Data\Transformer;

use App\Exceptions\InvalidJalaliDateException;
use Carbon\Carbon;
use DateTimeInterface;
use Exception;
use Hekmatinasser\Verta\Verta;
use Spatie\LaravelData\Casts\Cast;
use Spatie\LaravelData\Support\Creation\CreationContext;
use Spatie\LaravelData\Support\DataProperty;

final readonly class CarbonFromJalaliString implements Cast
{
    public function __construct(
        private ?string $format = null
    ) {}

    public function cast(DataProperty $property, mixed $value, array $properties, CreationContext $context): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value;
        }

        if ($value instanceof Verta) {
            return $value->toCarbon();
        }
        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value);
        }

        if (is_string($value)) {
            try {
                // Use the format provided in the attribute if available
                if ($this->format) {
                    return Verta::parseFormat($this->format, $value)->toCarbon();
                }

                return Verta::parse($value)->toCarbon();
            } catch (Exception $e) {
                throw new InvalidJalaliDateException($property->name, $value);
            }
        }
        // Also throw our new exception for invalid types
        throw new InvalidJalaliDateException($property->name, $value);
    }
}
