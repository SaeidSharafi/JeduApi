<?php

declare(strict_types=1);

namespace App\Data\Casts;

use App\Contracts\ProductableContract;
use App\Data\Admin\Course\CourseListItemData;
use App\Data\Admin\Course\ShowCourseData;
use App\Data\Admin\DigitalAsset\DigitalAssetListItemData;
use App\Data\Admin\DigitalAsset\ShowDigitalAssetData;
use App\Data\Admin\Seminar\SeminarListItemData;
use App\Data\Admin\Seminar\ShowSeminarData;
use App\Models\Course;
use App\Models\DigitalAsset;
use App\Models\Seminar;
use InvalidArgumentException;
use Spatie\LaravelData\Casts\Cast;
use Spatie\LaravelData\Support\Creation\CreationContext;
use Spatie\LaravelData\Support\DataProperty;

final readonly class ProductableCast implements Cast
{
    public function __construct(private bool $short = false) {}

    public function cast(DataProperty $property, mixed $value, array $properties, CreationContext $context): mixed
    {
        if (! $value) {
            return null;
        }
        if (! ($value instanceof ProductableContract)) {
            throw new InvalidArgumentException('Value must implement ProductableContract, '.gettype($value).' given.');
        }

        return match (true) {
            $value instanceof Course       => $this->getCourseData($value),
            $value instanceof Seminar      => $this->getSeminarData($value),
            $value instanceof DigitalAsset => $this->getDigitalAssetData($value),
            default                        => throw new InvalidArgumentException('Unsupported productable type: '.get_class($value)),
        };

    }

    private function getCourseData($value): ShowCourseData|CourseListItemData
    {
        if ($this->short) {
            return CourseListItemData::from([
                ...$value->toArray(),
                'media'      => [],
                'categories' => [],
            ]);
        }

        return ShowCourseData::from([
            ...$value->toArray(),
            'media'      => $value->getProductableMedia(),
            'categories' => $value->categories,
        ]);
    }

    private function getSeminarData($value): ShowSeminarData|SeminarListItemData
    {
        if ($this->short) {
            return SeminarListItemData::from([
                ...$value->toArray(),
                'media'      => [],
                'categories' => [],
            ]);
        }

        return ShowSeminarData::from([
            ...$value->toArray(),
            'media'      => $value->getProductableMedia(),
            'categories' => $value->categories,
        ]);
    }

    private function getDigitalAssetData($value): ShowDigitalAssetData|DigitalAssetListItemData
    {
        if ($this->short) {
            return DigitalAssetListItemData::from([
                ...$value->toArray(),
                'media'       => [],
                'attachments' => $value->getProductableAttachment(),
                'categories'  => [],
            ]);
        }

        return ShowDigitalAssetData::from([
            ...$value->toArray(),
            'media'       => $value->getProductableMedia(),
            'attachments' => $value->getProductableAttachment(),
            'categories'  => $value->categories,
        ]);
    }
}
