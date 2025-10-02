<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Audit;

use App\Contracts\ApiResponseInterface;
use App\Data\Admin\Audit\AdminAuditLogData;
use App\Data\Admin\Audit\AdminAuditLogListData;
use App\Http\Controllers\Controller;
use App\Models\AdminActionLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @group Admin - Audit Logs
 *
 * @authenticated
 *
 * APIs for managing and viewing administrative audit logs.
 */
final class AdminAuditLogController extends Controller
{
    /**
     * Display a listing of admin audit logs.
     *
     * Returns paginated list of administrative actions performed by staff members.
     * Includes filtering by admin, action type, resource type, risk level, date range, and more.
     *
     * @queryParam filter[admin_id] int Filter by admin staff ID. Example: 1
     * @queryParam filter[action_types] string[] Filter by action types. Example: ["create", "update", "delete"]
     * @queryParam filter[resource_types] string[] Filter by resource types. Example: ["User", "Wallet"]
     * @queryParam filter[risk_levels] string[] Filter by risk levels. Example: ["high", "medium"]
     * @queryParam filter[date_from] string Filter by date from (Y-m-d H:i:s). Example: 2025-09-01 00:00:00
     * @queryParam filter[date_to] string Filter by date to (Y-m-d H:i:s). Example: 2025-09-30 23:59:59
     * @queryParam filter[ip_address] string Filter by IP address. Example: 192.168.1.1
     * @queryParam filter[http_methods] string[] Filter by HTTP methods. Example: ["POST", "DELETE"]
     * @queryParam filter[response_status] int Filter by response status code. Example: 200
     * @queryParam filter[route_name] string Filter by route name (partial match). Example: wallet
     * @queryParam filter[search] string Search across route name, action type, and admin details. Example: deposit
     * @queryParam sort string Sort by a field. Allowed values: created_at. Prefix with '-' for descending order (e.g.,
     *             -created_at).
     * @queryParam per_page integer Number of results per page. Example: 15
     */
    public function index(Request $request): ApiResponseInterface
    {
        Gate::authorize('view-any', AdminActionLog::class);

        $logs = QueryBuilder::for(AdminActionLog::class)
            ->with(['admin'])
            ->allowedFilters([
                AllowedFilter::exact('admin_id'),
                AllowedFilter::exact('action_type'),
                AllowedFilter::exact('resource_type'),
                AllowedFilter::exact('risk_level'),
                AllowedFilter::exact('http_method'),
                AllowedFilter::exact('response_status'),
                AllowedFilter::partial('route_name'),
                AllowedFilter::exact('ip_address'),
                AllowedFilter::callback('date_from', function ($query, $value): void {
                    $query->whereDate('created_at', '>=', $value);
                }),
                AllowedFilter::callback('date_to', function ($query, $value): void {
                    $query->whereDate('created_at', '<=', $value);
                }),
                AllowedFilter::callback('search', function ($query, $value): void {
                    $query->where(function ($q) use ($value): void {
                        $q->whereLike('route_name', "%{$value}%")
                            ->orWhereHas('admin', function ($adminQuery) use ($value): void {
                                $adminQuery->whereLike('name', "%{$value}%");
                            });
                    });
                }),
            ])
            ->allowedSorts([
                'created_at',
                'admin_id',
                'action_type',
                'risk_level',
                'response_status',
            ])
            ->defaultSort('-created_at')
            ->paginate($request->integer('per_page', 15));

        return response()->success(
            data: AdminAuditLogListData::collect($logs),
            message: __('messages.audit.admin_actions_loaded_successfully')
        );
    }

    /**
     * Display detailed audit log entry.
     *
     * Shows complete details of a specific administrative action including
     * Shows complete details of a specific administrative action including request data, metadata, and resource snapshot.
     *
     * @responseFile 200 responses/admin-audit-log/show.json
     *
     **/
    public function show(AdminActionLog $adminActionLog): ApiResponseInterface
    {
        Gate::authorize('view', $adminActionLog);

        $adminActionLog->load(['admin', 'resource']);

        return response()->success(
            data: AdminAuditLogData::from($adminActionLog),
            message: __('messages.audit.audit_log_loaded_successfully')
        );
    }
}
