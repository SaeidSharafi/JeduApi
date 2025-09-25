<?php

declare(strict_types=1);

namespace App\Actions\Shop;

use Illuminate\Http\UploadedFile;
use Plank\Mediable\Facades\MediaUploader;
use Plank\Mediable\Media;

final class UploadFileAction
{
    public function handle(UploadedFile $file, bool $isPublic = true): Media
    {
        return MediaUploader::fromSource($file)
            ->toDisk($isPublic ? config('mediable.default_disk', 'public') : 'local')
            ->toDirectory('forms/attachments')
            ->onDuplicateIncrement()
            ->upload();
    }
}
