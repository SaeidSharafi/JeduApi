<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Contracts\ApiResponseInterface;
use App\Data\MediaData;
use App\Data\PrivateFileData;
use App\Enums\PermissionEnum;
use App\Http\Controllers\Controller;
use App\Models\DigitalAsset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Plank\Mediable\Media;

final class PrivateFileDownloadController extends Controller
{
    /**
     * return the private file for download.
     *
     *
     *
     * @authenticated
     *
     * @group Private File Management
     *
     * @response 200 <<binary>> file,
     * @responseFile 404 storage/responses/404.json
     */
    public function __invoke(Request $request, Media $file): ApiResponseInterface|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        Gate::authorize('view-any',DigitalAsset::class);

        $disk = Storage::disk($file->disk);
        $path = $file->getDiskPath();

        if ( ! $disk->exists($path)) {
            return response()->notFound('File not found on storage.');
        }

        $headers = [
            'Content-Type' => $file->mime_type,
        ];

        return Storage::disk($file->disk)->download($path, "{$file->filename}.{$file->extension}", $headers);
    }
}
