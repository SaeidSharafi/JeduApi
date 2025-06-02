<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Contracts\ApiResponseInterface;
use App\Data\MediaData;
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
     * @response 201 {
     *    "message": "Private file uploaded successfully",
     *    "data": {
     *       "id": 1,
     *       "url": "https://example.com/api/v1/admin/private-file/1/download",
     *       "size": 123456,
     *       "file_name": "example.jpg",
     *       "alt": "Example Image",
     *       "mime_type": "image/jpeg",
     *       "extension": "jpg",
     *       "tag": null
     *    },
     *    metadata: []
     *  }
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

        return response()->created(MediaData::fromModel($file), 'Private file uploaded successfully');
    }
}
