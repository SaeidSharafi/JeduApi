<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\DynamicListEntityTypeEnum;
use App\Enums\DynamicListSortByEnum;
use App\Enums\HomePageBlockTypeEnum;
use App\Enums\PermissionEnum;
use App\Models\Blog\BlogCategory;
use App\Models\Blog\BlogPost;
use App\Models\Category;
use App\Models\Course;
use App\Models\DigitalAsset;
use App\Models\HomePageBlock;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductDeliveryOption;
use App\Models\Seminar;
use App\Models\Staff;
use App\Models\Teacher;
use App\Models\Term;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Seeder;
use Illuminate\Http\File;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Plank\Mediable\Facades\MediaUploader;
use Plank\Mediable\Media;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Storage;

final class DemoSeeder extends Seeder
{
    public function run(): void
    {

        if (app()->isProduction()) {
            $this->command->error('You cannot run this seeder in production.');

            return;
        }

        if (app()->environment() === 'local') {
            $this->disableForeignKeyChecks();
            DB::table('mediables')->truncate();
            BlogPost::query()->truncate();
            BlogCategory::query()->truncate();
            HomePageBlock::query()->truncate();
            Order::query()->truncate();
            Media::query()->truncate();
            Course::query()->truncate();
            Seminar::query()->truncate();
            Category::query()->truncate();
            DigitalAsset::query()->truncate();
            Media::query()->truncate();
            Staff::query()->truncate();
            ProductDeliveryOption::query()->truncate();
            Product::query()->truncate();
            Teacher::query()->truncate();
            Vendor::query()->truncate();
            Term::query()->truncate();
            User::query()->truncate();
            $this->enableForeignKeyChecks();
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
        Storage::disk('public')->putFileAs('fake-media', new File($palceHolderPath), 'main.svg');
        Storage::disk('public')->putFileAs('fake-media', new File($palceHolderPath), 'preview.svg');
        MediaUploader::importPath('public', 'fake-media/placeholder1.mp4');
        MediaUploader::importPath('public', 'fake-media/placeholder2.mp4');
        MediaUploader::importPath('public', 'fake-media/placeholder3.mp4');
        MediaUploader::importPath('public', 'fake-media/fake-gallery.svg');
        MediaUploader::importPath('public', 'fake-media/placeholder.svg');
        MediaUploader::importPath('public', 'fake-media/icon.svg');
        MediaUploader::importPath('public', 'fake-media/main.svg');
        MediaUploader::importPath('public', 'fake-media/preview.svg');
        $cover = MediaUploader::importPath('public', 'fake-media/fake-cover.svg');

        $user = Staff::forceCreate([
            'name'     => 'Admin',
            'email'    => 'staff@example.com',
            'phone'    => '9300000000',
            'password' => bcrypt('password'),
            'is_admin' => true,
        ]);
        User::factory(10)->create();
        $role = Role::firstOrCreate(
            [
                'name'       => 'admin',
                'guard_name' => 'staff',
                'label'      => 'Admin',
            ]
        );
        $manager = Role::firstOrCreate(
            [
                'name'       => 'manager',
                'guard_name' => 'staff',
                'label'      => 'Manager',
            ]
        );
        $edito = Role::firstOrCreate(
            [
                'name'       => 'editor',
                'guard_name' => 'staff',
                'label'      => 'Editor',
            ]
        );
        Artisan::call('permissions:sync', [
            '--guard' => 'staff',
        ]);

        $permissions = Permission::query()->where('guard_name', 'staff')->get()->pluck('name')->toArray();
        $role->syncPermissions($permissions);
        $manager->syncPermissions([
            PermissionEnum::COURSE_VIEW->value,
            PermissionEnum::COURSE_VIEW_ANY->value,
            PermissionEnum::COURSE_CREATE->value,
            PermissionEnum::COURSE_UPDATE->value,
            PermissionEnum::COURSE_DELETE->value,
            PermissionEnum::SEMINAR_VIEW->value,
            PermissionEnum::SEMINAR_VIEW_ANY->value,
            PermissionEnum::SEMINAR_CREATE->value,
            PermissionEnum::SEMINAR_UPDATE->value,
            PermissionEnum::SEMINAR_DELETE->value,
        ]);

        $edito->syncPermissions([
            PermissionEnum::COURSE_UPDATE->value,
            PermissionEnum::SEMINAR_UPDATE->value,
        ]);
        $user->assignRole('admin');
        $staff = Staff::query()->first();
        Staff::factory(50)->create();
        Vendor::factory(4)
            ->withMedia()
            ->create();
        Term::factory(10)->create();
        Category::factory(100)
            ->withIcon()
            ->withImage()
            ->create([
                'created_by' => $staff->id,
            ]);
        DigitalAsset::factory(100)
            ->withFile()
            ->withCategory()
            ->create([
                'created_by' => $staff->id,
            ]);

        Course::factory(100)
            ->withMedia(['gallery', 'cover', 'video'])
            ->withCategory(3)
            ->withDigitalAssets(2, true)
            ->create([
                'created_by' => $staff->id,
            ]);
        Seminar::factory(100)
            ->withMedia(['gallery', 'cover', 'video'])
            ->withCategory(3)
            ->withDigitalAssets(2, true)
            ->create([
                'created_by' => $staff->id,
            ]);

        Teacher::factory(25)
            ->withMedia()
            ->create();

        Product::factory(100)
            ->withDeliveryOptions(realData: true)
            ->useExistingRelations()
            ->withCategory(3)
            ->create();
        for ($i = 0; $i < 100; $i++) {
            Order::factory()
                ->useExistingCustomer()
                ->has(
                    OrderItem::factory()
                        ->count(rand(1, 3))
                        ->useExistingRelations()
                        ->withEnrolment(),
                    'items'
                )
                ->withCalculatedTotalsAutomated()
                ->create();
        }

        BlogPost::factory()
            ->count(20)
            ->withMedia()
            ->create([
                'author_id' => $staff->id,
            ]);
        HomePageBlock::factory()
            ->banner($cover)
            ->create([
                'title'    => 'Welcome to Our Store',
                'location' => 'hero',
                'order'    => 1,
            ]);
        HomePageBlock::factory()
            ->curatedList(Product::query()->inRandomOrder()->take(5)->pluck('id')->values()->toArray())
            ->create([
                'title'    => 'Random Products',
                'location' => 'middle',
                'order'    => 2,
            ]);

        HomePageBlock::factory()
            ->curatedList(Category::query()->inRandomOrder()->take(5)->pluck('id')->values()->toArray(), HomePageBlockTypeEnum::MAIN_CATEGORIES)
            ->create([
                'title'    => 'Main Categories',
                'location' => 'middle',
                'order'    => 3,
            ]);

        HomePageBlock::factory()
            ->dynamicList()
            ->create([
                'title'    => 'Latest Course',
                'location' => 'middle',
                'order'    => 4,
            ]);
        HomePageBlock::factory()
            ->dynamicList(DynamicListEntityTypeEnum::ALL_PRODUCTS, DynamicListSortByEnum::POPULAR)
            ->create([
                'title'    => 'Popular Products',
                'location' => 'middle',
                'order'    => 5,
            ]);
        HomePageBlock::factory()
            ->dynamicList(DynamicListEntityTypeEnum::BLOG_POST)
            ->create([
                'title'    => 'Latest Blog Posts',
                'location' => 'middle',
                'order'    => 6,
            ]);

    }

    protected function disableForeignKeyChecks(): void
    {
        $driver = DB::connection()->getDriverName();

        switch ($driver) {
            case 'mysql':
                DB::statement('SET FOREIGN_KEY_CHECKS=0');
                break;
            case 'pgsql':
                DB::statement("SET session_replication_role = 'replica'");
                break;
            case 'sqlite':
                DB::statement('PRAGMA foreign_keys = OFF');
                break;
        }
    }

    protected function enableForeignKeyChecks(): void
    {
        $driver = DB::connection()->getDriverName();

        switch ($driver) {
            case 'mysql':
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
                break;
            case 'pgsql':
                DB::statement("SET session_replication_role = 'origin'");
                break;
            case 'sqlite':
                DB::statement('PRAGMA foreign_keys = ON');
                break;
        }
    }
}
