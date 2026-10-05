<?php

declare(strict_types=1);

namespace App\Services\ImportExport;

use App\Enums\User\CivilIdTypeEnum;
use App\Enums\Wallet\WalletStatusEnum;
use App\Models\AdminActionLog;
use App\Models\ImportExportArtifact;
use App\Models\ImportRun;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/** Persists allowlisted audit details for the four spreadsheet operations. */
final class SpreadsheetAuditLogger
{
    public function operationFor(Request $request): ?string
    {
        return match ($request->route()?->getName()) {
            'api.v1.admin.imports.preview'  => 'import_preview',
            'api.v1.admin.imports.approve'  => 'import_approve',
            'api.v1.admin.exports.create'   => 'export_create',
            'api.v1.admin.exports.download' => 'export_download',
            default                         => null,
        };
    }

    public function record(Request $request, Response $response, SpreadsheetAuditContext $context): void
    {
        $operation = $context->operation();
        $adminId   = $context->adminId();
        if ($operation === null || $adminId === null || $context->auditAttempted()) {
            return;
        }

        $context->markAuditAttempted();
        try {
            $this->persist($request, $response, $context, $operation, $adminId);
        } catch (Throwable $exception) {
            Log::error('Admin spreadsheet audit persistence failed', [
                'failure_code' => class_basename($exception),
                'route'        => $request->route()?->getName(),
                'admin_id'     => $adminId,
            ]);
        }
    }

    private function persist(Request $request, Response $response, SpreadsheetAuditContext $context, string $operation, int $adminId): void
    {
        $resource     = (string) $request->route('resource');
        $routeName    = (string) $request->route()?->getName();
        $payload      = $response instanceof JsonResponse ? $response->getData(true) : [];
        $data         = is_array($payload) && is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $status       = $response->getStatusCode();
        $runUuid      = $operation === 'import_preview' ? ($data['run_id'] ?? null) : ($operation === 'import_approve' ? $request->route('run') : null);
        $runUuid      = $this->isUuid($runUuid) ? $runUuid : null;
        $artifactUuid = $operation === 'export_download' ? $request->route('artifact') : null;
        if ($operation === 'export_create' && is_string($data['download_url'] ?? null)) {
            $path         = parse_url($data['download_url'], PHP_URL_PATH);
            $artifactUuid = is_string($path) && preg_match('~/export/([0-9a-f-]{36})$~i', $path, $matches) ? $matches[1] : null;
        }
        $artifactUuid = $this->isUuid($artifactUuid) ? $artifactUuid : null;

        $approvalRun = $operation === 'import_approve' && $status >= 400 && $runUuid !== null
            ? ImportRun::query()->where('uuid', $runUuid)->where('resource', $resource)->first()
            : null;
        $outcome = $this->outcome($operation, $status, $context->approvedBeforeRequest());
        if ($operation === 'import_approve' && $status === 422 && $approvalRun?->artifacts_expires_at?->lessThanOrEqualTo(now()) === true) {
            $outcome = 'expired';
        } elseif ($operation === 'export_download' && $status === 403 && is_numeric($request->query('expires')) && (int) $request->query('expires') <= now()->timestamp) {
            $outcome = 'expired';
        } elseif ($operation === 'export_download' && $status === 404 && $artifactUuid !== null) {
            $expiresAt = ImportExportArtifact::query()
                ->where('artifact_uuid', $artifactUuid)
                ->where('resource', $resource)
                ->value('expires_at');
            if ($expiresAt !== null && Carbon::parse($expiresAt)->lessThanOrEqualTo(now())) {
                $outcome = 'expired';
            }
        }

        $metadata = [
            'operation'         => $operation,
            'resource'          => $resource,
            'request_outcome'   => $outcome,
            'execution_time_ms' => $context->startedAt() === null ? null : round((microtime(true) - $context->startedAt()) * 1000, 2),
            'memory_usage'      => memory_get_usage(true),
            'timestamp'         => now()->toISOString(),
            'request_size'      => mb_strlen($request->getContent()),
            'response_size'     => $response instanceof JsonResponse ? mb_strlen($response->getContent()) : 0,
        ];
        if ($operation === 'import_preview') {
            $this->addSafeIdentityKey($request, $metadata);
        }
        if ($status >= 400) {
            $metadata['failure_code'] = match (true) {
                $status >= 500                      => 'server_error',
                $outcome === 'expired'              => 'expired',
                $status  === 403                    => 'forbidden',
                $status  === 404 || $status === 410 => 'not_found',
                default                             => 'validation_error',
            };
        }
        if ($operation === 'export_create') {
            $metadata += $this->safeExportParameters($request);
        }

        if ($operation === 'import_preview' && $runUuid !== null) {
            $metadata['run_uuid'] = $runUuid;
            $metadata['filename'] = "{$resource}-import-{$runUuid}.xlsx";
            $this->addSafeIdentityKey($request, $metadata);
            if (is_array($data['summary'] ?? null)) {
                $summary = $data['summary'];
                foreach (['total_rows' => 'rows_total', 'valid_rows' => 'rows_valid', 'invalid_rows' => 'rows_invalid'] as $key => $metadataKey) {
                    if (is_int($summary[$key] ?? null)) {
                        $metadata[$metadataKey] = $summary[$key];
                    }
                }
            }
            if (is_string($data['status'] ?? null)) {
                $metadata['run_status'] = $data['status'];
            }
        } elseif ($operation === 'import_approve' && $runUuid !== null) {
            $metadata['run_uuid'] = $runUuid;
            $metadata['filename'] = "{$resource}-import-{$runUuid}.xlsx";
            foreach (['identity_key', 'status'] as $key) {
                if (is_string($data[$key] ?? null)) {
                    $metadata[$key === 'status' ? 'run_status' : $key] = $data[$key];
                }
            }
            if (is_array($data['summary'] ?? null)) {
                foreach (['created_count', 'updated_count', 'provider_queued_count'] as $key) {
                    if (is_int($data['summary'][$key] ?? null)) {
                        $metadata[$key] = $data['summary'][$key];
                    }
                }
            }
            if ($status >= 400 && $approvalRun !== null) {
                $metadata['run_status'] = $approvalRun->status->value;
                if (is_string($approvalRun->identity_key)) {
                    $metadata['identity_key'] = $approvalRun->identity_key;
                }
            }
            if ($status < 400 && $context->approvedBeforeRequest() !== null) {
                $metadata['approval_replay'] = $context->approvedBeforeRequest();
            }
        }

        if (in_array($operation, ['export_create', 'export_download'], true) && $artifactUuid !== null) {
            $metadata['artifact_uuid'] = $artifactUuid;
            $metadata['filename']      = "{$resource}-export.xlsx";
        }
        if ($operation === 'export_create' && $status < 400 && $context->exportedRows() !== null) {
            $metadata['exported_rows'] = $context->exportedRows();
        }

        $requestData = ['operation' => $operation, 'resource' => $resource];
        if ($operation === 'import_preview') {
            $this->addSafeIdentityKey($request, $requestData);
        } elseif ($operation === 'export_create') {
            $requestData += $this->safeExportParameters($request);
        }

        AdminActionLog::query()->create([
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
            'risk_level'      => $this->riskLevel($status),
            'metadata'        => array_filter($metadata, static fn (mixed $value): bool => $value !== null),
        ]);
    }

    /** @param array<string, mixed> $target */
    private function addSafeIdentityKey(Request $request, array &$target): void
    {
        $identityKey = $request->query('identity_key');
        if (in_array($identityKey, ['phone', 'email'], true)) {
            $target['identity_key'] = $identityKey;
        }
    }

    /** @return array<string, mixed> */
    private function safeExportParameters(Request $request): array
    {
        $safe = [];
        if (in_array($request->query('locale'), ['fa', 'en'], true)) {
            $safe['locale'] = $request->query('locale');
        }
        $filters = $request->query('filter', []);
        if (is_array($filters)) {
            foreach (['civil_id_type', 'wallet_status', 'date_of_birth_from', 'date_of_birth_to'] as $key) {
                $value  = $filters[$key] ?? null;
                $isSafe = match ($key) {
                    'civil_id_type'                          => is_string($value) && in_array($value, array_column(CivilIdTypeEnum::cases(), 'value'), true),
                    'wallet_status'                          => is_string($value) && in_array($value, array_column(WalletStatusEnum::cases(), 'value'), true),
                    'date_of_birth_from', 'date_of_birth_to' => $this->isSafeDateFilter($value),
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

    private function isUuid(mixed $value): bool
    {
        return is_string($value) && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value) === 1;
    }

    private function outcome(string $operation, int $status, ?bool $approvedBeforeRequest): string
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
            return $approvedBeforeRequest === true ? 'approval_replay' : 'approval_succeeded';
        }

        return $operation === 'export_create' ? 'export_generated' : 'export_downloaded';
    }

    private function riskLevel(int $status): string
    {
        if ($status >= 500) {
            return 'high';
        }

        $hour = now()->hour;

        return $hour < 7 || $hour > 22 ? 'medium' : 'low';
    }
}
