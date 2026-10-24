<?php

declare(strict_types=1);

namespace App\Data\Admin\ImportExport;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

final class ImportApprovalRequestData extends Data
{
    public function __construct(
        public bool|Optional $include_valid_rows,
    ) {}

    public static function rules(): array
    {
        return [
            'include_valid_rows' => ['sometimes', 'boolean'],
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
            'include_valid_rows' => [
                'description' => 'Required only when the preview contains invalid rows. True accepts importing the valid rows and ignoring the invalid ones; omit it when every row is valid.',
                'example'     => true,
            ],
        ];
    }
}
