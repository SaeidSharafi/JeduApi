<?php

declare(strict_types=1);

namespace App\Data\Shop;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

/** Query parameters for bounded pagination. */
final class PaginationRequestData extends Data
{
    public function __construct(
        public ?int $page = null,
        public ?int $per_page = 15,
    ) {}

    public static function rules(?ValidationContext $context = null): array
    {
        return [
            'page'     => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    /** @return array<string, array<string, string>> */
    public function queryParameters(): array
    {
        return [
            'page'     => ['description' => 'Page number', 'example' => '1'],
            'per_page' => ['description' => 'Items per page (1-100)', 'example' => '15'],
        ];
    }
}
