<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\ImportExport;

use App\Actions\Admin\ImportExport\GetImportRunAction;
use App\Contracts\ApiResponseInterface;
use App\Http\Controllers\Controller;
use App\Models\ImportRun;
use Illuminate\Support\Facades\Gate;

/**
 * @group Admin - Import
 *
 * Poll the status, row results and provider outcomes of an import run.
 *
 * @authenticated
 */
final class ShowImportRunController extends Controller
{
    /**
     * Show an import run and its row-level results.
     *
     * Use this endpoint after approval while the run is `processing` to collect final provider
     * outcomes. When the run expires, row data, row errors and the error report reference are
     * suppressed while the run identity and counters remain available.
     *
     * @urlParam resource string required The registered import resource. Enum: `users`. Example: users
     * @urlParam run string required The import run UUID returned by the preview. Example: 01932f7c-6f2a-7c31-9d5a-6f1c2b8e4a10
     *
     * @responseFile 200 resources/responses/admin/import/show.json
     * @responseFile 403 resources/responses/403.json
     * @responseFile 404 resources/responses/404.json
     */
    public function __invoke(string $resource, string $run, GetImportRunAction $action): ApiResponseInterface
    {
        Gate::authorize('viewResults', ImportRun::class);

        return apiResponse()->success($action->handle($resource, $run));
    }
}
