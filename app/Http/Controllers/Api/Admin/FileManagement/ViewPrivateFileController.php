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
     * @responseFile 200 resources/responses/admin/media/private-view.json
     */
    public function __invoke(Request $request, Media $file): ApiResponseInterface
    {
        Gate::authorize('view-any', DigitalAsset::class);

        return apiResponse()->success(PrivateFileData::fromModel($file), __('messages.success'));
    }
}
