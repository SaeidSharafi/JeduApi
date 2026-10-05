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
            'locale'                    => ['description' => 'Enum label locale; fa or en. Defaults to the application locale.', 'example' => 'fa'],
            'filter'                    => ['description' => 'The same allowed filters as the User list.', 'example' => ['name' => 'علی']],
            'filter.name'               => ['type' => 'string', 'description' => 'Partial combined first and last name.', 'example' => 'علی'],
            'filter.email'              => ['type' => 'string', 'description' => 'Partial email address.', 'example' => 'user@example.com'],
            'filter.phone'              => ['type' => 'string', 'description' => 'Partial mobile phone.', 'example' => '0912'],
            'filter.civil_id'           => ['type' => 'string', 'description' => 'Partial civil ID.', 'example' => '0000000019'],
            'filter.civil_id_type'      => ['type' => 'string', 'description' => 'Exact civil ID type.', 'example' => 'national_code'],
            'filter.wallet_status'      => ['type' => 'string', 'description' => 'Exact wallet status.', 'example' => 'active'],
            'filter.date_of_birth_from' => ['type' => 'string', 'description' => 'Inclusive Jalali birth date lower bound.', 'example' => '1370-01-01'],
            'filter.date_of_birth_to'   => ['type' => 'string', 'description' => 'Inclusive Jalali birth date upper bound.', 'example' => '1380-01-01'],
            'sort'                      => ['description' => 'Comma-separated first_name, last_name, email, phone, civil_id, civil_id_type or date_of_birth. Prefix with - for descending; ID resolves ties. Pagination is ignored.', 'example' => '-last_name,first_name'],
        ];
    }
}
