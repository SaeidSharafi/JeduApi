<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\FileManagement;

use App\Contracts\ApiResponseInterface;
use App\Data\Admin\MediaData;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Plank\Mediable\Facades\MediaUploader;

final class UploadPrivateController extends Controller
{
    /**
     * Upload a private file and return its info.
     *
     *
     *
     * @authenticated
     *
     * @group Admin - Private File Management
     *
     * @bodyParam file file required The file to upload
     * @bodyParam alt string optional The alt text for the file
     *
     * @responseFile 201 resources/responses/admin/media/private-upload.json
     */
    public function __invoke(Request $request): ApiResponseInterface
    {

        $request->validate([
            'file' => 'required|file|max:10240',
            'alt'  => 'nullable|string|max:255',
        ]);

        /** @var UploadedFile $fileUpload */
        $fileUpload = $request->file('file');
        $alt        = (string) $request->string('alt');
        $file       = MediaUploader::fromSource($fileUpload)
            ->toDisk('local')
            ->withAltAttribute($alt)
            ->onDuplicateIncrement()
            ->upload();

        return response()->created(MediaData::fromModel($file), __('messages.file_uploaded'));
    }
}
