<?php

declare(strict_types=1);

namespace Tests\Support\Traits;

use Exception;
use Illuminate\Http\File;
use Illuminate\Support\Facades\Storage;
use Plank\Mediable\Facades\MediaUploader;

trait FakeMediaTrait
{
    protected function fakeMedia(): void
    {
        $videoPath       = base_path().'/resources/seed-media/placeholder.mp4';
        $coverPath       = base_path().'/resources/seed-media/fake-cover.svg';
        $galleryPath     = base_path().'/resources/seed-media/fake-gallery.svg';
        $palceHolderPath = base_path().'/resources/seed-media/placeholder.svg';
        $iconPath        = base_path().'/resources/seed-media/icon.svg';
        // use try-cath to rpevent erros if files already exist in the storage, but ignore the exception
        try {
            Storage::disk('public')->putFileAs('fake-media', new File($videoPath), 'placeholder1.mp4');
            Storage::disk('public')->putFileAs('fake-media', new File($videoPath), 'placeholder2.mp4');
            Storage::disk('public')->putFileAs('fake-media', new File($videoPath), 'placeholder3.mp4');
            Storage::disk('public')->putFileAs('fake-media', new File($coverPath), 'fake-cover.svg');
            Storage::disk('public')->putFileAs('fake-media', new File($galleryPath), 'fake-gallery.svg');
            Storage::disk('public')->putFileAs('fake-media', new File($palceHolderPath), 'placeholder.svg');
            Storage::disk('public')->putFileAs('fake-media', new File($iconPath), 'icon.svg');
            Storage::disk('public')->putFileAs('fake-media', new File($palceHolderPath), 'main.svg');
            Storage::disk('public')->putFileAs('fake-media', new File($palceHolderPath), 'preview.svg');
        } catch (Exception $e) {
            // ignore the exception
        }

        MediaUploader::importPath('public', 'fake-media/placeholder1.mp4');
        MediaUploader::importPath('public', 'fake-media/placeholder2.mp4');
        MediaUploader::importPath('public', 'fake-media/placeholder3.mp4');
        MediaUploader::importPath('public', 'fake-media/fake-gallery.svg');
        MediaUploader::importPath('public', 'fake-media/placeholder.svg');
        MediaUploader::importPath('public', 'fake-media/icon.svg');
        MediaUploader::importPath('public', 'fake-media/main.svg');
        MediaUploader::importPath('public', 'fake-media/preview.svg');
        $cover = MediaUploader::importPath('public', 'fake-media/fake-cover.svg');
    }
}
