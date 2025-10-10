<?php

declare(strict_types=1);

namespace App\Data\Shop\Product\Course;

use App\Enums\CourseDifficultyLevelEnum;
use App\Enums\Product\FulfillmentTypeEnum;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class CourseFilterData extends Data
{
    public function __construct(
        public ?string $fulfillment_type = null,
        public ?string $categorySlug = null,
        public ?string $difficulty_level = null,
        public ?int $min_price = null,
        public ?int $max_price = null,
        public ?bool $with_discounts = null,
    ) {}

    public static function rules(?ValidationContext $context = null, string $prefix = ''): array
    {
        return [
            $prefix.'fulfillment_type' => ['sometimes', 'string', Rule::enum(FulfillmentTypeEnum::class)],
            $prefix.'categorySlug'     => ['sometimes', 'string', 'exists:categories,slug'],
            $prefix.'difficulty_level' => ['sometimes', 'string', Rule::enum(CourseDifficultyLevelEnum::class)],
            $prefix.'min_price'        => ['sometimes', 'integer', 'min:0'],
            $prefix.'max_price'        => ['sometimes', 'integer', "gte:{$prefix}min_price"],
            $prefix.'with_discounts'   => ['sometimes', 'boolean'],
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
            'difficulty_level' => 'Filter by course difficulty level (e.g., beginner, intermediate, advanced)',
            'min_price'        => 'Only include courses with a minimum price greater than or equal to this amount.',
            'max_price'        => 'Only include courses with a minimum price less than or equal to this amount.',
            'with_discounts'   => 'When true, only include courses that currently have an active discount.',
        ];
    }
}
