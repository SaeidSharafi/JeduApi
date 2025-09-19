<?php

use App\Enums\MediaTagEnum;

describe('GetThumnailUrlAction', function () {
    beforeEach(function () {
        $this->action = new \App\Actions\Admin\GetThumnailUrlAction();
    });

    it('returns null when media array is empty', function () {
        $result = $this->action->handle([]);
        expect($result)->toBeNull();
    });

    it('returns null when COVER tag is missing', function () {
        $media = [
            'OTHER_TAG' => [1, 2, 3],
        ];
        $result = $this->action->handle($media);
        expect($result)->toBeNull();
    });

    it('returns null when COVER tag is empty', function () {
        $media = [
            MediaTagEnum::COVER->value => [],
        ];
        $result = $this->action->handle($media);
        expect($result)->toBeNull();
    });

    it('returns null when media ID does not exist', function () {
        $media = [
            MediaTagEnum::COVER->value => [9999], // Assuming 9999 does not exist
        ];
        $result = $this->action->handle($media);
        expect($result)->toBeNull();
    });

    it('returns the correct URL when media ID exists', function () {
        // Create a media item for testing
        Storage::fake('public');
        $mediaItem = MediaUploader::fromSource(Illuminate\Http\UploadedFile::fake()->image('cover.jpg'))
            ->toDisk('public')
            ->upload();
        $media = [
            MediaTagEnum::COVER->value => [$mediaItem->id],
        ];
        $result = $this->action->handle($media);
        expect($result)->toBe($mediaItem->getUrl());

        // Clean up
        $mediaItem->delete();
    });
});
