<?php

declare(strict_types=1);

use App\Enums\PermissionEnum;
use App\Services\ImportExport\Resources\UserImportResource;

covers(UserImportResource::class);

it('accepts translated and raw enum values independently of the application locale', function (string $locale, array $values): void {
    app()->setLocale($locale);
    $this->authorized_user([PermissionEnum::IMPORT_PREVIEW]);

    $response = postImportPreview($this, userImportFile([userImportRow($values)]));

    $response->assertSuccessful()->assertJsonPath('data.rows.0.status', 'valid');
    $response->assertJsonPath('data.rows.0.data.civil_id_type', 'national_code');
    $response->assertJsonPath('data.rows.0.data.gender', 'male');
    $response->assertJsonPath('data.rows.0.data.education_level', 'bachelor');
    $response->assertJsonPath('data.rows.0.data.education_status', 'graduated');
})->with(['fa', 'en'])->with([
    'Persian labels'                => [['civil_id_type' => 'کد ملی', 'gender' => 'مرد', 'education_level' => 'کارشناسی', 'education_status' => 'فارغ‌التحصیل']],
    'English labels'                => [['civil_id_type' => 'National Code', 'gender' => 'Male', 'education_level' => 'Bachelor', 'education_status' => 'Graduated']],
    'raw keys'                      => [['civil_id_type' => 'national_code', 'gender' => 'male', 'education_level' => 'bachelor', 'education_status' => 'graduated']],
    'Arabic letters and whitespace' => [['civil_id_type' => ' كد ملي ', 'gender' => ' مَرد ', 'education_level' => 'كارشناسي', 'education_status' => 'فارغ التحصيل']],
]);

it('downloads translated enum examples in the Persian template', function (): void {
    app()->setLocale('fa');
    $this->authorized_user([PermissionEnum::IMPORT_TEMPLATE]);

    $response = $this->get(route('api.v1.admin.imports.template', ['resource' => 'users']))->assertSuccessful();
    $rows     = importWorksheetRows($response->getFile()->getPathname());
    $comments = importWorksheetComments($response->getFile()->getPathname());

    expect([$rows[1][6], $rows[1][9], $rows[1][10], $rows[1][12]])
        ->toBe(['کد ملی', 'مرد', 'کارشناسی', 'فارغ‌التحصیل']);
    expect($comments['G1'])->toContain('کد ملی');
    expect($comments['J1'])->toContain('مرد');
    expect($comments['K1'])->toContain('کارشناسی');
    expect($comments['M1'])->toContain('فارغ‌التحصیل');
});

it('rejects unknown enum labels rather than treating them as valid translations', function (): void {
    $this->authorized_user([PermissionEnum::IMPORT_PREVIEW]);

    $response = postImportPreview($this, userImportFile([userImportRow([
        'civil_id_type'   => 'شناسه ناشناخته', 'gender' => 'ناشناخته',
        'education_level' => 'مقطع ناشناخته', 'education_status' => 'وضعیت ناشناخته',
    ])]));

    $response->assertSuccessful()->assertJsonPath('data.rows.0.status', 'invalid');
    expect(array_column($response->json('data.rows.0.errors'), 'field'))
        ->toContain('civil_id_type', 'gender', 'education_level', 'education_status');
});

it('approves a Persian spreadsheet even when the locale changes after preview', function (): void {
    app()->setLocale('fa');
    $this->authorized_user([PermissionEnum::IMPORT_PREVIEW, PermissionEnum::IMPORT_APPROVE]);
    $preview = postImportPreview($this, userImportFile([userImportRow([
        'civil_id_type'   => 'کد ملی', 'gender' => 'مرد',
        'education_level' => 'کارشناسی', 'education_status' => 'فارغ‌التحصیل',
    ])]))->assertSuccessful()->assertJsonPath('data.rows.0.status', 'valid');
    app()->setLocale('en');

    postImportApproval($this, $preview->json('data.run_id'))->assertSuccessful();

    $this->assertDatabaseHas('users', [
        'phone'  => '09123456789', 'civil_id_type' => 'national_code',
        'gender' => 'male', 'education_level' => 'bachelor', 'education_status' => 'graduated',
    ]);
});
