<?php

declare(strict_types=1);

namespace App\Services\ImportExport;

use App\Contracts\ImportExport\ImportResourceContract;
use App\Data\ImportExport\ImportRowResult;
use App\Data\ImportExport\SpreadsheetColumnDefinition;
use App\Enums\ImportExport\ImportIdentityKeyEnum;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Turns an uploaded worksheet into validated, resource owned row results.
 *
 * The engine owns spreadsheet mechanics only: heading resolution, row limits,
 * duplicate identities and the preview envelope keys. Field rules and provider
 * flags stay inside the resource contract.
 */
final readonly class ImportPreviewEngine
{
    private const string FILE_ATTRIBUTE = 'file';

    public function __construct(
        private SpreadsheetImportReader $reader,
        private HeadingNormalizer $headingNormalizer,
    ) {}

    /**
     * Preview results keyed by their spreadsheet row number.
     *
     * @return array<int, ImportRowResult>
     *
     * @throws ValidationException when the uploaded file cannot be previewed
     */
    public function preview(
        ImportResourceContract $resource,
        ImportIdentityKeyEnum $identityKey,
        UploadedFile $file,
    ): array {
        $rows = $this->reader->read($file);

        if (count($rows) < 2) {
            throw ValidationException::withMessages([
                self::FILE_ATTRIBUTE => __('imports.errors.empty'),
            ]);
        }

        $columns = $this->resolveColumns($resource, $identityKey, array_values($rows[0]));

        $results = $this->validateRows($resource, $identityKey, $rows, $columns);

        $this->markDuplicateIdentities($results, $identityKey);

        return $results;
    }

    /**
     * Matches the heading row against the resource column contract.
     *
     * @param  array<int, mixed>  $headingCells
     * @return array<string, int> Column key to cell position.
     */
    private function resolveColumns(
        ImportResourceContract $resource,
        ImportIdentityKeyEnum $identityKey,
        array $headingCells,
    ): array {
        $columns = $resource->columns();
        $index   = $this->headingIndex($columns);

        $resolved = [];
        $unknown  = [];

        foreach ($headingCells as $position => $cell) {
            $heading = is_scalar($cell) ? mb_trim((string) $cell) : '';

            if ($heading === '') {
                continue;
            }

            $column = $index[$this->headingNormalizer->normalize($heading)] ?? null;

            if (! $column instanceof SpreadsheetColumnDefinition) {
                $unknown[] = $heading;

                continue;
            }

            // A heading repeated in the file keeps its first occurrence.
            $resolved[$column->key] ??= $position;
        }

        $missing = [];

        foreach ($columns as $column) {
            if (($column->required || $column->key === $identityKey->value) && ! isset($resolved[$column->key])) {
                $missing[] = $column->heading();
            }
        }

        $messages = [];

        if ($missing !== []) {
            $messages[] = __('imports.errors.missing_columns', ['columns' => implode(', ', $missing)]);
        }

        if ($unknown !== []) {
            $messages[] = __('imports.errors.unknown_columns', ['columns' => implode(', ', $unknown)]);
        }

        if ($messages !== []) {
            throw ValidationException::withMessages([self::FILE_ATTRIBUTE => $messages]);
        }

        return $resolved;
    }

    /**
     * Every accepted heading spelling mapped to its column.
     *
     * @param  list<SpreadsheetColumnDefinition>  $columns
     * @return array<string, SpreadsheetColumnDefinition>
     */
    private function headingIndex(array $columns): array
    {
        $index = [];

        foreach ($columns as $column) {
            foreach ($column->headings() as $heading) {
                $index[$this->headingNormalizer->normalize($heading)] = $column;
            }
        }

        return $index;
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     * @param  array<string, int>  $columns
     * @return array<int, ImportRowResult>
     */
    private function validateRows(
        ImportResourceContract $resource,
        ImportIdentityKeyEnum $identityKey,
        array $rows,
        array $columns,
    ): array {
        $results  = [];
        $dataRows = 0;

        foreach ($rows as $index => $cells) {
            if ($index === 0) {
                continue;
            }

            $cells  = array_values($cells);
            $values = [];

            foreach ($columns as $key => $position) {
                $values[$key] = $cells[$position] ?? null;
            }

            if (! $this->rowHasContent($values)) {
                continue;
            }

            if (++$dataRows > SpreadsheetImportReader::MAX_DATA_ROWS) {
                throw ValidationException::withMessages([
                    self::FILE_ATTRIBUTE => __('imports.errors.too_many_rows', [
                        'max' => SpreadsheetImportReader::MAX_DATA_ROWS,
                    ]),
                ]);
            }

            $results[$index + 1] = $resource->validateRow($values, $identityKey);
        }

        return $results;
    }

    /**
     * @param  iterable<mixed>  $cells
     */
    private function rowHasContent(iterable $cells): bool
    {
        foreach ($cells as $cell) {
            if (is_scalar($cell) && mb_trim((string) $cell) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * Rows sharing an identity reject each other: spreadsheet order must never
     * decide which one wins.
     *
     * @param  array<int, ImportRowResult>  $results
     */
    private function markDuplicateIdentities(array &$results, ImportIdentityKeyEnum $identityKey): void
    {
        $identities = [];

        foreach ($results as $result) {
            if ($result->identity !== null) {
                $identities[$result->identity] = ($identities[$result->identity] ?? 0) + 1;
            }
        }

        foreach ($results as $rowNumber => $result) {
            if ($result->identity === null || ($identities[$result->identity] ?? 0) < 2) {
                continue;
            }

            $results[$rowNumber] = $result->withDuplicateIdentityError(
                $identityKey->value,
                (string) __('imports.errors.duplicate_identity', ['identity' => $result->identity]),
            );
        }
    }
}
