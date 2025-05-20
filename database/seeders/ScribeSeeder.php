<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\Course;
use Illuminate\Database\Seeder;
use Illuminate\Http\File;
use Plank\Mediable\Facades\MediaUploader;
use Plank\Mediable\Media;
use Storage;

final class ScribeSeeder extends Seeder
{
    public function run(): void
    {

        Admin::query()->truncate();
        Admin::forceCreate([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'phone' => '9300000000',
            'password' => bcrypt('password'),
            'is_admin' => true,
        ]);
        Media::query()
            ->where('directory', 'fake-media')
            ->orWhere('directory', 'fake-media/uploads')
            ->delete();

        Storage::disk('public')->deleteDirectory('fake-media');
        $path = base_path().'/resources/seed-media/placeholder.mp4';
        Storage::disk('public')->putFileAs('fake-media', new File($path), 'placeholder1.mp4');
        Storage::disk('public')->putFileAs('fake-media', new File($path), 'placeholder2.mp4');
        Storage::disk('public')->putFileAs('fake-media', new File($path), 'placeholder3.mp4');
        MediaUploader::importPath('public', 'fake-media/placeholder1.mp4');
        MediaUploader::importPath('public', 'fake-media/placeholder2.mp4');
        MediaUploader::importPath('public', 'fake-media/placeholder3.mp4');

        Course::query()->truncate();
        Course::factory(100)
            ->withMedia(['gallery', 'cover', 'video'])
            ->create([
                'created_by' => Admin::query()->first()->id,
            ]);

    }
}
