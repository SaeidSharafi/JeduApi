<?php

declare(strict_types=1);

describe('ThumbnailData', function () {
    it('can be created from a Media model', function () {
        Illuminate\Support\Facades\DB::table('media')
            ->insert([
                'id'             => 1,
                'filename'       => 'example.jpg',
                'size'           => 123456,
                'mime_type'      => 'image/jpeg',
                'extension'      => 'jpg',
                'alt'            => 'An example image',
                'created_at'     => now(),
                'updated_at'     => now(),
                'disk'           => 'local',
                'directory'      => 'thumbnails',
                'aggregate_type' => 'image',
            ]);
        $media         = Plank\Mediable\Media::find(1);
        $thumbnailData = App\Data\Admin\ThumbnailData::fromModel($media);

        expect($thumbnailData->id)->toBe(1);
        expect($thumbnailData->url)->toBe($media->getUrl());
        expect($thumbnailData->size)->toBe(123456);
        expect($thumbnailData->file_name)->toBe('example.jpg');
        expect($thumbnailData->alt)->toBe('An example image');
        expect($thumbnailData->mime_type)->toBe('image/jpeg');
        expect($thumbnailData->extension)->toBe('jpg');
    });
});
