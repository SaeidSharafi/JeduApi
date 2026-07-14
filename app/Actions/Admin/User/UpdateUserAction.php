<?php

declare(strict_types=1);

namespace App\Actions\Admin\User;

use App\Data\Admin\User\UserCreateData;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Plank\Mediable\Media;

final readonly class UpdateUserAction
{
    /**
     * Execute the action.
     */
    public function handle(UserCreateData $data, User $user): User
    {
        return DB::transaction(function () use ($data, $user): User {
            $userData               = $data->except('media')->toArray();
            $avatarMedia            = null;
            $userData['avatar_url'] = null;
            if ($mediaId = data_get($data->media, 'avatar')) {
                $avatarMedia            = Media::find($mediaId);
                $userData['avatar_url'] = $avatarMedia?->getUrl();
            }
            $user->update($userData);

            $user->syncMedia($avatarMedia, 'avatar');

            return $user->fresh();
        });
    }
}
