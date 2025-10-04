<?php

declare(strict_types=1);

namespace App\Data\Shop\Search;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class SearchSuggestRequestData extends Data
{
    public function __construct(
        public string $q,
        public ?int $limit = 5,
    ) {}

    public static function rules(?ValidationContext $context = null): array
    {
        return [
            'q'     => ['required', 'string', 'min:2', 'max:255'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:20'],
        ];
    }

    /**
     * @codeCoverageIgnore
     */
    public function queryParameters(): array
    {
        return [
            'q' => [
                'description' => 'The search query for suggestions',
                'example'     => 'lap',
            ],
            'limit' => [
                'description' => 'Maximum number of suggestions to return (1-20)',
                'example'     => 5,
            ],
        ];
    }
}
