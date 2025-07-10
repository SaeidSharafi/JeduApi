<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\FileManagement;

use App\Contracts\ApiResponseInterface;
use App\Data\Admin\PrivateFileData;
use App\Http\Controllers\Controller;
use App\Models\DigitalAsset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Plank\Mediable\Media;

final class ViewPrivateFileController extends Controller
{
    /**
     * return a Private file info.
     *
     *
     *
     * @authenticated
     *
     * @group Admin - Private File Management
     *
     * @response 200 {
     *   "message": "Private file retrieved successfully",
     *   "data": {
     *      "id": 1,
     *      "url": "https://example.com/api/v1/admin/private-file/1/download",
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
    public function __invoke(Request $request, Media $file): ApiResponseInterface
    {
        Gate::authorize('view-any', DigitalAsset::class);

        return response()->success(PrivateFileData::fromModel($file), __('messages.success'));
    }
}
