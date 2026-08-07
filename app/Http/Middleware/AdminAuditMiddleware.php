<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\AdminActionLog;
use Closure;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

final class AdminAuditMiddleware
{
    /**
     * Handle an incoming request and log admin actions.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);

        // Snapshot the actor before the request runs: endpoints that mutate
        // auth state (logout, token revocation) would otherwise resolve null/wrong.
        $adminId = auth('staff')->id();

        // Get the response
        $response = $next($request);

        // Only log if authenticated staff member
        if ($adminId === null) {
            return $response;
        }

        // Skip certain routes to avoid noise
        if ($this->shouldSkipLogging($request)) {
            return $response;
        }

        try {
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            $this->logAdminAction($request, $response, $executionTime, $adminId);
        }
        // @codeCoverageIgnoreStart
        catch (Exception $e) {
            // Log the error but don't break the request
            Log::error('AdminAuditMiddleware failed to log action', [
                'error'    => $e->getMessage(),
                'route'    => $request->route()?->getName(),
                'admin_id' => $adminId,
            ]);
        }
        // @codeCoverageIgnoreEnd

        return $response;
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
