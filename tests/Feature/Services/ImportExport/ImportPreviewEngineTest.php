<?php

declare(strict_types=1);

use App\Contracts\ImportExport\ImportResourceContract;
use App\Data\ImportExport\ImportRowCommitResult;
use App\Data\ImportExport\ImportRowResult;
use App\Data\ImportExport\SpreadsheetColumnDefinition;
use App\Enums\ImportExport\ImportIdentityKeyEnum;
use App\Enums\ImportExport\SpreadsheetResourceEnum;
use App\Services\ImportExport\ImportPreviewEngine;
use Illuminate\Validation\ValidationException;

mutates(ImportPreviewEngine::class);

it('previews rows without an identity column or duplicate matching when no key is selected', function (): void {
    $resource = new class implements ImportResourceContract
    {
        public function resource(): SpreadsheetResourceEnum
        {
            return SpreadsheetResourceEnum::USERS;
        }

        public function columns(): array
        {
            return [
                new SpreadsheetColumnDefinition('name', 'name', [], true, null, 'name'),
                new SpreadsheetColumnDefinition('phone', 'phone', [], false, null, 'phone'),
            ];
        }

        public function exampleRow(): array
        {
            return ['name' => 'Example'];
        }

        public function providerCapabilities(): array
        {
            return [];
        }

        public function validateRow(array $values, ?ImportIdentityKeyEnum $identityKey): ImportRowResult
        {
            expect($identityKey)->toBeNull();

            return ImportRowResult::create($values);
        }

        public function importRow(ImportRowResult $row): ImportRowCommitResult
        {
            throw new LogicException('Preview must not commit rows.');
        }

        public function errorReportFieldLabel(string $field): ?string
        {
            return $field;
        }

        public function errorReportCode(string $code): string
        {
            return $code;
        }

        public function errorReportMessage(string $code): string
        {
            return $code;
        }
    };
    $file = importSpreadsheet([[['name'], ['Same name'], ['Same name']]]);

    $rows = app(ImportPreviewEngine::class)->preview($resource, null, $file);

    expect($rows)->toHaveCount(2);
    foreach ($rows as $row) {
        expect($row->valid)->toBeTrue()
            ->and($row->identity)->toBeNull()
            ->and($row->data)->toBe(['name' => 'Same name']);
    }
});

it('still requires an identity when the user resource is called directly', function (): void {
    expect(fn () => app(App\Services\ImportExport\Resources\UserImportResource::class)->validateRow([], null))
        ->toThrow(ValidationException::class);
});

it('rejects every duplicate row when an identity key is selected', function (): void {
    $file = userImportFile([userImportRow(), userImportRow()]);

    $rows = app(ImportPreviewEngine::class)->preview(
        app(App\Services\ImportExport\Resources\UserImportResource::class),
        ImportIdentityKeyEnum::PHONE,
        $file,
    );

    expect(array_keys($rows))->toBe([2, 3]);
    foreach ($rows as $row) {
        expect($row->valid)->toBeFalse()
            ->and($row->errors[0]['field'])->toBe('phone')
            ->and($row->errors[0]['code'])->toBe('duplicate_identity');
    }
});

it('requires resource headings and the selected identity heading', function (string $missingColumn, ImportIdentityKeyEnum $identityKey): void {
    $headings = array_values(array_filter(userImportHeadings(), fn (string $heading): bool => $heading !== $missingColumn));
    $file     = importSpreadsheet([[$headings, array_fill(0, count($headings), 'value')]]);

    expect(fn () => app(ImportPreviewEngine::class)->preview(
        app(App\Services\ImportExport\Resources\UserImportResource::class),
        $identityKey,
        $file,
    ))->toThrow(ValidationException::class);
})->with([
    'resource-required heading'          => ['first_name', ImportIdentityKeyEnum::PHONE],
    'selected optional identity heading' => ['email', ImportIdentityKeyEnum::EMAIL],
]);
