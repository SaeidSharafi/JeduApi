<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop;

use App\Actions\User\UpdateUserAvatarAction;
use App\Contracts\ApiResponseInterface;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

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
     */
    public function update(Request $request, UpdateUserAvatarAction $updateUserAvatar): ApiResponseInterface
    {
        $request->validate([
            'file' => 'required|file|max:10240',
        ]);

        /** @var UploadedFile $file */
        $file = $request->file('file');

        /** @var User $user */
        $user = auth('user')->user();

        $media = $updateUserAvatar->handle($user, $file);

        return apiResponse()->updated(
            [
                'avatar_url' => $media->getUrl(),
            ]
        );
    }

    /**
     * Delete the users avatar.
     *
     * @response 204
     */
    public function destroy(Request $request): JsonResponse
    {

        DB::transaction(function (): void {

            $user = auth('user')->user();
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
