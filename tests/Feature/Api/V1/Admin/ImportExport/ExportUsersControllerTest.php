<?php

declare(strict_types=1);

use App\Actions\Admin\ImportExport\CreateExportAction;
use App\Enums\PermissionEnum;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

covers(CreateExportAction::class);

it('stores a private filtered export in the same deterministic order as the user list without pagination', function (): void {
    Storage::fake('local');
    $this->authorized_user([PermissionEnum::USER_VIEW_ANY, PermissionEnum::USER_EXPORT]);
    $first  = User::factory()->create(['first_name' => 'Export', 'last_name' => 'Same']);
    $second = User::factory()->create(['first_name' => 'Export', 'last_name' => 'Same']);
    User::factory()->create(['first_name' => 'Excluded']);
    $params = ['filter' => ['name' => 'Export'], 'sort' => '-last_name', 'per_page' => 1];
    $list   = $this->getJson(route('api.v1.admin.users.index', $params))->assertSuccessful();

    $response = $this->getJson(route('api.v1.admin.exports.create', ['resource' => 'users', ...$params]))->assertSuccessful();
    $download = $this->get($response->json('data.download_url'))->assertSuccessful()->assertDownload('users-export.xlsx');
    $rows     = importWorksheetRows($download->getFile()->getPathname());

    expect(array_column(array_slice($rows, 1), 0))->toBe([(string) $first->id, (string) $second->id]);
    expect($list->json('data.data.0.id'))->toBe($first->id);
    $files = Storage::disk('local')->allFiles('exports');
    expect($files)->toHaveCount(1);
    expect(Storage::disk('local')->getVisibility($files[0]))->toBe('private');
});

it('exports only safe fields with localized headings, translated enums and Jalali dates', function (string $locale, array $labels, array $headings): void {
    Storage::fake('local');
    $this->authorized_user([PermissionEnum::USER_EXPORT]);
    $user = User::factory()->withPassword()->create([
        'first_name'      => '=1+1', 'phone' => '09123456789', 'civil_id' => '0000000019',
        'gender'          => 'male', 'civil_id_type' => 'national_code',
        'education_level' => 'bachelor', 'education_status' => 'graduated', 'date_of_birth' => '1991-03-21',
        'created_at'      => '2024-03-20 12:34:56', 'updated_at' => '2024-03-20 12:34:56',
    ]);
    $user->createToken('secret');

    $response = $this->getJson(route('api.v1.admin.exports.create', ['resource' => 'users', 'locale' => $locale]))->assertSuccessful();
    $download = $this->get($response->json('data.download_url'))->assertSuccessful();
    $rows     = importWorksheetRows($download->getFile()->getPathname());

    expect($rows[0])->toBe($headings);
    expect($rows[1][1])->toBe('=1+1');
    expect($rows[1][3])->toBe('09123456789');
    expect($rows[1][6])->toBe('0000000019');
    expect($rows[1][8])->toBe('1370-01-01');
    expect([$rows[1][7], $rows[1][10], $rows[1][11], $rows[1][13]])->toBe($labels);
    expect($rows[1][14])->toBe('1403-01-01 12:34:56');
    expect($rows[1][15])->toBe('1403-01-01 12:34:56');
})->with([
    'Persian' => ['fa', ['کد ملی', 'مرد', 'کارشناسی', 'فارغ‌التحصیل'], [
        'شناسه', 'نام', 'نام خانوادگی', 'تلفن همراه', 'پست الکترونیکی', 'تلفن همراه دوم', 'کد شناسایی',
        'نوع کد شناسایی', 'تاریخ تولد (شمسی)', 'نام پدر', 'جنسیت', 'مقطع تحصیلی',
        'رشته تحصیلی', 'وضعیت تحصیلی', 'زمان ایجاد', 'زمان به‌روزرسانی',
    ]],
    'English' => ['en', ['National Code', 'Male', 'Bachelor', 'Graduated'], [
        'ID', 'First name', 'Last name', 'Mobile phone', 'Email', 'Secondary phone', 'Civil ID',
        'Civil ID type', 'Date of birth (Jalali)', "Father's name", 'Gender', 'Education level',
        'Field of study', 'Education status', 'Created at', 'Updated at',
    ]],
]);

it('rejects guests for export generation with 401', function (): void {
    $this->getJson(route('api.v1.admin.exports.create', ['resource' => 'users']))->assertUnauthorized();
});

it('rejects staff with list permission but no export permission with 403', function (): void {
    Storage::fake('local');
    $this->authorized_user([PermissionEnum::USER_VIEW_ANY]);

    $this->getJson(route('api.v1.admin.exports.create', ['resource' => 'users']))->assertForbidden();

    expect(Storage::disk('local')->allFiles('exports'))->toBe([]);
});

it('requires export permission again when downloading a generated artifact', function (): void {
    Storage::fake('local');
    $this->authorized_user([PermissionEnum::USER_EXPORT]);
    $response = $this->getJson(route('api.v1.admin.exports.create', ['resource' => 'users']))->assertSuccessful();
    $this->authorized_user([]);

    $this->get($response->json('data.download_url'))->assertForbidden();
});

it('rejects expired or tampered download references with 403', function (string $case): void {
    Storage::fake('local');
    $this->freezeTime();
    $this->authorized_user([PermissionEnum::USER_EXPORT]);
    $response = $this->getJson(route('api.v1.admin.exports.create', ['resource' => 'users']))->assertSuccessful();
    $url      = $response->json('data.download_url');
    if ($case === 'expired') {
        $this->travel(25)->hours();
    } else {
        $url .= '&extra=tampered';
    }

    $this->get($url)->assertForbidden();
})->with(['expired', 'tampered']);

it('returns 404 when a generated artifact is missing', function (): void {
    Storage::fake('local');
    $this->authorized_user([PermissionEnum::USER_EXPORT]);
    $response = $this->getJson(route('api.v1.admin.exports.create', ['resource' => 'users']))->assertSuccessful();
    Storage::disk('local')->delete(Storage::disk('local')->allFiles('exports'));

    $this->get($response->json('data.download_url'))->assertNotFound();
});

it('rejects unregistered resources with 404', function (): void {
    $this->authorized_user([PermissionEnum::USER_EXPORT]);

    $this->getJson(route('api.v1.admin.exports.create', ['resource' => 'unknown']))->assertNotFound();
});

it('rejects unsupported locales with 422', function (): void {
    $this->authorized_user([PermissionEnum::USER_EXPORT]);

    $this->getJson(route('api.v1.admin.exports.create', ['resource' => 'users', 'locale' => 'invalid']))
        ->assertUnprocessable()->assertJsonValidationErrors('locale');
});

it('uses every user list filter in exports', function (array $filter): void {
    Storage::fake('local');
    $this->authorized_user([PermissionEnum::USER_VIEW_ANY, PermissionEnum::USER_EXPORT]);
    $matching = User::factory()->create([
        'first_name'    => 'Selected', 'last_name' => 'Person', 'email' => 'selected@example.com',
        'phone'         => '09123456789', 'civil_id' => '0000000019', 'civil_id_type' => 'national_code',
        'date_of_birth' => '1991-03-21',
    ]);
    $excluded = User::factory()->create([
        'first_name'    => 'Excluded', 'last_name' => 'Person', 'email' => 'excluded@example.com',
        'phone'         => '09120000000', 'civil_id' => '987654321', 'civil_id_type' => 'passport',
        'date_of_birth' => '1990-03-21',
    ]);
    $matching->wallet()->update(['status' => 'active']);
    $excluded->wallet()->update(['status' => 'suspended']);
    $params = ['filter' => $filter];
    $list   = $this->getJson(route('api.v1.admin.users.index', $params))->assertSuccessful();

    $response = $this->getJson(route('api.v1.admin.exports.create', ['resource' => 'users', ...$params]))->assertSuccessful();
    $download = $this->get($response->json('data.download_url'))->assertSuccessful();
    $rows     = importWorksheetRows($download->getFile()->getPathname());

    expect(array_column($list->json('data.data'), 'id'))->toBe([$matching->id]);
    expect(array_column(array_slice($rows, 1), 0))->toBe([(string) $matching->id]);
})->with([
    'name'              => [['name' => 'Selected']],
    'email'             => [['email' => 'selected@']],
    'phone'             => [['phone' => '3456789']],
    'civil ID'          => [['civil_id' => '0000000019']],
    'civil ID type'     => [['civil_id_type' => 'national_code']],
    'wallet status'     => [['wallet_status' => 'active']],
    'Jalali date range' => [['date_of_birth_from' => '1370-01-01', 'date_of_birth_to' => '1370-01-01']],
]);

it('rejects unsafe filters and sort fields for both list and export', function (array $params): void {
    Storage::fake('local');
    $this->authorized_user([PermissionEnum::USER_VIEW_ANY, PermissionEnum::USER_EXPORT]);

    $this->getJson(route('api.v1.admin.users.index', $params))->assertBadRequest();
    $this->getJson(route('api.v1.admin.exports.create', ['resource' => 'users', ...$params]))->assertBadRequest();

    expect(Storage::disk('local')->allFiles('exports'))->toBe([]);
})->with([
    'filter' => [['filter' => ['password' => 'secret']]],
    'sort'   => [['sort' => 'password']],
]);

it('requires authentication to download even with a valid signed reference', function (): void {
    Storage::fake('local');
    $this->authorized_user([PermissionEnum::USER_EXPORT]);
    $response = $this->getJson(route('api.v1.admin.exports.create', ['resource' => 'users']))->assertSuccessful();
    $this->app['auth']->forgetGuards();

    $this->get($response->json('data.download_url'))->assertUnauthorized();
});

it('applies requested sort directions before the stable ID tie-breaker', function (string $sort, bool $descending): void {
    Storage::fake('local');
    $this->authorized_user([PermissionEnum::USER_VIEW_ANY, PermissionEnum::USER_EXPORT]);
    $first    = User::factory()->create(['first_name' => 'Alpha']);
    $second   = User::factory()->create(['first_name' => 'Beta']);
    $third    = User::factory()->create(['first_name' => 'Beta']);
    $expected = $descending ? [$second->id, $third->id, $first->id] : [$first->id, $second->id, $third->id];
    $list     = $this->getJson(route('api.v1.admin.users.index', ['sort' => $sort]))->assertSuccessful();

    $response = $this->getJson(route('api.v1.admin.exports.create', ['resource' => 'users', 'sort' => $sort]))->assertSuccessful();
    $download = $this->get($response->json('data.download_url'))->assertSuccessful();
    $rows     = importWorksheetRows($download->getFile()->getPathname());

    expect(array_column($list->json('data.data'), 'id'))->toBe($expected);
    expect(array_column(array_slice($rows, 1), 0))->toBe(array_map(strval(...), $expected));
})->with(['ascending' => ['first_name', false], 'descending' => ['-first_name', true]]);

it('removes a partial artifact when spreadsheet storage fails', function (): void {
    Storage::fake('local');
    $this->authorized_user([PermissionEnum::USER_EXPORT]);
    $root    = Storage::disk('local')->path('');
    $adapter = new class($root) extends League\Flysystem\Local\LocalFilesystemAdapter
    {
        public function writeStream(string $path, $contents, League\Flysystem\Config $config): void
        {
            parent::writeStream($path, $contents, $config);

            throw League\Flysystem\UnableToWriteFile::atLocation($path);
        }
    };
    Storage::set('local', new Illuminate\Filesystem\FilesystemAdapter(
        new League\Flysystem\Filesystem($adapter), $adapter, ['root' => $root],
    ));

    $this->getJson(route('api.v1.admin.exports.create', ['resource' => 'users']))->assertServerError();

    expect(Storage::disk('local')->allFiles('exports'))->toBe([]);
});

it('uses the application locale for export headings when locale is omitted', function (string $locale, string $heading): void {
    Storage::fake('local');
    $this->authorized_user([PermissionEnum::USER_EXPORT]);
    app()->setLocale($locale);

    $response = $this->getJson(route('api.v1.admin.exports.create', ['resource' => 'users']))->assertSuccessful();
    $download = $this->get($response->json('data.download_url'))->assertSuccessful();
    $rows     = importWorksheetRows($download->getFile()->getPathname());

    expect($rows[0][0])->toBe($heading);
})->with([
    'Persian application locale' => ['fa', 'شناسه'],
    'English application locale' => ['en', 'ID'],
]);
