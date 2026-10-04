<?php

declare(strict_types=1);

namespace App\Data\Admin\OrganizationTrainingRequest;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class OrganizationTrainingRequestListQueryData extends Data
{
    public function __construct(
        public ?array $filter = null,
        public ?string $sort = null,
        public ?int $per_page = null,
        public ?int $page = null,
    ) {}

    public static function rules(?ValidationContext $context = null): array
    {
        return [
            'filter'   => ['sometimes', 'array'],
            'sort'     => ['sometimes', 'string'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page'     => ['sometimes', 'integer', 'min:1'],
        ];
    }

    /** @return array<string, array<string, mixed>> */
    public function queryParameters(): array
    {
        return [
            'filter[status]'            => ['description' => 'Filter by request status.', 'example' => 'pending'],
            'filter[assigned_to_id]'    => ['description' => 'Filter by assigned staff ID.', 'example' => 1],
            'filter[organization_name]' => ['description' => 'Filter by organization name.', 'example' => 'Example'],
            'filter[search]'            => ['description' => 'Search representative, phone, organization, or position.', 'example' => 'Sara'],
            'sort'                      => ['description' => 'Sort by status, created_at, or assigned_to_id.', 'example' => '-created_at'],
            'per_page'                  => ['description' => 'Number of items per page.', 'example' => 15],
            'page'                      => ['description' => 'Page number.', 'example' => 1],
        ];
    }
}
