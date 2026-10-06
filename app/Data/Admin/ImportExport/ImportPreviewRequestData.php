<?php

declare(strict_types=1);

namespace App\Data\Admin\ImportExport;

use App\Enums\ImportExport\ImportIdentityKeyEnum;
use App\Enums\ImportExport\SpreadsheetResourceEnum;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

/**
 * Multipart upload payload: the spreadsheet itself and an optional identity key, required for users.
 */
final class ImportPreviewRequestData extends Data
{
    public function __construct(
        public UploadedFile $file,
        public ?string $identity_key = null,
    ) {}

    public static function rules(?ValidationContext $context = null): array
    {
        return [
            'file'         => ['required', 'file', 'mimes:xlsx', 'max:5120'],
            'identity_key' => [Rule::requiredIf(request()->route('resource') === SpreadsheetResourceEnum::USERS->value), 'nullable', 'string', Rule::enum(ImportIdentityKeyEnum::class)],
        ];
    }

    public static function attributes(...$args): array
    {
        return [
            'file'         => __('imports.fields.file'),
            'identity_key' => __('imports.fields.identity_key'),
        ];
    }

    /**
     * The identity key travels in the query string and is annotated on the
     * controller, so this DTO documents the multipart body only.
     *
     * @codeCoverageIgnore
     *
     * @return array<string, array<string, mixed>>
     */
    public function bodyParameters(): array
    {
        return [
            'file' => [
                'description' => 'The XLSX file to preview: the first worksheet, one heading row and at most 2000 data rows.',
                'example'     => null,
            ],
        ];
    }
}
