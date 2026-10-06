<?php

declare(strict_types=1);

use App\Enums\PermissionEnum;
use App\Http\Middleware\AdminAuditMiddleware;
use App\Models\AdminActionLog;
use App\Models\ImportExportArtifact;
use App\Models\ImportRun;
use App\Models\Staff;
use App\Models\User;
use App\Services\ImportExport\SpreadsheetAuditContext;
use App\Services\ImportExport\SpreadsheetAuditLogger;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Config;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\UnableToWriteFile;
use Tests\Support\Traits\AuthTestTrait;

uses(AuthTestTrait::class);

mutates(AdminAuditMiddleware::class);

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
        public function handle(Request $request, Closure $next): Symfony\Component\HttpFoundation\Response
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

function spreadsheetAuditPrivacyRequest(string $operation, array $query = [], ?string $uuid = null): Request
{
    $routes = [
        'import_preview'  => ['POST', 'users/import', 'api.v1.admin.imports.preview'],
        'import_approve'  => ['POST', 'users/import/{run}/approve', 'api.v1.admin.imports.approve'],
        'export_create'   => ['GET', 'users/export', 'api.v1.admin.exports.create'],
        'export_download' => ['GET', 'users/export/{artifact}', 'api.v1.admin.exports.download'],
    ];
    [$method, $path, $name] = $routes[$operation];
    $uri                    = '/'.str_replace(['{run}', '{artifact}'], $uuid ?? '', $path);
    $request                = Request::create($uri, $method, $query);
    $request->query->add($query);
    $route = new Route($method, '{resource}/'.mb_substr($path, 6), fn () => null);
    $route->name($name)->bind($request);
    $route->setParameter('resource', 'users');
    if ($operation === 'import_approve' && $uuid !== null) {
        $route->setParameter('run', $uuid);
    }
    if ($operation === 'export_download' && $uuid !== null) {
        $route->setParameter('artifact', $uuid);
    }
    $request->setRouteResolver(fn () => $route);

    return $request;
}

describe('SpreadsheetAuditLogger', function (): void {
    it('persists only allowlisted export filters and rejects malformed dates and sensitive parameters', function (array $filters, ?array $safeFilters, string $sort, ?string $safeSort): void {
        $this->travelTo(now()->setTime(12, 0));
        $staff   = Staff::factory()->create();
        $context = new SpreadsheetAuditContext;
        $context->begin('export_create', $staff->id, microtime(true));
        $context->recordExportedRows(4);
        $request = spreadsheetAuditPrivacyRequest('export_create', [
            'locale' => 'en', 'filter' => $filters, 'sort' => $sort, 'password' => 'secret-value',
        ]);
        $uuid = 'a051e060-c934-4bac-8d98-098c100da020';

        app(SpreadsheetAuditLogger::class)->record($request, new JsonResponse(['data' => [
            'download_url' => 'https://example.test/users/export/'.$uuid.'?signature=private',
            'password'     => 'secret-response',
        ]]), $context);

        $log      = AdminActionLog::query()->sole();
        $expected = ['operation' => 'export_create', 'resource' => 'users', 'locale' => 'en'];
        if ($safeFilters !== null) {
            $expected['filter'] = $safeFilters;
        }
        if ($safeSort !== null) {
            $expected['sort'] = $safeSort;
        }
        expect($log->request_data)->toMatchArray($expected)
            ->toHaveCount(count($expected))
            ->and($log->metadata['request_outcome'])->toBe('export_generated')
            ->and($log->metadata['artifact_uuid'])->toBe($uuid)
            ->and($log->metadata['exported_rows'])->toBe(4)
            ->and($log->risk_level)->toBe('low')
            ->and(json_encode($log->toArray(), JSON_THROW_ON_ERROR))->not->toContain('secret-value')->not->toContain('secret-response')->not->toContain('signature');
    })->with([
        'valid allowlist' => [['civil_id_type' => 'national_code', 'wallet_status' => 'active', 'date_of_birth_from' => '2000-02-29', 'date_of_birth_to' => '2001-12-31', 'email' => 'private@example.test'], ['civil_id_type' => 'national_code', 'wallet_status' => 'active', 'date_of_birth_from' => '2000-02-29', 'date_of_birth_to' => '2001-12-31'], '-first_name,email', '-first_name,email'],
        'invalid values'  => [['civil_id_type' => 'private', 'wallet_status' => 'unknown', 'date_of_birth_from' => '2001-02-29', 'date_of_birth_to' => ['secret']], null, 'password', null],
    ]);

    it('audits an expired missing download as expired without retaining an invalid identity key', function (): void {
        $this->freezeTime();
        $staff    = Staff::factory()->create();
        $artifact = ImportExportArtifact::factory()->create(['expires_at' => now()]);
        $context  = new SpreadsheetAuditContext;
        $context->begin('export_download', $staff->id, microtime(true));
        $request = spreadsheetAuditPrivacyRequest('export_download', ['identity_key' => 'secret'], $artifact->artifact_uuid);

        app(SpreadsheetAuditLogger::class)->record($request, new JsonResponse(['data' => []], 404), $context);

        $log = AdminActionLog::query()->sole();
        expect($log->metadata)->toMatchArray(['request_outcome' => 'expired', 'failure_code' => 'expired', 'artifact_uuid' => $artifact->artifact_uuid])
            ->and($log->request_data)->toMatchArray(['operation' => 'export_download', 'resource' => 'users'])
            ->toHaveCount(2);
    });

    it('records expired approval status without trusting response counters', function (): void {
        $this->freezeTime();
        $run     = ImportRun::factory()->create(['artifacts_expires_at' => now()]);
        $context = new SpreadsheetAuditContext;
        $context->begin('import_approve', $run->staff_id, microtime(true));
        $request = spreadsheetAuditPrivacyRequest('import_approve', [], $run->uuid);

        app(SpreadsheetAuditLogger::class)->record($request, new JsonResponse(['data' => []], 422), $context);

        $log = AdminActionLog::query()->sole();
        expect($log->metadata)->toMatchArray([
            'request_outcome' => 'expired', 'failure_code' => 'expired', 'run_uuid' => $run->uuid,
            'run_status'      => 'preview_ready',
        ])->not->toHaveKey('created_count');
    });

    it('records preview counters once per context and omits invalid summary values', function (): void {
        $staff   = Staff::factory()->create();
        $context = new SpreadsheetAuditContext;
        $context->begin('import_preview', $staff->id, microtime(true));
        $request  = spreadsheetAuditPrivacyRequest('import_preview', ['identity_key' => 'email']);
        $uuid     = 'a051e060-c934-4bac-8d98-098c100da020';
        $response = new JsonResponse(['data' => [
            'run_id'  => $uuid, 'status' => 'preview_ready',
            'summary' => ['total_rows' => 3, 'valid_rows' => 2, 'invalid_rows' => 'private'],
        ]]);
        $logger = app(SpreadsheetAuditLogger::class);

        $logger->record($request, $response, $context);
        $logger->record($request, $response, $context);

        $log = AdminActionLog::query()->sole();
        expect($log->metadata)->toMatchArray(['run_uuid' => $uuid, 'rows_total' => 3, 'rows_valid' => 2, 'identity_key' => 'email', 'request_outcome' => 'preview_succeeded_no_changes'])
            ->not->toHaveKey('rows_invalid');
    });

    it('records approval replay counters with the safe run reference', function (): void {
        $staff   = Staff::factory()->create();
        $context = new SpreadsheetAuditContext;
        $context->begin('import_approve', $staff->id, microtime(true), true);
        $uuid    = 'a051e060-c934-4bac-8d98-098c100da020';
        $request = spreadsheetAuditPrivacyRequest('import_approve', [], $uuid);

        app(SpreadsheetAuditLogger::class)->record($request, new JsonResponse(['data' => [
            'identity_key' => 'phone', 'status' => 'completed',
            'summary'      => ['created_count' => 1, 'updated_count' => 2, 'provider_queued_count' => 0],
        ]]), $context);

        expect(AdminActionLog::query()->sole()->metadata)->toMatchArray([
            'run_uuid'      => $uuid, 'approval_replay' => true, 'request_outcome' => 'approval_replay',
            'created_count' => 1, 'updated_count' => 2, 'provider_queued_count' => 0,
        ]);
    });

    it('classifies failed operations and their risk without storing arbitrary response details', function (int $status, string $outcome, string $failure, int $hour, string $risk): void {
        $this->travelTo(now()->setTime($hour, 0));
        $staff   = Staff::factory()->create();
        $context = new SpreadsheetAuditContext;
        $context->begin('import_preview', $staff->id, microtime(true));
        $request = spreadsheetAuditPrivacyRequest('import_preview', ['identity_key' => 'private']);

        app(SpreadsheetAuditLogger::class)->record($request, new JsonResponse(['data' => ['run_id' => 'invalid-secret', 'detail' => 'response-secret']], $status), $context);

        $log = AdminActionLog::query()->sole();
        expect($log->metadata)->toMatchArray(['request_outcome' => $outcome, 'failure_code' => $failure])
            ->not->toHaveKey('run_uuid')->not->toHaveKey('identity_key')
            ->and($log->risk_level)->toBe($risk)
            ->and(json_encode($log->toArray(), JSON_THROW_ON_ERROR))->not->toContain('secret');
    })->with([
        'server error' => [500, 'server_failure', 'server_error', 12, 'high'],
        'forbidden'    => [403, 'forbidden', 'forbidden', 23, 'medium'],
        'not found'    => [404, 'not_found', 'not_found', 6, 'medium'],
        'gone'         => [410, 'not_found', 'not_found', 12, 'low'],
        'invalid'      => [422, 'validation_failure', 'validation_error', 12, 'low'],
    ]);
});
