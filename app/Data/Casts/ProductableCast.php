<?php

namespace App\Data\Casts;

use App\Contracts\ProductableContract;
use App\Data\Course\ShowCourseData;
use App\Data\DigitalAsset\ShowDigitalAssetData;
use App\Data\Seminar\ShowSeminarData;
use App\Models\Course;
use App\Models\DigitalAsset;
use App\Models\Seminar;
use Spatie\LaravelData\Casts\Cast;
use Spatie\LaravelData\Support\Creation\CreationContext;
use Spatie\LaravelData\Support\DataProperty;

class ProductableCast implements Cast
{
    public function cast(DataProperty $property, mixed $value, array $properties, CreationContext $context): mixed
    {
        if (!$value) {
            return null;
        }
        if (!($value instanceof ProductableContract)) {
            throw new \InvalidArgumentException('Value must implement ProductableContract, ' . gettype($value) . ' given.');
        }

       return  match (true){
            $value instanceof Course => ShowCourseData::from([
                ...$value->toArray(),
                'media' => $value->getProductableMedia(),
                'categories' => $value->categories
            ]),
            $value instanceof Seminar => ShowSeminarData::from([
                ...$value->toArray(),
                'media' => $value->getProductableMedia(),
                'categories' => $value->categories
            ]),
            $value instanceof DigitalAsset => ShowDigitalAssetData::from([
                ...$value->toArray(),
                'media' => $value->getProductableMedia(),
                'attachments' => $value->getProductableAttachment(),
                'categories' => $value->categories
            ]),
            default => throw new \InvalidArgumentException('Unsupported productable type: ' . get_class($value)),
        };

    }
}
