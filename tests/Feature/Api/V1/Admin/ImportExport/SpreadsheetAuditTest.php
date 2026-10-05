<?php

declare(strict_types=1);

use App\Enums\PermissionEnum;
use App\Http\Middleware\AdminAuditMiddleware;
use App\Models\AdminActionLog;
use App\Models\ImportRun;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Config;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\UnableToWriteFile;
use Tests\Support\Traits\AuthTestTrait;

uses(AuthTestTrait::class);

covers(AdminAuditMiddleware::class);

beforeEach(function (): void {
    Storage::fake('local');
    $this->withMiddleware(AdminAuditMiddleware::class);
});

it('audits handled validation and forbidden outcomes without retaining arbitrary input', function (): void {
    $this->authorized_user([PermissionEnum::IMPORT_PREVIEW]);
    postImportPreview(
        $this,
        importSpreadsheet([[['unknown-heading'], ['distinctive-row-secret']]], 'private-client-name.xlsx'),
    )->assertUnprocessable();

    $this->getJson(route('api.v1.admin.exports.create', [
        'resource'  => 'users',
        'filter'    => ['password' => 'nested-secret'],
        'arbitrary' => 'query-secret',
    ]))->assertForbidden();

    $logs = AdminActionLog::query()->orderBy('id')->get();
    expect($logs)->toHaveCount(2)
        ->and($logs[0]->metadata['request_outcome'])->toBe('validation_failure')
        ->and($logs[0]->metadata)->not->toHaveKey('rows_total')
        ->and($logs[1]->metadata['request_outcome'])->toBe('forbidden')
        ->and(json_encode($logs->toArray(), JSON_THROW_ON_ERROR))
        ->not->toContain('distinctive-row-secret')
        ->not->toContain('private-client-name')
        ->not->toContain('nested-secret')
        ->not->toContain('query-secret');
});

it('keeps template and polling GET requests outside spreadsheet operation auditing', function (): void {
    $this->authorized_user([
        PermissionEnum::IMPORT_PREVIEW,
        PermissionEnum::IMPORT_RESULTS,
        PermissionEnum::IMPORT_TEMPLATE,
    ]);
    $runId = postImportPreview($this, userImportFile([userImportRow()]))
        ->assertSuccessful()
        ->json('data.run_id');

    $this->getJson(route('api.v1.admin.imports.results', ['resource' => 'users', 'run' => $runId]))->assertSuccessful();
    $this->get(route('api.v1.admin.imports.template', ['resource' => 'users']))->assertSuccessful();

    expect(AdminActionLog::query()->where('admin_id', $this->user->id)->count())->toBe(1);
});

it('does not create a staff audit record for a guest', function (): void {
    postImportPreview($this, userImportFile([userImportRow()]))->assertUnauthorized();

    expect(AdminActionLog::query()->count())->toBe(0);
});

it('marks approval at the exact run deadline as expired', function (): void {
    $this->authorized_user([PermissionEnum::IMPORT_PREVIEW, PermissionEnum::IMPORT_APPROVE]);
    $runId = postImportPreview($this, userImportFile([userImportRow()]))->assertSuccessful()->json('data.run_id');
    $run   = ImportRun::query()->where('uuid', $runId)->firstOrFail();
    $this->travelTo($run->artifacts_expires_at);

    postImportApproval($this, $runId)->assertUnprocessable();

    $log = AdminActionLog::query()->where('route_name', 'api.v1.admin.imports.approve')->latest('id')->firstOrFail();
    expect($log->metadata['request_outcome'])->toBe('expired')
        ->and($log->metadata['failure_code'])->toBe('expired')
        ->and($log->metadata)->not->toHaveKey('created_count');
});

it('classifies expired signed download references as expired', function (): void {
    $this->authorized_user([PermissionEnum::USER_EXPORT]);
    $export = $this->getJson(route('api.v1.admin.exports.create', ['resource' => 'users']))->assertSuccessful();
    $this->travel(25)->hours();

    $this->get($export->json('data.download_url'))->assertForbidden();

    $log = AdminActionLog::query()->where('route_name', 'api.v1.admin.exports.download')->firstOrFail();
    expect($log->metadata['request_outcome'])->toBe('expired')
        ->and($log->metadata['failure_code'])->toBe('expired')
        ->and($log->metadata)->not->toHaveKey('signature');
});

it('keeps a successful export successful when audit persistence fails', function (): void {
    $this->authorized_user([PermissionEnum::USER_EXPORT]);
    AdminActionLog::creating(function (AdminActionLog $log): never {
        throw new RuntimeException('audit persistence secret');
    });

    $this->getJson(route('api.v1.admin.exports.create', ['resource' => 'users']))->assertSuccessful();
});

it('renders and audits a thrown export storage failure without changing the server error response', function (): void {
    $this->authorized_user([PermissionEnum::USER_EXPORT]);
    $root    = Storage::disk('local')->path('');
    $adapter = new class($root) extends LocalFilesystemAdapter
    {
        public function writeStream(string $path, $contents, Config $config): void
        {
            parent::writeStream($path, $contents, $config);
            throw UnableToWriteFile::atLocation($path);
        }
    };
    Storage::set('local', new FilesystemAdapter(new Filesystem($adapter), $adapter, ['root' => $root]));

    $response = $this->getJson(route('api.v1.admin.exports.create', ['resource' => 'users']))
        ->assertServerError();

    expect($response->status())->toBe(500)
        ->and(AdminActionLog::query()->count())->toBe(1)
        ->and(AdminActionLog::query()->sole()->metadata['request_outcome'])->toBe('server_failure')
        ->and(AdminActionLog::query()->sole()->metadata['failure_code'])->toBe('server_error')
        ->and(AdminActionLog::query()->sole()->metadata)->not->toHaveKey('exported_rows');
});

it('audits preview approval replay and exports once with allowlisted spreadsheet details', function (): void {
    $this->authorized_user([
        PermissionEnum::IMPORT_PREVIEW,
        PermissionEnum::IMPORT_APPROVE,
        PermissionEnum::USER_EXPORT,
    ]);

    $preview = postImportPreview(
        $this,
        importSpreadsheet([[userImportHeadings(), userImportRow()]], 'secret-client-filename.xlsx'),
    )->assertSuccessful();
    $runId = $preview->json('data.run_id');
    postImportApproval($this, $runId)->assertSuccessful();
    postImportApproval($this, $runId)->assertSuccessful();
    User::factory()->create(['first_name' => 'distinctive-free-text-secret']);

    $export = $this->getJson(route('api.v1.admin.exports.create', [
        'resource'  => 'users',
        'filter'    => ['name' => 'distinctive-free-text-secret', 'wallet_status' => 'active'],
        'sort'      => 'first_name',
        'unrelated' => 'arbitrary-query-secret',
    ]))->assertSuccessful();
    $this->get($export->json('data.download_url'))->assertSuccessful();

    $logs = AdminActionLog::query()->orderBy('id')->get();
    expect($logs)->toHaveCount(5)
        ->and($logs->pluck('metadata.operation')->all())->toBe([
            'import_preview', 'import_approve', 'import_approve', 'export_create', 'export_download',
        ])
        ->and($logs[0]->action_type)->toBe('create')
        ->and($logs[0]->resource_id)->toBeNull()
        ->and($logs[0]->metadata['run_uuid'])->toBe($runId)
        ->and($logs[0]->metadata['rows_total'])->toBe(1)
        ->and($logs[0]->request_data)->toMatchArray(['operation' => 'import_preview', 'resource' => 'users', 'identity_key' => 'phone'])
        ->and($logs[2]->metadata['request_outcome'])->toBe('approval_replay')
        ->and($logs[3]->metadata['exported_rows'])->toBe(1)
        ->and($logs[3]->request_data['filter'])->toBe(['wallet_status' => 'active'])
        ->and($logs[4]->action_type)->toBe('view')
        ->and($logs[4]->metadata['artifact_uuid'])->not->toBeEmpty();

    $serializedLogs = json_encode($logs->toArray(), JSON_THROW_ON_ERROR);
    expect($serializedLogs)
        ->not->toContain('secret-client-filename')
        ->not->toContain('distinctive-free-text-secret')
        ->not->toContain('arbitrary-query-secret')
        ->not->toContain('signature=');
});

it('audits replay when another approval commits after the request begins', function (): void {
    $this->authorized_user([PermissionEnum::IMPORT_PREVIEW, PermissionEnum::IMPORT_APPROVE]);
    $runId = postImportPreview($this, userImportFile([userImportRow()]))->json('data.run_id');
    AdminActionLog::query()->delete();
    $middleware = new class
    {
        public function handle(Illuminate\Http\Request $request, Closure $next): Symfony\Component\HttpFoundation\Response
        {
            app(App\Actions\Admin\ImportExport\ApproveImportRunAction::class)->handle(
                $request->route('resource'), $request->route('run'),
                App\Data\Admin\ImportExport\ImportApprovalRequestData::from([]),
            );

            return $next($request);
        }
    };
    $this->app->instance('test.competing-import-approval', $middleware);
    $router = $this->app->make('router');
    $router->aliasMiddleware('competing-import-approval', 'test.competing-import-approval');
    $router->getRoutes()->getByName('api.v1.admin.imports.approve')->middleware('competing-import-approval');

    postImportApproval($this, $runId)->assertSuccessful();

    $log = AdminActionLog::query()->sole();
    expect($log->metadata['request_outcome'])->toBe('approval_replay')
        ->and($log->metadata['approval_replay'])->toBeTrue()
        ->and($log->metadata['created_count'])->toBe(1);
    expect(User::query()->where('phone', '09123456789')->count())->toBe(1);
});
