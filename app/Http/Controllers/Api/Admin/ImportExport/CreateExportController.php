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
     * Generate and privately store a filtered XLSX export.
     *
     * @urlParam resource string required Registered export resource. Enum: `users`. Example: users
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
