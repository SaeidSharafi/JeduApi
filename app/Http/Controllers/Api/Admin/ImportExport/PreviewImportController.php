<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\ImportExport;

use App\Actions\Admin\ImportExport\CreateImportPreviewAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Admin\ImportExport\ImportPreviewRequestData;
use App\Http\Controllers\Controller;
use App\Models\ImportRun;
use Illuminate\Support\Facades\Gate;

/**
 * @group Admin - Import
 *
 * Validate an uploaded spreadsheet and return its preview envelope.
 *
 * @authenticated
 */
final class PreviewImportController extends Controller
{
    /**
     * Preview an import spreadsheet without changing any stored data.
     *
     * Validates every row synchronously, stores the immutable Import Run and returns the preview envelope.
     *
     * @urlParam resource string required The registered import resource. Enum: `users`. Example: users
     *
     * @queryParam identity_key string required The identity rows are matched on. Enum: `phone`, `email`. Example: phone
     *
     * @responseFile 200 resources/responses/admin/import/preview.json
     * @responseFile 403 resources/responses/403.json
     * @responseFile 404 resources/responses/404.json
     * @responseFile 422 resources/responses/422.json
     */
    public function __invoke(
        string $resource,
        ImportPreviewRequestData $data,
        CreateImportPreviewAction $action,
    ): ApiResponseInterface {
        Gate::authorize('preview', ImportRun::class);

        return apiResponse()->success(
            $action->handle($resource, $data, auth('staff')->user()),
            __('messages.import.preview_ready'),
        );
    }
}
