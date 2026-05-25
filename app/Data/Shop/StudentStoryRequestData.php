<?php

declare(strict_types=1);

namespace App\Data\Shop;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class StudentStoryRequestData extends Data
{
    public function __construct(
        public ?string $course_slug = null,
        public ?string $category_slug = null,
        public ?bool $featured_only = false,
        public ?int $limit = null,
    ) {}

    public static function rules(?ValidationContext $context = null): array
    {
        return [
            'course_slug'   => ['nullable', 'string'],
            'category_slug' => ['nullable', 'string'],
            'featured_only' => ['nullable', 'boolean'],
            'limit'         => ['nullable', 'integer', 'min:1'],
        ];
    }
}
