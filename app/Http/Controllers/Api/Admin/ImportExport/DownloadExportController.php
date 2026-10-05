<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\ImportExport;

use App\Actions\Admin\ImportExport\DownloadExportAction;
use App\Http\Controllers\Controller;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * @group Admin - Export
 *
 * @authenticated Staff
 */
final class DownloadExportController extends Controller
{
    /**
     * Download a privately stored export using its unexpired signed reference.
     *
     * @urlParam resource string required Registered export resource. Enum: `users`. Example: users
     * @urlParam artifact string required Export UUID from the download reference.
     *
     * @queryParam expires integer required Expiry timestamp supplied in the download reference.
     * @queryParam signature string required Signature supplied in the download reference.
     *
     * @response 200 <<binary>> file,
     *
     * @responseFile 403 resources/responses/403.json
     * @responseFile 404 resources/responses/404.json
     */
    public function __invoke(string $resource, string $artifact, DownloadExportAction $action): BinaryFileResponse
    {
        return $action->handle($resource, $artifact);
    }
}
