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
use Plank\Mediable\Media;

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
     * @responseFile 201 resources/responses/admin/media/upload.json
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

        if ($media->aggregate_type === Media::TYPE_IMAGE) {
            CreateImageVariants::dispatch($media, 'thumb');
        }

        return response()->created(MediaData::fromModel($media), message: __('messages.success'));
    }
}
