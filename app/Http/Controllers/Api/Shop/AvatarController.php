<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop;

use App\Contracts\ApiResponseInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Plank\Mediable\Facades\MediaUploader;
use Plank\Mediable\Jobs\CreateImageVariants;
use Plank\Mediable\Media;

/**
 * @group Shop - Profile
 *
 * APIs for managing customer profile.
 *
 * @authenticated user
 */
final class AvatarController extends Controller
{
    /**
     * Update the users avatar.
     *
     * @response 200 {
     *     "message": "Updated successfully.",
     *     "data": {
     *         "avatar_url": "https://example.com/path/to/avatar.jpg"
     *     }
     *     "metadata": []
     * }
     *
     */
    public function update(Request $request): ApiResponseInterface
    {
        $request->validate([
            'file' => 'required|file|max:10240',
        ]);

        $media = null;
        DB::transaction(function () use ($request, &$media) {
            /** @var UploadedFile $file */
            $file = $request->file('file');

            $media = MediaUploader::fromSource($file)
                ->toDisk(config('mediable.default_disk', 'public'))
                ->onDuplicateIncrement()
                ->upload();

            if ($media->aggregate_type === Media::TYPE_IMAGE) {
                CreateImageVariants::dispatch($media, 'thumb');
            }
            $user = auth('user')?->user();
            $user->syncMedia($media, 'avatar');
        });

        return apiResponse()->updated(
            [
                'avatar_url' => $media?->getUrl(),
            ]
        );
    }

    /**
     * Delete the users avatar.
     *
     * @response 204
     *
     */
    public function destroy(Request $request): JsonResponse
    {

        DB::transaction(function () use ($request, &$media) {

            $user = auth('user')?->user();
            $user->load('media');
            $avatars = $user->getMediaMatchAll(['avatar']);
            foreach ($avatars as $avatar) {
                $avatar->delete();
            }
            $user->avatar_url = null;
            $user->save();

        });

        return apiResponse()->noContentJson();
    }
}
