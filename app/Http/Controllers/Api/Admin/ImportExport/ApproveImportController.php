<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\ImportExport;

use App\Actions\Admin\ImportExport\ApproveImportRunAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Admin\ImportExport\ImportApprovalRequestData;
use App\Http\Controllers\Controller;
use App\Models\ImportRun;
use Illuminate\Support\Facades\Gate;

/**
 * @group Admin - Import
 *
 * Approve a validated import run and apply its valid rows.
 *
 * @authenticated
 */
final class ApproveImportController extends Controller
{
    /**
     * Approve an import run.
     *
     * Approval is idempotent: a run is approved once and a repeated request returns the original
     * result without applying the rows again. When the preview contains invalid rows, the request
     * must accept importing the valid rows and ignoring the invalid ones. The response reports the
     * local commit counts and queued provider work only; poll the run-status endpoint for provider
     * outcomes.
     *
     * @urlParam resource string required The registered import resource. Enum: `users`. Example: users
     * @urlParam run string required The import run UUID returned by the preview. Example: 01932f7c-6f2a-7c31-9d5a-6f1c2b8e4a10
     *
     * @responseFile 200 resources/responses/admin/import/approve.json
     * @responseFile 403 resources/responses/403.json
     * @responseFile 404 resources/responses/404.json
     * @responseFile 422 resources/responses/422.json
     */
    public function __invoke(
        string $resource,
        string $run,
        ImportApprovalRequestData $data,
        ApproveImportRunAction $action,
    ): ApiResponseInterface {
        Gate::authorize('approve', ImportRun::class);

        return apiResponse()->success(
            $action->handle($resource, $run, $data),
            __('messages.import.approved'),
        );
    }
}
