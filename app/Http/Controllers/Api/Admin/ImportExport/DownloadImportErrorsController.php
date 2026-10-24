<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\ImportExport;

use App\Actions\Admin\ImportExport\DownloadImportErrorsAction;
use App\Http\Controllers\Controller;
use App\Models\ImportRun;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * @group Admin - Import
 *
 * Download the validation and provider error report of an import run.
 *
 * @authenticated
 */
final class DownloadImportErrorsController extends Controller
{
    /**
     * Download the error report of an import run as a private XLSX file.
     *
     * The workbook contains `row_number`, `field`, `code`, `message` and `provider` columns with
     * value-free localized messages. Validation errors are available immediately after the preview;
     * final provider failures appear once the run leaves `processing`.
     *
     * @urlParam resource string required The registered import resource. Enum: `users`. Example: users
     * @urlParam run string required The import run UUID returned by the preview. Example: 01932f7c-6f2a-7c31-9d5a-6f1c2b8e4a10
     *
     * @response 200 <<binary>> file
     *
     * @responseFile 403 resources/responses/403.json
     * @responseFile 404 resources/responses/404.json
     */
    public function __invoke(string $resource, string $run, DownloadImportErrorsAction $action): StreamedResponse
    {
        Gate::authorize('viewResults', ImportRun::class);

        return $action->handle($resource, $run);
    }
}
