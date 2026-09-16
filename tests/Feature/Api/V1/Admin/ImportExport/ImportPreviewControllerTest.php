<?php

declare(strict_types=1);

use App\Actions\Admin\ImportExport\CreateImportPreviewAction;
use App\Data\ImportExport\ImportRowResult;
use App\Enums\ImportExport\ImportIdentityKeyEnum;
use App\Enums\ImportExport\ImportRowActionEnum;
use App\Enums\ImportExport\ImportRowStatusEnum;
use App\Enums\ImportExport\ImportRunStatusEnum;
use App\Enums\PermissionEnum;
use App\Enums\User\CivilIdTypeEnum;
use App\Enums\User\GenderEnum;
use App\Models\ImportRun;
use App\Models\ImportRunRow;
use App\Models\User;
use App\Services\ImportExport\HeadingNormalizer;
use App\Services\ImportExport\ImportPreviewEngine;
use App\Services\ImportExport\Resources\UserImportResource;
use App\Services\ImportExport\SpreadsheetImportReader;
use App\Services\ImportExport\SpreadsheetResourceRegistry;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

covers([
    CreateImportPreviewAction::class,
    HeadingNormalizer::class,
    ImportPreviewEngine::class,
    ImportRowResult::class,
    SpreadsheetImportReader::class,
    SpreadsheetResourceRegistry::class,
    UserImportResource::class,
]);

beforeEach(function (): void {
    Storage::fake('local');
});

describe('authorization', function (): void {
    it('rejects guests', function (): void {
        $response = postImportPreview($this, userImportFile([userImportRow()]));

        $response->assertUnauthorized();
    });

    it('rejects staff without the preview permission', function (): void {
        $this->unauthorized_user();

        $response = postImportPreview($this, userImportFile([userImportRow()]));

        $response->assertForbidden();
    });
});

describe('preview envelope', function (): void {
    it('previews a new user without touching stored data or providers', function (): void {
        $this->authorized_user([PermissionEnum::IMPORT_PREVIEW]);
        Http::fake();
        $existingUsers = User::query()->count();

        $response = postImportPreview($this, userImportFile([userImportRow()]));

        $response->assertSuccessful();
        $response->assertJsonPath('data.resource', 'users');
        $response->assertJsonPath('data.identity_key', 'phone');
        $response->assertJsonPath('data.status', ImportRunStatusEnum::PREVIEW_READY->value);
        $response->assertJsonPath('data.summary', [
            'total_rows'                          => 1,
            'valid_rows'                          => 1,
            'invalid_rows'                        => 0,
            'create_count'                        => 1,
            'update_count'                        => 0,
            'provider_provisioning_request_count' => 0,
        ]);
        $response->assertJsonPath('data.can_approve', true);
        $response->assertJsonPath('data.approval_warning', __('imports.approval_warning'));
        $response->assertJsonPath('data.rows.0.row_number', 2);
        $response->assertJsonPath('data.rows.0.status', ImportRowStatusEnum::VALID->value);
        $response->assertJsonPath('data.rows.0.operation', ImportRowActionEnum::CREATE->value);
        $response->assertJsonPath('data.rows.0.errors', []);

        expect($response->json('data.rows.0.data'))->toEqual([
            'phone'                => '09123456789',
            'email'                => 'ali@example.com',
            'first_name'           => 'علی',
            'last_name'            => 'محمدی',
            'phone2'               => null,
            'civil_id'             => '0000000019',
            'civil_id_type'        => 'national_code',
            'date_of_birth'        => '1991-03-21',
            'father_name'          => 'حسن',
            'gender'               => 'male',
            'education_level'      => 'bachelor',
            'field_of_study'       => 'مهندسی کامپیوتر',
            'education_status'     => 'graduated',
            'provision_moodle'     => false,
            'provision_ims'        => false,
            'provision_spotplayer' => false,
        ]);

        expect(User::query()->count())->toBe($existingUsers);
        Http::assertNothingSent();
    });

    it('stores the immutable run with its rows and the private upload', function (): void {
        $this->authorized_user([PermissionEnum::IMPORT_PREVIEW]);

        $response = postImportPreview($this, userImportFile([userImportRow(), userImportRow(['phone' => '09120000000'])]));

        $response->assertSuccessful();

        $runId = $response->json('data.run_id');

        $run = ImportRun::query()->where('uuid', $runId)->sole();

        expect($run->resource->value)->toBe('users')
            ->and($run->identity_key)->toBe(ImportIdentityKeyEnum::PHONE)
            ->and($run->status)->toBe(ImportRunStatusEnum::PREVIEW_READY)
            ->and($run->staff_id)->toBe($this->user->id)
            ->and($run->rows_total)->toBe(2)
            ->and($run->rows_valid)->toBe(2)
            ->and($run->original_filename)->toBe('users.xlsx');

        expect($run->rows()->count())->toBe(2);
        Storage::disk('local')->assertExists($run->file_path);
    });

    it('assigns every row of the file its spreadsheet row number', function (): void {
        $this->authorized_user([PermissionEnum::IMPORT_PREVIEW]);

        $response = postImportPreview($this, userImportFile([
            userImportRow(),
            userImportRow(['phone' => '09120000001']),
            userImportRow(['phone' => '09120000002']),
        ]));

        $response->assertSuccessful();
        $response->assertJsonPath('data.rows.*.row_number', [2, 3, 4]);
    });

    it('reports a run without valid rows as not approvable', function (): void {
        $this->authorized_user([PermissionEnum::IMPORT_PREVIEW]);

        $response = postImportPreview($this, userImportFile([userImportRow(['first_name' => ''])]));

        $response->assertSuccessful();
        $response->assertJsonPath('data.summary.valid_rows', 0);
        $response->assertJsonPath('data.can_approve', false);
    });
});

describe('identity matching', function (): void {
    it('updates an existing user matched by phone and preserves untouched cells', function (): void {
        $this->authorized_user([PermissionEnum::IMPORT_PREVIEW]);
        $user = User::factory()->create(['phone' => '09123456789', 'first_name' => 'قدیمی']);

        $emptyCells = [
            'first_name'      => '', 'last_name' => '', 'email' => '', 'phone2' => '', 'civil_id' => '',
            'civil_id_type'   => '', 'date_of_birth' => '', 'father_name' => '', 'gender' => '',
            'education_level' => '', 'field_of_study' => '', 'education_status' => '',
        ];

        $response = postImportPreview($this, userImportFile([userImportRow($emptyCells)]));

        $response->assertSuccessful();
        $response->assertJsonPath('data.summary.update_count', 1);
        $response->assertJsonPath('data.rows.0.operation', ImportRowActionEnum::UPDATE->value);

        expect($response->json('data.rows.0.data'))->toEqual(['phone' => '09123456789']);

        expect(User::query()->count())->toBe(1);
    });

    it('matches an existing user by email when email is the identity key', function (): void {
        $this->authorized_user([PermissionEnum::IMPORT_PREVIEW]);
        User::factory()->create(['email' => 'ali@example.com']);

        $response = postImportPreview(
            $this,
            userImportFile([userImportRow(['phone' => '', 'first_name' => ''])]),
            ImportIdentityKeyEnum::EMAIL->value,
        );

        $response->assertSuccessful();
        $response->assertJsonPath('data.identity_key', 'email');
        $response->assertJsonPath('data.rows.0.operation', ImportRowActionEnum::UPDATE->value);
    });

    it('does not silently fall back to the other identity key', function (): void {
        $this->authorized_user([PermissionEnum::IMPORT_PREVIEW]);
        User::factory()->create(['phone' => '09123456789']);

        $response = postImportPreview(
            $this,
            userImportFile([userImportRow(['email' => ''])]),
            ImportIdentityKeyEnum::EMAIL->value,
        );

        $response->assertSuccessful();
        $response->assertJsonPath('data.summary.valid_rows', 0);
        $response->assertJsonPath('data.summary.invalid_rows', 1);
        $response->assertJsonPath('data.rows.0.operation', null);

        $errors = collect($response->json('data.rows.0.errors'))->keyBy('field');

        expect($errors->keys()->all())->toContain('email')
            ->and($errors['email']['code'])->toBe('required');

        expect(User::query()->count())->toBe(1);
    });

    it('rejects every row sharing an identity inside the file', function (): void {
        $this->authorized_user([PermissionEnum::IMPORT_PREVIEW]);

        $response = postImportPreview($this, userImportFile([
            userImportRow(['phone' => '09120000001', 'email' => 'dup@example.com']),
            userImportRow(['phone' => '09120000002', 'email' => 'DUP@example.com']),
            userImportRow(['phone' => '09120000003', 'email' => 'other@example.com']),
        ]), ImportIdentityKeyEnum::EMAIL->value);

        $response->assertSuccessful();
        $response->assertJsonPath('data.summary.valid_rows', 1);
        $response->assertJsonPath('data.summary.invalid_rows', 2);
        $response->assertJsonPath('data.rows.0.status', ImportRowStatusEnum::INVALID->value);
        $response->assertJsonPath('data.rows.0.errors.0.field', 'email');
        $response->assertJsonPath('data.rows.0.errors.0.code', 'duplicate_identity');
        $response->assertJsonPath('data.rows.1.errors.0.code', 'duplicate_identity');
        $response->assertJsonPath('data.rows.2.status', ImportRowStatusEnum::VALID->value);
    });

    it('keeps field errors of a row whose identity is duplicated', function (): void {
        $this->authorized_user([PermissionEnum::IMPORT_PREVIEW]);

        $response = postImportPreview($this, userImportFile([
            userImportRow(['phone' => '09120000001', 'email' => 'dup@example.com']),
            userImportRow(['phone' => '09120000002', 'email' => 'DUP@example.com', 'first_name' => '']),
        ]), ImportIdentityKeyEnum::EMAIL->value);

        $response->assertSuccessful();
        $response->assertJsonPath('data.summary.valid_rows', 0);
        $response->assertJsonPath('data.rows.1.errors.*.field', ['email', 'first_name']);
        $response->assertJsonPath('data.rows.1.errors.*.code', ['duplicate_identity', 'required']);
    });

    it('rejects an identity conflicting with another existing user', function (): void {
        $this->authorized_user([PermissionEnum::IMPORT_PREVIEW]);
        User::factory()->create(['email' => 'taken@example.com']);

        $response = postImportPreview($this, userImportFile([userImportRow(['email' => 'taken@example.com'])]));

        $response->assertSuccessful();
        $response->assertJsonPath('data.summary.valid_rows', 0);
        $response->assertJsonPath('data.rows.0.errors.0.field', 'email');
        $response->assertJsonPath('data.rows.0.errors.0.code', 'identity_conflict');
    });
});

describe('headings', function (): void {
    it('accepts Persian headings with Arabic variants, zero width and punctuation noise', function (): void {
        $this->authorized_user([PermissionEnum::IMPORT_PREVIEW]);

        $headings    = userImportPersianHeadings();
        $headings[0] = "  نام\u{200C}كوچك \u{200B}*  ";   // Arabic kaf/yeh, ZWNJ, zero width, punctuation, padding
        $headings[3] = 'پست الكترونیكی';                   // Arabic kaf/yeh inside a heading

        $response = postImportPreview($this, userImportFile([userImportRow([], $headings)], $headings));

        $response->assertSuccessful();
        $response->assertJsonPath('data.summary.valid_rows', 1);
    });

    it('accepts the headings of the generated template', function (): void {
        $this->authorized_user([PermissionEnum::IMPORT_PREVIEW, PermissionEnum::IMPORT_TEMPLATE]);

        $template = $this->get(route('api.v1.admin.imports.template', ['resource' => 'users']));
        $template->assertSuccessful();

        $rows = importWorksheetRows($template->getFile()->getPathname());

        $response = postImportPreview($this, importSpreadsheet([$rows]));

        $response->assertSuccessful();
        $response->assertJsonPath('data.summary.total_rows', 1);
    });

    it('normalizes Persian digits inside cells', function (): void {
        $this->authorized_user([PermissionEnum::IMPORT_PREVIEW]);

        $response = postImportPreview($this, userImportFile([
            userImportRow(['phone' => '۰۹۱۲۳۴۵۶۷۸۹', 'civil_id' => '۰۰۰۰۰۰۰۰۱۹', 'date_of_birth' => '۱۳۷۰-۰۱-۰۱']),
        ]));

        $response->assertSuccessful();
        $response->assertJsonPath('data.rows.0.data.phone', '09123456789');
        $response->assertJsonPath('data.rows.0.data.date_of_birth', '1991-03-21');
    });

    it('rejects missing required headings', function (): void {
        $this->authorized_user([PermissionEnum::IMPORT_PREVIEW]);

        $headings = array_values(array_diff(userImportHeadings(), ['civil_id']));

        $response = postImportPreview($this, userImportFile([userImportRow([], $headings)], $headings));

        $response->assertUnprocessable();
        $response->assertJsonPath('errors.file.0', __('imports.errors.missing_columns', [
            'columns' => __('imports.columns.civil_id'),
        ]));
    });

    it('rejects unknown headings', function (): void {
        $this->authorized_user([PermissionEnum::IMPORT_PREVIEW]);

        $headings   = userImportHeadings();
        $headings[] = 'favourite_colour';

        $response = postImportPreview($this, userImportFile([userImportRow([], $headings)], $headings));

        $response->assertUnprocessable();
        $response->assertJsonPath('errors.file.0', __('imports.errors.unknown_columns', ['columns' => 'favourite_colour']));
    });
});

describe('file shape', function (): void {
    it('reads the first worksheet and ignores the rest', function (): void {
        $this->authorized_user([PermissionEnum::IMPORT_PREVIEW]);

        $file = importSpreadsheet([
            [userImportHeadings(), userImportRow(), userImportRow(['phone' => '09120000000'])],
            [['unrelated'], ['second sheet']],
        ]);

        $response = postImportPreview($this, $file);

        $response->assertSuccessful();
        $response->assertJsonPath('data.summary.total_rows', 2);
    });

    it('ignores trailing empty rows', function (): void {
        $this->authorized_user([PermissionEnum::IMPORT_PREVIEW]);

        $blank = array_fill(0, count(userImportHeadings()), '');

        $response = postImportPreview($this, userImportFile([userImportRow(), $blank, $blank]));

        $response->assertSuccessful();
        $response->assertJsonPath('data.summary.total_rows', 1);
    });

    it('rejects a worksheet without data rows', function (): void {
        $this->authorized_user([PermissionEnum::IMPORT_PREVIEW]);

        $response = postImportPreview($this, userImportFile([]));

        $response->assertUnprocessable();
        $response->assertJsonPath('errors.file.0', __('imports.errors.empty'));
    });

    it('rejects more than two thousand data rows', function (): void {
        $this->authorized_user([PermissionEnum::IMPORT_PREVIEW]);

        $rows = [];

        for ($index = 0; $index < 2001; $index++) {
            $rows[] = userImportRow(['phone' => '0912'.mb_str_pad((string) $index, 7, '0', STR_PAD_LEFT)]);
        }

        $response = postImportPreview($this, userImportFile($rows));

        $response->assertUnprocessable();
        $response->assertJsonPath('errors.file.0', __('imports.errors.too_many_rows', ['max' => 2000]));
    });

    it('accepts a file with exactly two thousand data rows', function (): void {
        $this->authorized_user([PermissionEnum::IMPORT_PREVIEW]);

        $rows = [];

        for ($index = 0; $index < 2000; $index++) {
            $rows[] = userImportRow(['phone' => '0912'.mb_str_pad((string) $index, 7, '0', STR_PAD_LEFT)]);
        }

        $response = postImportPreview($this, userImportFile($rows));

        $response->assertSuccessful();
        $response->assertJsonPath('data.summary.total_rows', 2000);
        $response->assertJsonPath('data.summary.valid_rows', 2000);
    });

    it('rejects a file that is not an xlsx workbook', function (): void {
        $this->authorized_user([PermissionEnum::IMPORT_PREVIEW]);

        $response = $this->post(
            route('api.v1.admin.imports.preview', ['resource' => 'users', 'identity_key' => 'phone']),
            ['file'   => UploadedFile::fake()->createWithContent('users.csv', "first_name\nAli\n")],
            ['Accept' => 'application/json'],
        );

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('file');
    });

    it('rejects an unsupported identity key', function (): void {
        $this->authorized_user([PermissionEnum::IMPORT_PREVIEW]);

        $response = postImportPreview($this, userImportFile([userImportRow()]), 'username');

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('identity_key');
    });

    it('requires the identity key', function (): void {
        $this->authorized_user([PermissionEnum::IMPORT_PREVIEW]);

        $response = $this->post(
            route('api.v1.admin.imports.preview', ['resource' => 'users']),
            ['file'   => userImportFile([userImportRow()])],
            ['Accept' => 'application/json'],
        );

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('identity_key');
    });

    it('rejects an unregistered resource', function (): void {
        $this->authorized_user([PermissionEnum::IMPORT_PREVIEW]);

        $response = $this->post(
            route('api.v1.admin.imports.preview', ['resource' => 'unknown_resource', 'identity_key' => 'phone']),
            ['file'   => userImportFile([userImportRow()])],
            ['Accept' => 'application/json'],
        );

        $response->assertNotFound();
    });
});

describe('row validation', function (): void {
    it('reports invalid cells of a row and keeps the normalized data', function (): void {
        $this->authorized_user([PermissionEnum::IMPORT_PREVIEW]);

        $response = postImportPreview($this, userImportFile([
            userImportRow(['first_name' => '', 'email' => 'not-an-email', 'civil_id_type' => 'unknown']),
        ]));

        $response->assertSuccessful();
        $response->assertJsonPath('data.summary.invalid_rows', 1);
        $response->assertJsonPath('data.rows.0.status', ImportRowStatusEnum::INVALID->value);
        $response->assertJsonPath('data.rows.0.operation', null);
        $response->assertJsonPath('data.rows.0.errors.*.field', ['email', 'first_name', 'civil_id_type']);
        $response->assertJsonPath('data.rows.0.errors.*.code', ['invalid', 'required', 'invalid']);
        $response->assertJsonPath('data.rows.0.data.email', 'not-an-email');
    });

    it('rejects malformed provision flags', function (): void {
        $this->authorized_user([PermissionEnum::IMPORT_PREVIEW]);

        $response = postImportPreview($this, userImportFile([userImportRow(['provision_moodle' => 'maybe'])]));

        $response->assertSuccessful();
        $response->assertJsonPath('data.rows.0.status', ImportRowStatusEnum::INVALID->value);
        $response->assertJsonPath('data.rows.0.errors.0.field', 'provision_moodle');
        $response->assertJsonPath('data.rows.0.errors.0.code', 'invalid');
    });

    it('accepts translated enum labels', function (): void {
        $this->authorized_user([PermissionEnum::IMPORT_PREVIEW]);

        $response = postImportPreview($this, userImportFile([
            userImportRow([
                'gender'        => GenderEnum::MALE->translate(),
                'civil_id_type' => CivilIdTypeEnum::NATIONAL_CODE->translate(),
            ]),
        ]));

        $response->assertSuccessful();
        $response->assertJsonPath('data.rows.0.data.gender', 'male');
        $response->assertJsonPath('data.rows.0.data.civil_id_type', 'national_code');
    });

    it('rejects an impossible Jalali date', function (): void {
        $this->authorized_user([PermissionEnum::IMPORT_PREVIEW]);

        $response = postImportPreview($this, userImportFile([userImportRow(['date_of_birth' => '1370-13-45'])]));

        $response->assertSuccessful();
        $response->assertJsonPath('data.rows.0.status', ImportRowStatusEnum::INVALID->value);
        $response->assertJsonPath('data.rows.0.errors.0.field', 'date_of_birth');
    });

    it('validates an imported password and never returns it', function (): void {
        $this->authorized_user([PermissionEnum::IMPORT_PREVIEW]);

        $response = postImportPreview($this, userImportFile([
            userImportRow(['password' => 'short']),
            userImportRow(['phone' => '09120000000', 'password' => 'a-secure-password']),
        ]));

        $response->assertSuccessful();
        $response->assertJsonPath('data.rows.0.status', ImportRowStatusEnum::INVALID->value);
        $response->assertJsonPath('data.rows.0.errors.0.field', 'password');
        $response->assertJsonPath('data.rows.1.status', ImportRowStatusEnum::VALID->value);
        $response->assertJsonMissing(['password' => 'a-secure-password']);

        $run    = ImportRun::query()->where('uuid', $response->json('data.run_id'))->sole();
        $stored = ImportRunRow::query()->where('import_run_id', $run->id)->pluck('data')->all();

        expect(json_encode($stored))->not->toContain('a-secure-password');
    });

    it('accepts additive provider flags and counts the requests', function (): void {
        $this->authorized_user([PermissionEnum::IMPORT_PREVIEW]);

        $response = postImportPreview($this, userImportFile([
            userImportRow(['provision_moodle' => 'true', 'provision_ims' => 'بله', 'provision_spotplayer' => 'yes']),
            userImportRow(['phone' => '09120000000', 'provision_moodle' => 'false', 'provision_ims' => '0']),
        ]));

        $response->assertSuccessful();
        $response->assertJsonPath('data.rows.0.data.provision_moodle', true);
        $response->assertJsonPath('data.rows.0.data.provision_ims', true);
        $response->assertJsonPath('data.rows.0.data.provision_spotplayer', true);
        $response->assertJsonPath('data.rows.1.data.provision_moodle', false);
        $response->assertJsonPath('data.summary.provider_provisioning_request_count', 3);
    });
});
