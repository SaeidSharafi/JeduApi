<?php

declare(strict_types=1);

use App\Actions\Admin\ImportExport\BuildImportTemplateAction;
use App\Enums\PermissionEnum;
use App\Services\ImportExport\ImportTemplateExport;
use App\Services\ImportExport\SpreadsheetResourceRegistry;

covers([
    BuildImportTemplateAction::class,
    ImportTemplateExport::class,
    SpreadsheetResourceRegistry::class,
]);

describe('authorization', function (): void {
    it('rejects guests', function (): void {
        $this->get(route('api.v1.admin.imports.template', ['resource' => 'users']))
            ->assertUnauthorized();
    });

    it('rejects staff without the template permission', function (): void {
        $this->unauthorized_user();

        $this->get(route('api.v1.admin.imports.template', ['resource' => 'users']))
            ->assertForbidden();
    });

    it('rejects an unregistered resource', function (): void {
        $this->authorized_user([PermissionEnum::IMPORT_TEMPLATE]);

        $this->get(route('api.v1.admin.imports.template', ['resource' => 'unknown_resource']))
            ->assertNotFound();
    });
});

describe('template content', function (): void {
    it('downloads the user import template built from the column contract', function (): void {
        $this->authorized_user([PermissionEnum::IMPORT_TEMPLATE]);

        $response = $this->get(route('api.v1.admin.imports.template', ['resource' => 'users']));

        $response->assertSuccessful();
        $response->assertDownload('users-import-template.xlsx');

        $rows = importWorksheetRows($response->getFile()->getPathname());

        expect($rows)->toHaveCount(2)
            ->and($rows[0])->toBe([
                'Mobile phone', 'Email', 'First name', 'Last name', 'Secondary phone', 'Civil ID',
                'Civil ID type', 'Date of birth (Jalali)', "Father's name", 'Gender', 'Education level',
                'Field of study', 'Education status', 'Password', 'Provision Moodle account',
                'Provision IMS account', 'Provision SpotPlayer account',
            ])
            ->and($rows[1])->toBe([
                '09123456789', 'user@example.com', 'علی', 'محمدی', '09120000000', '0000000000',
                'national_code', '1370-01-01', 'حسن', 'male', 'bachelor', 'مهندسی کامپیوتر',
                'graduated', null, 'true', 'true', 'true',
            ]);
    });

    it('documents every column as a cell comment', function (): void {
        $this->authorized_user([PermissionEnum::IMPORT_TEMPLATE]);

        $response = $this->get(route('api.v1.admin.imports.template', ['resource' => 'users']));

        $comments = importWorksheetComments($response->getFile()->getPathname());

        expect($comments)->toHaveCount(18)
            ->and($comments['A2'])->toBe(__('imports.template.example_notice'));

        $requiredColumn = explode("\n", $comments['A1']);
        $optionalColumn = explode("\n", $comments['B1']);

        expect($requiredColumn)->toBe([
            __('imports.guidance.phone'),
            __('imports.template.example', ['example' => '09123456789']),
            __('imports.template.english_key', ['key' => 'phone']),
            __('imports.template.required'),
        ])
            ->and($optionalColumn)->toBe([
                __('imports.guidance.email'),
                __('imports.template.example', ['example' => 'user@example.com']),
                __('imports.template.english_key', ['key' => 'email']),
            ]);
    });
});
