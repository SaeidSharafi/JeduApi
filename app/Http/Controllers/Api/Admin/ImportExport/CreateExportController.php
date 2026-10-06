<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\ImportExport;

use App\Actions\Admin\ImportExport\CreateExportAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Admin\ImportExport\ExportRequestData;
use App\Http\Controllers\Controller;

/**
 * @group Admin - Export
 *
 * @authenticated Staff
 */
final class CreateExportController extends Controller
{
    /**
     * Generate and privately store a resource-specific XLSX export.
     *
     * Filters, sorting, columns and authorization are defined by the selected
     * resource. Use its list endpoint for supported filters and sort fields.
     * Pagination is ignored. Returns an artifact reference, not file bytes.
     * Fetch download_url unchanged with staff authentication before expires_at.
     *
     * @urlParam resource string required Registered export resource. Enum: `users`. Example: users
     *
     * @ignoreQueryParam filter.name, filter.email, filter.phone, filter.civil_id, filter.civil_id_type, filter.wallet_status, filter.date_of_birth_from, filter.date_of_birth_to
     *
     * @responseFile 200 resources/responses/admin/export/show.json
     * @responseFile 403 resources/responses/403.json
     * @responseFile 422 resources/responses/422.json
     */
    public function __invoke(string $resource, ExportRequestData $data, CreateExportAction $action): ApiResponseInterface
    {
        return apiResponse()->success($action->handle($resource, $data));
    }
}
