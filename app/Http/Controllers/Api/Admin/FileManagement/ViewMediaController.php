<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\FileManagement;

use App\Contracts\ApiResponseInterface;
use App\Data\Admin\MediaData;
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
     * @group Admin - Media Management
     *
     * @responseFile 200 resources/responses/admin/media/view.json
     */
    public function __invoke(Request $request, Media $media): ApiResponseInterface
    {
        $media->load('variants');

        return response()->success(MediaData::fromModel($media), message: __('messages.media_retrieved'));
    }
}
