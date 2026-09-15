<?php

declare(strict_types=1);

namespace App\Data\Shop\Student\Review;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class SubmitReviewData extends Data
{
    public function __construct(
        public int $rating,
        public string $title,
        public string $comment,
    ) {}

    /**
     * @return array<string, array<int, string>>
     */
    public static function rules(?ValidationContext $context = null): array
    {
        return [
            'rating'  => ['required', 'integer', 'between:1,5'],
            'title'   => ['required', 'string', 'max:255'],
            'comment' => ['required', 'string', 'max:2000'],
        ];
    }
}
