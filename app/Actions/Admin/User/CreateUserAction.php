<?php

declare(strict_types=1);

namespace App\Actions\Admin\User;

use App\Data\Admin\User\UserCreateData;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Plank\Mediable\Media;

final readonly class CreateUserAction
{
    /**
     * Execute the action.
     */
    public function handle(UserCreateData $data): User
    {
        return DB::transaction(function () use ($data): User {
            $avatarMedia = null;
            $userData    = $data->except('media')->toArray();
            if ($mediaId = data_get($data->media, 'avatar')) {
                $avatarMedia            = Media::find($mediaId);
                $userData['avatar_url'] = $avatarMedia?->getUrl();
            }
            $user = User::create($userData)->fresh();
            if ($avatarMedia) {
                $user->attachMedia($avatarMedia, 'avatar');
            }

            return $user;
        });
    }
}
