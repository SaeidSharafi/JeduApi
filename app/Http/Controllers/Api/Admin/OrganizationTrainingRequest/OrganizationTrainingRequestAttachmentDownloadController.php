<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\OrganizationTrainingRequest;

use App\Contracts\ApiResponseInterface;
use App\Http\Controllers\Controller;
use App\Models\OrganizationTrainingRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * @group Admin - Organization Training Requests
 *
 * @authenticated
 */
final class OrganizationTrainingRequestAttachmentDownloadController extends Controller
{
    /**
     * Download the attachment of an Organization Training Request.
     *
     * The request must have an attachment and its stored media must still exist on its disk.
     *
     * @response 200 <<binary>> file
     *
     * @responseFile 403 resources/responses/403.json
     * @responseFile 404 resources/responses/404.json
     */
    public function __invoke(
        OrganizationTrainingRequest $organizationTrainingRequest,
    ): ApiResponseInterface|StreamedResponse {
        Gate::authorize('view', $organizationTrainingRequest);

        $file = $organizationTrainingRequest->firstMedia('attachment');

        if ($file === null) {
            return apiResponse()->notFound(__('messages.file_not_found'));
        }

        $disk = Storage::disk($file->disk);
        $path = $file->getDiskPath();

        if (! $disk->exists($path)) {
            return apiResponse()->notFound(__('messages.file.storage_not_found'));
        }

        return $disk->download($path, "{$file->filename}.{$file->extension}", [
            'Content-Type' => $file->mime_type,
        ]);
    }
}
