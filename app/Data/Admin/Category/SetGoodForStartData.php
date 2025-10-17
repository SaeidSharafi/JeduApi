<?php

declare(strict_types=1);

namespace App\Data\Admin\Category;

use App\Enums\System\MorphTypeEnum;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class SetGoodForStartData extends Data
{
    public function __construct(
        public array $course_ids,
        public bool $good_for_start,
    ) {}

    public static function rules(?ValidationContext $context = null): array
    {
        $category = request()?->route()?->parameter('category');

        return [
            'course_ids' => ['required', 'array', 'min:1'],
            // This rule is now much more powerful
            'course_ids.*' => [
                'required',
                'integer',
                // Rule 1: Ensure each ID exists in the 'courses' table.
                'exists:courses,id',
                // Rule 2: Ensure each course is actually attached to THIS category.
                Rule::exists('categorizables', 'categorizable_id')->where(function ($query) use (
                    $category
                ): void {
                    $query->where('category_id', $category?->id)
                        ->where('categorizable_type', MorphTypeEnum::COURSE);
                }),
            ],
            'good_for_start' => ['required', 'boolean'],
        ];
    }

    /**
     * @codeCoverageIgnore
     */
    public function bodyParameters(): array
    {
        return [
            'course_ids' => [
                'description' => 'Array of course IDs to set as good for start.',
                'example'     => [1, 2, 3],
            ],
            'course_ids.*' => [
                'description' => 'A course ID.',
                'example'     => 1,
            ],
            'good_for_start' => [
                'description' => 'Whether the selected courses are good for start.',
                'example'     => true,
            ],
        ];
    }
}
