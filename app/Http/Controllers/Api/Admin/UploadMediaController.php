<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Data\MediaData;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Plank\Mediable\Facades\MediaUploader;

class UploadMediaController extends Controller
{
    /**
     * Upload a media file and return its info.
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     *
     * @authenticated
     * @group Media Management
     * @response 201 {
     *    "message": "Media file uploaded successfully",
     *    "data": {
     *       "id": 1,
     *       "url": "https://example.com/media/1",
     *       "size": 123456,
     *       "file_name": "example.jpg",
     *       "alt": "Example Image",
     *       "mime_type": "image/jpeg",
     *       "extension": "jpg",
     *       "tag": null
     *    },
     *    metdata: []
     *  }
     */
    public function __invoke(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:10240', // 10MB max
            'alt' => 'nullable|string|max:255',
        ]);

        /** @var UploadedFile $file */
        $file = $request->file('file');
        $alt = (string)$request->string('alt');
        $media = MediaUploader::fromSource($file)
            ->toDisk(config('mediable.default_disk', 'public'))
            ->withAltAttribute($alt)
            ->onDuplicateIncrement()
            ->upload();


        return response()->created(MediaData::fromModel($media), 'Media file uploaded successfully');
    }
}
