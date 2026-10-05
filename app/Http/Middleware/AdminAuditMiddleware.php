<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\User\CivilIdTypeEnum;
use App\Enums\Wallet\WalletStatusEnum;
use App\Models\AdminActionLog;
use App\Models\ImportExportArtifact;
use App\Models\ImportRun;
use App\Services\ImportExport\SpreadsheetAuditContext;
use Closure;
use Exception;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class AdminAuditMiddleware
{
    public function __construct(
        private readonly SpreadsheetAuditContext $spreadsheetAuditContext,
        private readonly ExceptionHandler $exceptionHandler,
    ) {}

    /**
     * Handle an incoming request and log admin actions.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);

        // Snapshot the actor before the request runs: endpoints that mutate
        // auth state (logout, token revocation) would otherwise resolve null/wrong.
        $adminId = auth('staff')->id();

        $spreadsheetOperation = $this->spreadsheetOperation($request);
        if ($spreadsheetOperation !== null) {
            $this->spreadsheetAuditContext->reset();
        }
        $approvedBeforeRequest = $spreadsheetOperation === 'import_approve'
            ? ImportRun::query()->where('uuid', $request->route('run'))->value('approved_at') !== null
            : false;

        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            if ($adminId === null || $spreadsheetOperation === null) {
                throw $exception;
            }

            $response = $this->exceptionHandler->render($request, $exception);
            Log::error('Admin spreadsheet operation failed', [
                'route'    => $request->route()?->getName(),
                'admin_id' => $adminId,
            ]);
        }

        // Only log if authenticated staff member
        if ($adminId === null) {
            return $response;
        }

        // Skip certain routes to avoid noise
        if ($spreadsheetOperation === null && $this->shouldSkipLogging($request)) {
            return $response;
        }

        try {
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            if ($spreadsheetOperation !== null) {
                $this->logSpreadsheetAction($request, $response, $executionTime, $adminId, $spreadsheetOperation, $approvedBeforeRequest, $this->spreadsheetAuditContext);
            } else {
                $this->logAdminAction($request, $response, $executionTime, $adminId);
            }
        }
        // @codeCoverageIgnoreStart
        catch (Throwable $e) {
            // Log the error but don't break the request
            if ($spreadsheetOperation !== null) {
                Log::error('AdminAuditMiddleware failed to log spreadsheet action', [
                    'failure_code' => class_basename($e),
                    'route'        => $request->route()?->getName(),
                    'admin_id'     => $adminId,
                ]);
            } elseif ($e instanceof Exception) {
                Log::error('AdminAuditMiddleware failed to log action', [
                    'error'    => $e->getMessage(),
                    'route'    => $request->route()?->getName(),
                    'admin_id' => $adminId,
                ]);
            } else {
                throw $e;
            }
        }
        // @codeCoverageIgnoreEnd

        return $response;
    }

    private function spreadsheetOperation(Request $request): ?string
    {
        return match ($request->route()?->getName()) {
            'api.v1.admin.imports.preview'  => 'import_preview',
            'api.v1.admin.imports.approve'  => 'import_approve',
            'api.v1.admin.exports.create'   => 'export_create',
            'api.v1.admin.exports.download' => 'export_download',
            default                         => null,
        };
    }

    /** @param 'import_preview'|'import_approve'|'export_create'|'export_download' $operation */
    private function logSpreadsheetAction(Request $request, Response $response, float $executionTime, int $adminId, string $operation, bool $approvedBeforeRequest, ?SpreadsheetAuditContext $auditContext): void
    {
        $resource     = $request->route('resource');
        $routeName    = (string) $request->route()?->getName();
        $payload      = $response instanceof \Illuminate\Http\JsonResponse ? $response->getData(true) : [];
        $data         = is_array($payload) && is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $runUuid      = $operation === 'import_preview' ? ($data['run_id'] ?? null) : ($operation === 'import_approve' ? $request->route('run') : null);
        $runUuid      = is_string($runUuid) && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $runUuid) ? $runUuid : null;
        $artifactUuid = $operation === 'export_download' ? $request->route('artifact') : null;
        if ($operation === 'export_create' && is_string($data['download_url'] ?? null)) {
            $path         = parse_url($data['download_url'], PHP_URL_PATH);
            $artifactUuid = is_string($path) && preg_match('~/export/([0-9a-f-]{36})$~i', $path, $match) ? $match[1] : null;
        }

        $run      = is_string($runUuid) ? ImportRun::query()->where('uuid', $runUuid)->where('resource', $resource)->first() : null;
        $artifact = is_string($artifactUuid) ? ImportExportArtifact::query()->where('artifact_uuid', $artifactUuid)->where('resource', $resource)->first() : null;
        $status   = $response->getStatusCode();
        $outcome  = $this->spreadsheetOutcome($operation, $status, $approvedBeforeRequest);
        if ($status === 404) {
            if ($run?->artifacts_expires_at?->isPast() || $artifact?->expires_at?->isPast()) {
                $outcome = 'expired';
            } else {
                $outcome = 'not_found';
            }
        } elseif ($status === 422 && $run?->artifacts_expires_at?->isPast()) {
            $outcome = 'expired';
        }
        $metadata = [
            'operation'         => $operation,
            'resource'          => $resource,
            'request_outcome'   => $outcome,
            'execution_time_ms' => $executionTime,
            'memory_usage'      => memory_get_usage(true),
            'timestamp'         => now()->toISOString(),
            'request_size'      => mb_strlen($request->getContent()),
            'response_size'     => $response instanceof \Illuminate\Http\JsonResponse ? mb_strlen($response->getContent()) : 0,
        ];
        if ($operation === 'import_preview') {
            $identityKey = $request->query('identity_key');
            if (in_array($identityKey, ['phone', 'email'], true)) {
                $metadata['identity_key'] = $identityKey;
            }
        }
        if ($status >= 400) {
            $metadata['failure_code'] = match (true) {
                $status >= 500                      => 'server_error',
                $status  === 403                    => 'forbidden',
                $outcome === 'expired'              => 'expired',
                $status  === 404 || $status === 410 => 'not_found',
                default                             => 'validation_error',
            };
        }
        if (in_array($operation, ['export_create', 'export_download'], true)) {
            $metadata += $this->safeExportParameters($request);
        }
        if ($run !== null) {
            $metadata += [
                'run_uuid'     => $run->uuid,
                'identity_key' => $run->identity_key->value,
                'run_status'   => $run->status->value,
            ];
            if ($operation === 'import_preview' || $operation === 'import_approve') {
                $metadata['filename'] = "{$resource}-import-{$run->uuid}.xlsx";
            }
            if ($operation === 'import_preview') {
                $metadata['rows_total']   = $run->rows_total;
                $metadata['rows_valid']   = $run->rows_valid;
                $metadata['rows_invalid'] = $run->rows_invalid;
            } elseif ($operation === 'import_approve' && $run->approved_at !== null) {
                $metadata['created_count']         = $run->created_count;
                $metadata['updated_count']         = $run->updated_count;
                $metadata['provider_queued_count'] = $run->provider_queued_count;
                $metadata['approval_replay']       = $approvedBeforeRequest;
            }
        } elseif ($runUuid !== null && $operation === 'import_approve') {
            $metadata['run_uuid'] = $runUuid;
        }
        if ($artifact !== null) {
            $metadata['artifact_uuid'] = $artifact->artifact_uuid;
            $metadata['filename']      = "{$resource}-export.xlsx";
        } elseif (is_string($artifactUuid) && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $artifactUuid)) {
            $metadata['artifact_uuid'] = $artifactUuid;
        }
        $exportedRows = $auditContext?->exportedRows();
        if ($operation === 'export_create' && $exportedRows !== null && $status < 400) {
            $metadata['exported_rows'] = $exportedRows;
        }

        $requestData = ['operation' => $operation, 'resource' => $resource];
        if ($operation === 'import_preview') {
            $identityKey = $request->query('identity_key');
            if (in_array($identityKey, ['phone', 'email'], true)) {
                $requestData['identity_key'] = $identityKey;
            }
        }
        if (in_array($operation, ['export_create', 'export_download'], true)) {
            $requestData += $this->safeExportParameters($request);
        }

        AdminActionLog::create([
            'admin_id'        => $adminId,
            'action_type'     => $request->isMethod('GET') ? 'view' : 'create',
            'resource_type'   => null,
            'resource_id'     => null,
            'route_name'      => $routeName,
            'http_method'     => $request->method(),
            'request_data'    => $requestData,
            'response_status' => $status,
            'ip_address'      => $request->ip(),
            'user_agent'      => $request->userAgent(),
            'session_id'      => session()->getId(),
            'risk_level'      => $this->assessRiskLevel($request, $response, ['type' => null, 'id' => null]),
            'metadata'        => $metadata,
        ]);
    }

    /** @return array<string, mixed> */
    private function safeExportParameters(Request $request): array
    {
        $safe = [];
        if (in_array($request->query('locale'), ['fa', 'en'], true)) {
            $safe['locale'] = $request->query('locale');
        }
        $filter = $request->query('filter', []);
        if (is_array($filter)) {
            $allowed = ['civil_id_type', 'wallet_status', 'date_of_birth_from', 'date_of_birth_to'];
            foreach ($allowed as $key) {
                $value  = $filter[$key] ?? null;
                $isSafe = match ($key) {
                    'civil_id_type'                          => is_string($value) && in_array($value, array_column(CivilIdTypeEnum::cases(), 'value'), true),
                    'wallet_status'                          => is_string($value) && in_array($value, array_column(WalletStatusEnum::cases(), 'value'), true),
                    'date_of_birth_from', 'date_of_birth_to' => $this->isSafeDateFilter($value),
                    default                                  => false,
                };
                if ($isSafe) {
                    $safe['filter'][$key] = $value;
                }
            }
        }
        $sort = $request->query('sort');
        if (is_string($sort) && preg_match('/^-?(first_name|last_name|email|phone|civil_id|civil_id_type|date_of_birth)(,-?(first_name|last_name|email|phone|civil_id|civil_id_type|date_of_birth))*$/', $sort)) {
            $safe['sort'] = $sort;
        }

        return $safe;
    }

    private function isSafeDateFilter(mixed $value): bool
    {
        if (! is_string($value) || ! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $matches)) {
            return false;
        }

        return checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1]);
    }

    private function spreadsheetOutcome(string $operation, int $status, bool $approvedBeforeRequest): string
    {
        if ($status >= 500) {
            return 'server_failure';
        }
        if ($status === 403) {
            return 'forbidden';
        }
        if ($status === 404 || $status === 410) {
            return 'not_found';
        }
        if ($status >= 400) {
            return 'validation_failure';
        }
        if ($operation === 'import_preview') {
            return 'preview_succeeded_no_changes';
        }
        if ($operation === 'import_approve') {
            return $approvedBeforeRequest ? 'approval_replay' : 'approval_succeeded';
        }

        return $operation === 'export_create' ? 'export_generated' : 'export_downloaded';
    }

    /**
     * Determine if the request should be skipped from logging.
     */
    private function shouldSkipLogging(Request $request): bool
    {
        $skipRoutes = [
            // Skip index/list endpoints to avoid noise
            '*.index',
            // Skip select options
            'admin.select-option.*',
            // Skip health checks or monitoring
            'admin.health',
            'admin.status',
        ];

        $routeName = $request->route()?->getName();

        if (! $routeName) {
            return true;
        }

        foreach ($skipRoutes as $pattern) {
            if (fnmatch($pattern, $routeName)) {
                return true;
            }
        }

        // Only log state-changing operations by default (POST, PUT, DELETE)
        $loggedMethods = ['POST', 'PUT', 'PATCH', 'DELETE'];

        return ! in_array($request->method(), $loggedMethods);
    }

    /**
     * Log the admin action to the database.
     */
    private function logAdminAction(Request $request, Response $response, float $executionTime, ?int $adminId): void
    {
        $routeName    = $request->route()?->getName() ?? 'unknown';
        $resourceInfo = $this->extractResourceInfo($request);
        $riskLevel    = $this->assessRiskLevel($request, $response, $resourceInfo);

        AdminActionLog::create([
            'admin_id'        => $adminId,
            'action_type'     => $this->determineActionType($request->method(), $routeName),
            'resource_type'   => $resourceInfo['type'],
            'resource_id'     => $resourceInfo['id'],
            'route_name'      => $routeName,
            'http_method'     => $request->method(),
            'request_data'    => $this->sanitizeRequestData($request->all()),
            'response_status' => $response->getStatusCode(),
            'ip_address'      => $request->ip(),
            'user_agent'      => $request->userAgent(),
            'session_id'      => session()->getId(),
            'risk_level'      => $riskLevel,
            'metadata'        => [
                'execution_time_ms' => $executionTime,
                'memory_usage'      => memory_get_usage(true),
                'timestamp'         => now()->toISOString(),
                'request_size'      => mb_strlen($request->getContent()),
                'response_size'     => $response instanceof \Illuminate\Http\JsonResponse
                    ? mb_strlen($response->getContent())
                    : 0,
            ],
        ]);
    }

    /**
     * Extract resource information from the request.
     */
    /**
     * @return array<string, mixed>
     */
    private function extractResourceInfo(Request $request): array
    {
        $route      = $request->route();
        $parameters = $route ? $route->parameters() : [];

        // Preferred: model-bound parameters (implicit binding) — derive generically
        // so every route param (role, vendor, review, walletCampaign, ...) is covered.
        foreach ($parameters as $resource) {
            if ($resource instanceof Model) {
                return [
                    'type' => $resource::class,
                    'id'   => $resource->getKey(),
                ];
            }
        }

        // Fallback: scalar parameters mapped explicitly (routes without implicit binding)
        $resourceMappings = [
            'user'              => 'App\\Models\\User',
            'wallet'            => 'App\\Models\\Wallet',
            'walletCampaign'    => 'App\\Models\\WalletCampaign',
            'staff'             => 'App\\Models\\Staff',
            'category'          => 'App\\Models\\Category',
            'course'            => 'App\\Models\\Course',
            'teacher'           => 'App\\Models\\Teacher',
            'term'              => 'App\\Models\\Term',
            'seminar'           => 'App\\Models\\Seminar',
            'discountPromotion' => 'App\\Models\\DiscountPromotion',
        ];

        foreach ($resourceMappings as $paramName => $modelClass) {
            if (isset($parameters[$paramName])) {
                return [
                    'type' => $modelClass,
                    'id'   => $parameters[$paramName],
                ];
            }
        }

        return ['type' => null, 'id' => null];
    }

    /**
     * Determine action type based on HTTP method and route.
     */
    private function determineActionType(string $method, string $routeName): string
    {
        // Special wallet actions
        if (str_contains($routeName, 'deposit')) {
            return 'deposit';
        }
        if (str_contains($routeName, 'withdraw')) {
            return 'withdrawal';
        }
        if (str_contains($routeName, 'adjust')) {
            return 'adjustment';
        }
        if (str_contains($routeName, 'allocate') || str_contains($routeName, 'trigger')) {
            return 'allocation';
        }

        // Standard CRUD operations
        return match ($method) {
            'POST'         => str_contains($routeName, 'bulk') ? 'bulk_create' : 'create',
            'PUT', 'PATCH' => 'update',
            'DELETE'       => 'delete',
            'GET'          => 'view',
            default        => mb_strtolower($method),
        };
    }

    /**
     * Assess risk level of the action.
     */
    /**
     * @param  array<string, mixed>  $resourceInfo
     */
    private function assessRiskLevel(Request $request, Response $response, array $resourceInfo): string
    {
        // High risk conditions
        if ($response->getStatusCode() >= 500) {
            return 'high'; // Server errors
        }

        if ($request->method() === 'DELETE') {
            return 'high'; // All deletions are high risk
        }

        if ($this->isWalletAction($request->route()?->getName() ?? '')) {
            $amount = $this->extractWalletAmount($request->all());

            if ($amount > 10000000) { // > 1M Toman
                return 'high';
            }
            if ($amount > 1000000) { // > 100K Toman
                return 'medium';
            }
        }

        if (str_contains($request->route()?->getName() ?? '', 'bulk')) {
            return 'medium'; // Bulk operations
        }

        if ($this->isOutsideBusinessHours()) {
            return 'medium'; // Actions outside business hours
        }

        return 'low';
    }

    /**
     * Check if this is a wallet-related action.
     */
    private function isWalletAction(string $routeName): bool
    {
        return str_contains($routeName, 'wallet') || str_contains($routeName, 'deposit') || str_contains($routeName, 'withdraw') || str_contains($routeName, 'adjust');
    }

    /**
     * Extract wallet amount from request data.
     */
    /**
     * @param  array<string, mixed>  $requestData
     */
    private function extractWalletAmount(array $requestData): int
    {
        return (int) ($requestData['amount'] ?? 0);
    }

    /**
     * Check if current time is outside business hours.
     */
    private function isOutsideBusinessHours(): bool
    {
        $hour = now()->hour;

        return $hour < 7 || $hour > 22; // Outside 7 AM - 10 PM
    }

    /**
     * Sanitize request data to remove sensitive information.
     */
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function sanitizeRequestData(array $data): array
    {
        $sanitized = $this->sanitizeRecursive($data);

        // Limit data size to prevent huge logs
        $jsonData = json_encode($sanitized);
        if ($jsonData !== false && mb_strlen($jsonData) > 10000) { // 10KB limit
            return ['_large_request' => 'Request data too large, truncated'];
        }

        return $sanitized;
    }

    /**
     * Recursively redact sensitive keys at any depth so nested secrets
     * (e.g. user.password, credentials.token) never reach the audit log.
     */
    private function sanitizeRecursive(mixed $value, string $key = ''): mixed
    {
        $sensitiveFields = [
            'password',
            'password_confirmation',
            'current_password',
            'new_password',
            'token',
            'api_key',
            'secret',
        ];

        if (in_array($key, $sensitiveFields, true)) {
            return '[REDACTED]';
        }

        if ($value instanceof UploadedFile) {
            return sprintf('[FILE: %s]', $value->getClientOriginalName());
        }

        if (is_array($value)) {
            foreach ($value as $childKey => $childValue) {
                $value[$childKey] = $this->sanitizeRecursive($childValue, (string) $childKey);
            }
        }

        return $value;
    }
}
