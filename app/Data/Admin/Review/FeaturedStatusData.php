<?php

declare(strict_types=1);

namespace App\Data\Admin\Review;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class FeaturedStatusData extends Data
{
    public function __construct(
        public ?bool $is_featured = null,
    ) {}

    public static function rules(?ValidationContext $context = null): array
    {
        return [
            'is_featured' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @codeCoverageIgnore
     *
     * @return array<string, array<string, mixed>>
     */
    public function bodyParameters(): array
    {
        return [
            'is_featured' => [
                'description' => 'Whether the review is featured.',
                'example'     => true,
            ],
        ];
    }
}
