<?php

declare(strict_types=1);

namespace App\Data\Admin\ImportExport;

use Spatie\LaravelData\Data;

/** Query parameters for a private spreadsheet export. */
final class ExportRequestData extends Data
{
    /** @param array<string, mixed>|null $filter */
    public function __construct(
        public ?string $locale = null,
        public ?array $filter = null,
        public ?string $sort = null,
    ) {}

    public static function rules(): array
    {
        return [
            'locale'                    => ['nullable', 'string', 'in:fa,en'],
            'filter'                    => ['sometimes', 'array'],
            'filter.name'               => ['sometimes'],
            'filter.email'              => ['sometimes'],
            'filter.phone'              => ['sometimes'],
            'filter.civil_id'           => ['sometimes'],
            'filter.civil_id_type'      => ['sometimes'],
            'filter.wallet_status'      => ['sometimes'],
            'filter.date_of_birth_from' => ['sometimes'],
            'filter.date_of_birth_to'   => ['sometimes'],
            'sort'                      => ['sometimes', 'string'],
        ];
    }

    /**
     * @codeCoverageIgnore
     *
     * @return array<string, array<string, mixed>>
     */
    public function queryParameters(): array
    {
        return [
            'locale' => ['description' => 'Spreadsheet heading and enum label locale; fa or en. Defaults to the application locale.', 'example' => 'fa'],
            'filter' => ['type' => 'object', 'description' => 'Resource-specific filters, using filter[field]=value. Allowed fields and semantics match the selected resource list endpoint.', 'example' => null],
            'sort'   => ['description' => 'Resource-specific comma-separated sort fields. Prefix with - for descending. Allowed fields match the selected resource list endpoint. Pagination is ignored.', 'example' => null],
        ];
    }
}
