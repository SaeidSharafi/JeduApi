<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\FileManagement;

use App\Contracts\ApiResponseInterface;
use App\Data\Admin\MediaData;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Plank\Mediable\Facades\MediaUploader;
use Plank\Mediable\Jobs\CreateImageVariants;

final class UploadMediaController extends Controller
{
    /**
     * Upload a media file and return its info.
     *
     *
     *
     * @authenticated
     *
     * @group Admin - Media Management
     *
     * @bodyParam file file required The media file to upload
     * @bodyParam alt string optional The alt text for the media file
     *
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
     *    metadata: []
     *  }
     */
    public function __invoke(Request $request): ApiResponseInterface
    {
        $request->validate([
            'file' => 'required|file|max:10240',
            'alt'  => 'nullable|string|max:255',
        ]);

        /** @var UploadedFile $file */
        $file = $request->file('file');
        $alt  = (string) $request->string('alt');

        $media = MediaUploader::fromSource($file)
            ->toDisk(config('mediable.default_disk', 'public'))
            ->withAltAttribute($alt)
            ->onDuplicateIncrement()
            ->upload();

        CreateImageVariants::dispatch($media, 'thumb');

        return response()->created(MediaData::fromModel($media), message: __('messages.success'));
    }
}
