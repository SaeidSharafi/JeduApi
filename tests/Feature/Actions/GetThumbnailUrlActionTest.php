<?php

declare(strict_types=1);

use App\Enums\MediaTagEnum;

describe('GetThumbnailUrlAction', function (): void {
    beforeEach(function (): void {
        $this->action = new App\Actions\Admin\GetThumbnailUrlAction();
    });

    it('returns null when media array is empty', function (): void {
        $result = $this->action->handle([]);
        expect($result)->toBeNull();
    });

    it('returns null when COVER tag is missing', function (): void {
        $media = [
            'OTHER_TAG' => [1, 2, 3],
        ];
        $result = $this->action->handle($media);
        expect($result)->toBeNull();
    });

    it('returns null when COVER tag is empty', function (): void {
        $media = [
            MediaTagEnum::COVER->value => [],
        ];
        $result = $this->action->handle($media);
        expect($result)->toBeNull();
    });

    it('returns null when media ID does not exist', function (): void {
        $media = [
            MediaTagEnum::COVER->value => [9999], // Assuming 9999 does not exist
        ];
        $result = $this->action->handle($media);
        expect($result)->toBeNull();
    });

    it('returns the correct URL when media ID exists', function (): void {
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
