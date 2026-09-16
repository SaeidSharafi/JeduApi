<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\ImportExport;

use App\Actions\Admin\ImportExport\BuildImportTemplateAction;
use App\Http\Controllers\Controller;
use App\Models\ImportRun;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * @group Admin - Import
 *
 * Download the official spreadsheet import template of a resource.
 *
 * @authenticated
 */
final class DownloadImportTemplateController extends Controller
{
    /**
     * Download the import template of a spreadsheet resource.
     *
     * @urlParam resource string required The registered import resource. Enum: `users`. Example: users
     *
     * @response 200 <<binary>> file,
     *
     * @responseFile 403 resources/responses/403.json
     * @responseFile 404 resources/responses/404.json
     */
    public function __invoke(string $resource, BuildImportTemplateAction $action): BinaryFileResponse
    {
        Gate::authorize('downloadTemplate', ImportRun::class);

        return $action->handle($resource);
    }
}
