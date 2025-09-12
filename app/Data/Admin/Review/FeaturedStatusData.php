<?php

namespace App\Data\Admin\Review;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

class FeaturedStatusData extends Data
{
    public function __construct(
        public ?bool $is_featured = null,
    ) {
    }

    public static function rules(?ValidationContext $context = null): array
    {
        return [
            'is_featured' => ['sometimes', 'boolean']
        ];
    }
}
