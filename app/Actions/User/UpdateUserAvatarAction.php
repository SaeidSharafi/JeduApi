<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Actions\Media\DispatchImageVariantsAction;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Plank\Mediable\Facades\MediaUploader;
use Plank\Mediable\Media;

/**
 * Stores an uploaded file as the user's avatar.
 *
 * Uploads the file to the media disk, queues any image variant that can be
 * generated from it, attaches the resulting media to the `avatar` tag and
 * mirrors its URL onto the user, replacing whatever was attached before.
 */
final readonly class UpdateUserAvatarAction
{
    public function __construct(private DispatchImageVariantsAction $dispatchImageVariants) {}

    public function handle(User $user, UploadedFile $file): Media
    {
        return DB::transaction(function () use ($user, $file): Media {
            $media = MediaUploader::fromSource($file)
                ->toDisk(config('mediable.default_disk', 'public'))
                ->onDuplicateIncrement()
                ->upload();

            $this->dispatchImageVariants->handle($media, 'thumb');

            $user->syncMedia($media, 'avatar');
            $user->update(['avatar_url' => $media->getUrl()]);

            return $media;
        });
    }
}
