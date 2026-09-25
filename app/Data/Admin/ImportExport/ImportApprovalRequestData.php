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
}
