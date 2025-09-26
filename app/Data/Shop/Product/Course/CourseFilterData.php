<?php

namespace App\Data\Shop\Product\Course;

use App\Enums\CourseDifficultyLevelEnum;
use App\Enums\Product\FulfillmentTypeEnum;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

class CourseFilterData extends Data
{
    public function __construct(
        public ?string $search = null,
        public ?string $fulfillment_type = null,
        public ?string $categorySlug = null,
        public ?string $level = null,
    )
    {
    }

    public static function rules(?ValidationContext $context = null): array
    {
        return [
            'search'           => ['sometimes', 'string', 'max:255'],
            'fulfillment_type' => ['sometimes', 'string', Rule::enum(FulfillmentTypeEnum::class)],
            'categorySlug'     => ['sometimes', 'string', 'exists:categories,slug'],
            'level'            => ['sometimes', 'string', Rule::enum(CourseDifficultyLevelEnum::class)],
        ];
    }

    /**
     * @codeCoverageIgnore
     */

    public function queryParameters(): array
    {
        return [
            'search'           => 'Filter by course name or description',
            'fulfillment_type' => 'Filter by fulfillment type (e.g., online, offline)',
            'categorySlug'     => 'Filter by category slug',
            'level'            => 'Filter by course difficulty level (e.g., beginner, intermediate, advanced)',
        ];
    }
}
