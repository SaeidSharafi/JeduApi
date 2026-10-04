<?php

declare(strict_types=1);

namespace App\Data\Shop\Organization;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

/** Query parameters for the public Organization page. */
final class OrganizationPageRequestData extends Data
{
    public function __construct(public ?int $limit = 6) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(?ValidationContext $context = null): array
    {
        return [
            'limit' => ['sometimes', 'integer', 'min:1', 'max:20'],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function queryParameters(): array
    {
        return [
            'limit' => [
                'description' => 'Number of recent published and visible Course products to return (1-20).',
                'example'     => 6,
            ],
        ];
    }
}
