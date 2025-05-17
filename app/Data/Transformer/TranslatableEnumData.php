<?php

namespace App\Data\Transformer;

use Spatie\LaravelData\Casts\Cast;
use Spatie\LaravelData\Support\Creation\CreationContext;
use Spatie\LaravelData\Support\DataProperty;
use Spatie\LaravelData\Support\Transformation\TransformationContext;
use Spatie\LaravelData\Transformers\Transformer;

class TranslatableEnumData implements Transformer
{

    public function transform(DataProperty $property, mixed $value, TransformationContext $context): mixed
    {
        return $value->translate();
    }
}
