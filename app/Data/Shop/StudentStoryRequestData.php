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

    /**
     * @codeCoverageIgnore
     *
     * @return array<string, mixed>
     */
    public function queryParameters(): array
    {
        return [
            'course_slug' => [
                'description' => 'Filter stories by the slug of a specific course.',
                'example'     => 'python-fundamentals',
            ],
            'category_slug' => [
                'description' => 'Filter stories by the slug of a specific category.',
                'example'     => 'programming',
            ],
            'featured_only' => [
                'description' => 'When true, returns only featured student stories.',
                'example'     => true,
            ],
            'limit' => [
                'description' => 'Maximum number of student stories to return.',
                'example'     => 6,
            ],
        ];
    }
}
