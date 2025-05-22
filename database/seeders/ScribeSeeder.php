<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\Category;
use App\Models\Course;
use Illuminate\Database\Seeder;
use Illuminate\Http\File;
use Illuminate\Support\Facades\DB;
use Plank\Mediable\Facades\MediaUploader;
use Plank\Mediable\Media;
use Plank\Mediable\Mediable;
use Storage;

final class ScribeSeeder extends Seeder
{
    public function run(): void
    {

        if (app()->isProduction()){
            $this->command->error('You cannot run this seeder in production.');
            return;
        }

        if (app()->environment() === 'local'){
            DB::statement('SET FOREIGN_KEY_CHECKS = 0');
            DB::table('mediables')->truncate();
            Media::query()->truncate();
            Course::query()->truncate();
            Category::query()->truncate();
            Media::query()->truncate();
            Admin::query()->truncate();
            DB::statement('SET FOREIGN_KEY_CHECKS = 1');
        }

        Storage::disk('public')->deleteDirectory('fake-media');
        $videoPath = base_path().'/resources/seed-media/placeholder.mp4';
        $coverPath = base_path().'/resources/seed-media/fake-cover.svg';
        $galleryPath = base_path().'/resources/seed-media/fake-gallery.svg';
        $palceHolderPath = base_path().'/resources/seed-media/placeholder.svg';
        $iconPath = base_path().'/resources/seed-media/icon.svg';
        Storage::disk('public')->putFileAs('fake-media', new File($videoPath), 'placeholder1.mp4');
        Storage::disk('public')->putFileAs('fake-media', new File($videoPath), 'placeholder2.mp4');
        Storage::disk('public')->putFileAs('fake-media', new File($videoPath), 'placeholder3.mp4');
        Storage::disk('public')->putFileAs('fake-media', new File($coverPath), 'fake-cover.svg');
        Storage::disk('public')->putFileAs('fake-media', new File($galleryPath), 'fake-gallery.svg');
        Storage::disk('public')->putFileAs('fake-media', new File($palceHolderPath), 'placeholder.svg');
        Storage::disk('public')->putFileAs('fake-media', new File($iconPath), 'icon.svg');
        MediaUploader::importPath('public', 'fake-media/placeholder1.mp4');
        MediaUploader::importPath('public', 'fake-media/placeholder2.mp4');
        MediaUploader::importPath('public', 'fake-media/placeholder3.mp4');
        MediaUploader::importPath('public', 'fake-media/fake-cover.svg');
        MediaUploader::importPath('public', 'fake-media/fake-gallery.svg');
        MediaUploader::importPath('public', 'fake-media/placeholder.svg');
        MediaUploader::importPath('public', 'fake-media/icon.svg');


        Admin::forceCreate([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'phone' => '9300000000',
            'password' => bcrypt('password'),
            'is_admin' => true,
        ]);
        Course::factory(100)
            ->withMedia(['gallery', 'cover', 'video'])
            ->create([
                'created_by' => Admin::query()->first()->id,
            ]);

        Category::factory(100)
            ->withIcon()
            ->withImage()
            ->create([
                'created_by' => Admin::query()->first()->id,
            ]);
    }
}
