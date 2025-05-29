<?php

namespace App\Data\Transformer;

use Hekmatinasser\Verta\Verta;
use Spatie\LaravelData\Casts\Cast;
use Spatie\LaravelData\Support\Creation\CreationContext;
use Spatie\LaravelData\Support\DataProperty;

class CarbonFromJalaliString implements Cast
{
    public function cast(DataProperty $property, mixed $value, array $properties, CreationContext $context): mixed
    {
        if ($value instanceof Verta){
            return $value->toCarbon();
        }
        if ($value instanceof \DateTimeInterface) {
            return verta($value)->toCarbon();
        }

        if (is_string($value)) {
            try {
                return \Hekmatinasser\Verta\Facades\Verta::parse($value)->toCarbon();
            } catch (\Exception $e) {
                return null;
            }
        }

        return $value;
    }
}
