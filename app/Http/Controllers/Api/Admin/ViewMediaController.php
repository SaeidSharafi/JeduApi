<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Contracts\ApiResponseInterface;
use App\Data\MediaData;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Plank\Mediable\Media;

final class ViewMediaController extends Controller
{
    /**
     * return a media file info.
     *
     *
     *
     * @authenticated
     *
     * @group Media Management
     *
     * @response 200 {
     *   "message": "Media file retrieved successfully",
     *   "data": {
     *      "id": 1,
     *      "url": "https://example.com/media/1",
     *      "size": 123456,
     *      "file_name": "example.jpg",
     *      "alt": "Example Image",
     *      "mime_type": "image/jpeg",
     *      "extension": "jpg",
     *      "tag": null
     *   },
     *   metadata: []
     * }
     */
    public function __invoke(Request $request, Media $media): ApiResponseInterface
    {
        return response()->success(MediaData::fromModel($media), 'Media file retrieved successfully');
    }
}
