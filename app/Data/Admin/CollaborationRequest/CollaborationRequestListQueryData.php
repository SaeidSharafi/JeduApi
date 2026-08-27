<?php

declare(strict_types=1);

namespace App\Data\Admin\CollaborationRequest;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

/** Query parameters for the Collaboration Request list endpoint. */
final class CollaborationRequestListQueryData extends Data
{
    public function __construct(public ?array $filter = null, public ?string $sort = null, public ?int $per_page = null, public ?int $page = null) {}

    public static function rules(?ValidationContext $context = null): array
    {
        return ['filter' => ['sometimes', 'array'], 'sort' => ['sometimes', 'string'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'], 'page' => ['sometimes', 'integer', 'min:1']];
    }

    /** @return array<string, array<string, mixed>> */
    public function queryParameters(): array
    {
        return [
            'filter[status]'         => ['description' => 'Filter by status.', 'example' => 'pending'],
            'filter[assigned_to_id]' => ['description' => 'Filter by assigned staff ID.', 'example' => 1],
            'filter[department]'     => ['description' => 'Filter by department.', 'example' => 'partnerships'],
            'filter[search]'         => ['description' => 'Search name, phone, email, or department.', 'example' => 'john'],
            'sort'                   => ['description' => 'Sort by status, created_at, or assigned_to_id.', 'example' => '-created_at'],
            'per_page'               => ['description' => 'Number of items per page.', 'example' => 15],
            'page'                   => ['description' => 'Page number.', 'example' => 1],
        ];
    }
}
