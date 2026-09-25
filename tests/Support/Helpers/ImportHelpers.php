<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;

if (! function_exists('importSpreadsheet')) {
    /**
     * Build an uploaded XLSX file from one row set per worksheet.
     *
     * @param  list<list<array<int, string|int|float|null>>>  $worksheets
     */
    function importSpreadsheet(array $worksheets, string $filename = 'users.xlsx'): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);

        foreach ($worksheets as $index => $rows) {
            $sheet = $spreadsheet->createSheet();
            $sheet->setTitle('Sheet'.($index + 1));
            $sheet->fromArray($rows, null, 'A1');
        }

        $path = tempnam(sys_get_temp_dir(), 'jedu-import').'.xlsx';

        (new XlsxWriter($spreadsheet))->save($path);

        $contents = (string) file_get_contents($path);

        @unlink($path);

        return UploadedFile::fake()->createWithContent($filename, $contents);
    }
}

if (! function_exists('userImportHeadings')) {
    /**
     * English headings accepted by the User import column contract.
     *
     * @return list<string>
     */
    function userImportHeadings(): array
    {
        return [
            'first_name', 'last_name', 'phone', 'email', 'phone2', 'civil_id', 'civil_id_type',
            'date_of_birth', 'father_name', 'gender', 'education_level', 'field_of_study',
            'education_status', 'password', 'provision_moodle', 'provision_ims', 'provision_spotplayer',
        ];
    }
}

if (! function_exists('userImportPersianHeadings')) {
    /**
     * Persian headings accepted by the User import column contract.
     *
     * @return list<string>
     */
    function userImportPersianHeadings(): array
    {
        return [
            'نام', 'نام خانوادگی', 'تلفن همراه', 'پست الکترونیکی', 'تلفن همراه دوم', 'کد شناسایی', 'نوع کد شناسایی',
            'تاریخ تولد (شمسی)', 'نام پدر', 'جنسیت', 'مقطع تحصیلی', 'رشته تحصیلی',
            'وضعیت تحصیلی', 'رمز عبور', 'ساخت حساب مودل', 'ساخت حساب آی‌ام‌اس', 'ساخت حساب اسپات‌پلیر',
        ];
    }
}

if (! function_exists('userImportValues')) {
    /**
     * A valid User import row keyed by column key.
     *
     * @param  array<string, string|int|float|null>  $overrides
     * @return array<string, string|int|float|null>
     */
    function userImportValues(array $overrides = []): array
    {
        return array_merge([
            'first_name'           => 'علی',
            'last_name'            => 'محمدی',
            'phone'                => '09123456789',
            'email'                => 'ali@example.com',
            'phone2'               => '',
            'civil_id'             => '0000000019',
            'civil_id_type'        => 'national_code',
            'date_of_birth'        => '1370-01-01',
            'father_name'          => 'حسن',
            'gender'               => 'male',
            'education_level'      => 'bachelor',
            'field_of_study'       => 'مهندسی کامپیوتر',
            'education_status'     => 'graduated',
            'password'             => '',
            'provision_moodle'     => '',
            'provision_ims'        => '',
            'provision_spotplayer' => '',
        ], $overrides);
    }
}

if (! function_exists('userImportColumnKey')) {
    /**
     * Column key a heading resolves to, or null when the heading is unknown.
     */
    function userImportColumnKey(string $heading): ?string
    {
        /** @var App\Services\ImportExport\HeadingNormalizer $normalizer */
        $normalizer = app(App\Services\ImportExport\HeadingNormalizer::class);

        /** @var App\Contracts\ImportExport\ImportResourceContract $contract */
        $contract = app(App\Services\ImportExport\SpreadsheetResourceRegistry::class)->import('users');

        $normalized = $normalizer->normalize($heading);

        foreach ($contract->columns() as $column) {
            foreach ($column->headings() as $accepted) {
                if ($normalizer->normalize($accepted) === $normalized) {
                    return $column->key;
                }
            }
        }

        return null;
    }
}

if (! function_exists('userImportRow')) {
    /**
     * A User import row as spreadsheet cells, aligned with the requested headings.
     *
     * @param  array<string, string|int|float|null>  $overrides
     * @param  list<string>|null  $headings
     * @return list<string|int|float|null>
     */
    function userImportRow(array $overrides = [], ?array $headings = null): array
    {
        $values = userImportValues($overrides);
        $row    = [];

        foreach ($headings ?? userImportHeadings() as $heading) {
            $key = userImportColumnKey($heading);

            $row[] = $key === null ? '' : ($values[$key] ?? '');
        }

        return $row;
    }
}

if (! function_exists('userImportFile')) {
    /**
     * An uploaded XLSX file with the given User import rows.
     *
     * @param  list<list<string|int|float|null>>  $rows
     * @param  list<string>|null  $headings
     */
    function userImportFile(array $rows, ?array $headings = null): UploadedFile
    {
        return importSpreadsheet([[$headings ?? userImportHeadings(), ...$rows]]);
    }
}

if (! function_exists('postImportPreview')) {
    /**
     * Upload a spreadsheet to the User import preview endpoint.
     *
     * The identity key travels in the query string, exactly as documented.
     */
    function postImportPreview(
        Tests\TestCase $test,
        UploadedFile $file,
        string $identityKey = 'phone',
        string $resource = 'users',
    ): Illuminate\Testing\TestResponse {
        return $test->post(
            route('api.v1.admin.imports.preview', ['resource' => $resource, 'identity_key' => $identityKey]),
            ['file'   => $file],
            ['Accept' => 'application/json'],
        );
    }
}

if (! function_exists('postImportApproval')) {
    /**
     * Approve a previously previewed import run.
     *
     * @param  array<string, mixed>  $body
     */
    function postImportApproval(
        Tests\TestCase $test,
        string $runId,
        array $body = [],
        string $resource = 'users',
    ): Illuminate\Testing\TestResponse {
        return $test->postJson(route('api.v1.admin.imports.approve', [
            'resource' => $resource,
            'run'      => $runId,
        ]), $body);
    }
}

if (! function_exists('importWorksheetRows')) {
    /**
     * Read the first worksheet of an XLSX file into rows.
     *
     * @return list<list<mixed>>
     */
    function importWorksheetRows(string $path): array
    {
        $spreadsheet = PhpOffice\PhpSpreadsheet\IOFactory::load($path);

        return $spreadsheet->getSheet(0)->toArray();
    }
}

if (! function_exists('importWorksheetComments')) {
    /**
     * Cell comments of a worksheet, keyed by coordinate.
     *
     * @return array<string, string>
     */
    function importWorksheetComments(string $path): array
    {
        $comments = [];

        foreach (PhpOffice\PhpSpreadsheet\IOFactory::load($path)->getSheet(0)->getComments() as $coordinate => $comment) {
            $comments[$coordinate] = $comment->getText()->getPlainText();
        }

        return $comments;
    }
}
