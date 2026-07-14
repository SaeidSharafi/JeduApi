<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Content\HomePageBlockTypeEnum;
use App\Enums\PermissionEnum;
use App\Enums\System\MorphTypeEnum;
use App\Models\AdviceRequest;
use App\Models\Blog\BlogCategory;
use App\Models\Blog\BlogPost;
use App\Models\Category;
use App\Models\CollaborationRequest;
use App\Models\ContactUsRequest;
use App\Models\Course;
use App\Models\DigitalAsset;
use App\Models\DiscountCoupon;
use App\Models\DiscountPromotion;
use App\Models\DiscountPromotionRule;
use App\Models\Enrollment;
use App\Models\HomePageBlock;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Partner;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\Product;
use App\Models\ProductDeliveryOption;
use App\Models\ProductDeliveryOptionDiscountPrice;
use App\Models\ProductPrice;
use App\Models\Refund;
use App\Models\Review;
use App\Models\Seminar;
use App\Models\Setting;
use App\Models\Slider;
use App\Models\Staff;
use App\Models\StudentStory;
use App\Models\Teacher;
use App\Models\Term;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Wallet;
use App\Models\WalletCampaign;
use App\Models\WalletTransaction;
use Hekmatinasser\Verta\Facades\Verta;
use Illuminate\Database\Seeder;
use Illuminate\Http\File;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File as FileFacade;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Plank\Mediable\Facades\MediaUploader;
use Plank\Mediable\Media;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Storage;

final class DemoSeeder extends Seeder
{
    private string $demoDataPath;

    /** @var array<string, Media> Cached imported media by filename */
    private array $mediaCache = [];

    public function __construct()
    {
        $this->demoDataPath = database_path('demo');
    }

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command->error('You cannot run this seeder in production.');

            return;
        }

        $this->command->info('Starting Farsi demo data seeding...');

        $this->truncateTables();
        $this->prepareSeedMedia();

        $this->seedModel(User::class, 'users.json', fn ($data) => [...$data, 'password' => Hash::make('password')], function (array $collection) {
            $this->seedUserMedia($collection);
        });

        $this->seedModel(Staff::class, 'staff.json',
            fn ($data) => [...$data, 'password' => Hash::make($data['password'])]);

        $this->seedModel(Vendor::class, 'vendors.json', function ($data) {
            if (isset($data['social_links'])) {
                $data['social_links'] = json_encode($data['social_links']);
            }
            if (isset($data['theme_options'])) {
                $data['theme_options'] = json_encode($data['theme_options']);
            }

            return $data;
        }, function (array $collection) {
            $this->seedVendorMedia($collection);
        });

        $this->seedModel(Term::class, 'terms.json');

        $this->seedModel(Teacher::class, 'teachers.json', function ($data) {
            if (isset($data['social_links'])) {
                $data['social_links'] = json_encode($data['social_links']);
            }

            return $data;
        }, function (array $collection) {
            $this->seedTeacherMedia($collection);
        });

        $this->seedModel(Category::class, 'categories.json', null, function (array $collection) {
            $this->seedCategoryMedia($collection);
        });

        $this->seedModel(Course::class, 'courses.json', function ($data) {
            foreach ($data as $key => &$value) {
                if (is_array($value)) {
                    $value = json_encode($value);
                }
            }

            return $data;
        }, function (array $collection) {
            $this->seedCourseMedia($collection);
        });

        $this->seedModel(Seminar::class, 'seminars.json', function ($data) {
            foreach ($data as $key => &$value) {
                if (is_array($value)) {
                    $value = json_encode($value);
                }
            }

            return $data;
        }, function (array $collection) {
            $this->seedSeminarMedia($collection);
        });

        $this->seedModel(DigitalAsset::class, 'digital_assets.json', function ($data) {
            foreach ($data as $key => &$value) {
                if (is_array($value)) {
                    $value = json_encode($value);
                }
            }

            return $data;
        }, function (array $collection) {
            $this->seedDigitalAssetMedia($collection);
        });

        $this->seedModel(Product::class, 'products.json', function ($data) {
            if (empty($data['term_id'])) {
                $data['term_id'] = random_int(1, 4);
            }
            foreach ($data as $key => &$value) {
                if (is_array($value)) {
                    $value = json_encode($value);
                }
            }

            return $data;
        });

        $this->seedModel(ProductDeliveryOption::class, 'product_delivery_options.json');

        $this->seedModel(BlogCategory::class, 'blog_categories.json', null, function (array $collection) {
            $this->seedBlogCategoryMedia($collection);
        });

        $this->seedModel(BlogPost::class, 'blog_posts.json', null, function (array $collection) {
            $this->seedBlogPostMedia($collection);
        });

        $this->seedModel(Slider::class, 'sliders.json', null, function (array $collection) {
            $this->seedSliderMedia($collection);
        });

        $this->seedModel(HomePageBlock::class, 'home_page_blocks.json', null, function (array $collection) {
            $this->seedHomePageBlockMedia($collection);
        });

        $this->seedModel(StudentStory::class, 'student_stories.json', null, function (array $collection) {
            $this->seedStudentStoryMedia($collection);
        });

        // ─── Discounts & Promotions ────────────────────────────────────────

        $this->seedModel(DiscountPromotion::class, 'discount_promotions.json');
        $this->seedModel(DiscountPromotionRule::class, 'discount_promotion_rules.json');
        $this->seedModel(DiscountCoupon::class, 'discount_coupons.json');
        $this->seedModel(ProductDeliveryOptionDiscountPrice::class, 'product_delivery_option_discount_prices.json');

        // ─── Product Prices ────────────────────────────────────────────────

        $this->seedModel(ProductPrice::class, 'product_prices.json');

        // ─── Orders & Fulfillment ──────────────────────────────────────────

        $this->seedModel(Order::class, 'orders.json');
        $this->seedModel(OrderItem::class, 'order_items.json');
        $this->seedModel(Payment::class, 'payments.json');
        $this->seedModel(PaymentTransaction::class, 'payment_transactions.json');
        $this->seedModel(Refund::class, 'refunds.json');
        $this->seedModel(Enrollment::class, 'enrollments.json');

        // ─── Wallet System ─────────────────────────────────────────────────

        $this->seedModel(Wallet::class, 'wallets.json');
        $this->seedModel(WalletTransaction::class, 'wallet_transactions.json');
        $this->seedModel(WalletCampaign::class, 'wallet_campaigns.json');

        // ─── Content & Settings ────────────────────────────────────────────

        $this->seedModel(Setting::class, 'settings.json', function ($data) {
            $processMedia = function (array &$array) use (&$processMedia) {
                foreach ($array as $key => &$value) {
                    // 1. Handle "images" which is an array of strings
                    if ($key === 'images' && is_array($value)) {
                        foreach ($value as $subKey => $subValue) {
                            if (is_string($subValue)) {
                                $value[$subKey] = $this->media($subValue)?->id;
                            }
                        }
                    } elseif (in_array($key, ['image', 'logo', 'icon']) && is_string($value)) {
                        $array[$key.'_url'] = $this->media($value)?->getUrl();
                        $value              = $this->media($value)?->id;
                    } elseif (is_array($value)) {
                        $processMedia($value);
                    }
                }
            };

            if (is_array($data)) {
                $processMedia($data);
            }

            return $data;
        });
        $this->seedModel(Partner::class, 'partners.json', null, function (array $collection) {
            $this->seedPartnerMedia($collection);
        });

        // ─── Reviews ───────────────────────────────────────────────────────

        $this->seedModel(Review::class, 'reviews.json');

        // ─── Contact & Collaboration Forms ─────────────────────────────────

        $this->seedModel(ContactUsRequest::class, 'contact_us_requests.json');
        $this->seedModel(CollaborationRequest::class, 'collaboration_requests.json');
        $this->seedModel(AdviceRequest::class, 'advice_requests.json');

        $this->command->info('Farsi demo data seeding complete.');

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
        $editor = Role::firstOrCreate(
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

        $editor->syncPermissions([
            PermissionEnum::COURSE_UPDATE->value,
            PermissionEnum::SEMINAR_UPDATE->value,
        ]);
        $staff = Staff::firstOrCreate([
            'email' => 'staff@example.com',
            'phone' => '9300000000',
        ],
            [
                'name'     => 'Staff Member',
                'password' => Hash::make('password'),
            ]
        );
        $staff->assignRole('admin');
    }

    // ─── Original Infrastructure (modified) ────────────────────────────────

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

    // ─── Media Preparation ─────────────────────────────────────────────────

    private function prepareSeedMedia(): void
    {
        $this->command->info('Preparing seed media files...');

        Storage::disk('public')->deleteDirectory('fake-media');

        $seedPath = base_path().'/resources/seed-media';

        // Themed cover images for different content categories
        $coverFiles = [
            'cover-tech.svg', 'cover-art.svg', 'cover-science.svg',
            'cover-music.svg', 'cover-business.svg', 'cover-humanities.svg',
        ];
        // Gallery variants
        $galleryFiles = ['gallery-tech.svg', 'gallery-art.svg', 'gallery-nature.svg'];
        // Sliders & banners
        $sliderFiles = ['slider-main.svg', 'slider-promo.svg'];
        $bannerFiles = ['banner-course.svg', 'banner-webinar.svg'];
        // Core assets
        $coreImageFiles = [
            'placeholder.svg', 'icon.svg',
            'avatar-male.svg', 'avatar-female.svg', 'avatar-default.svg',
            'logo-tech.svg', 'logo-art.svg', 'logo-humanities.svg', 'logo-lang.svg',
            'favicon-tech.svg', 'favicon-art.svg', 'favicon-humanities.svg', 'favicon-lang.svg',
            'bank-mellat.svg', 'digipay.svg',
        ];

        // Import 3 video copies
        Storage::disk('public')->putFileAs('fake-media', new File($seedPath.'/placeholder.mp4'), 'placeholder1.mp4');
        Storage::disk('public')->putFileAs('fake-media', new File($seedPath.'/placeholder.mp4'), 'placeholder2.mp4');
        Storage::disk('public')->putFileAs('fake-media', new File($seedPath.'/placeholder.mp4'), 'placeholder3.mp4');

        // Import all image files
        $allImageFiles = array_merge($coverFiles, $galleryFiles, $sliderFiles, $bannerFiles, $coreImageFiles);
        foreach ($allImageFiles as $file) {
            Storage::disk('public')->putFileAs('fake-media', new File($seedPath.'/'.$file), $file);
        }

        // Import all into Media table via MediaUploader
        $importFiles = array_merge(
            ['placeholder1.mp4', 'placeholder2.mp4', 'placeholder3.mp4'],
            $allImageFiles,
        );

        foreach ($importFiles as $filename) {
            $media                       = MediaUploader::importPath('public', 'fake-media/'.$filename);
            $this->mediaCache[$filename] = $media;
        }

        $this->command->info('Imported '.count($this->mediaCache).' media files.');
    }

    /**
     * Get cached Media model by filename.
     */
    private function media(string $filename): Media
    {
        return $this->mediaCache[$filename];
    }

    // ─── Per-Model Media Attachment ─────────────────────────────────────────

    private function seedVendorMedia(array $collection): void
    {
        $vendorLogos = [
            $this->media('logo-tech.svg'),
            $this->media('logo-art.svg'),
            $this->media('logo-humanities.svg'),
            $this->media('logo-lang.svg'),
        ];
        $vendorFavicons = [
            $this->media('favicon-tech.svg'),
            $this->media('favicon-art.svg'),
            $this->media('favicon-humanities.svg'),
            $this->media('favicon-lang.svg'),
        ];

        $vendors = Vendor::whereIn('id', collect($collection)->pluck('id'))->get();
        foreach ($vendors as $i => $vendor) {
            $logo    = $vendorLogos[$i % count($vendorLogos)];
            $favicon = $vendorFavicons[$i % count($vendorFavicons)];
            $vendor->attachMedia($logo, 'logo');
            $vendor->attachMedia($favicon, 'favicon');
            $vendor->logo_url    = $logo->getUrl();
            $vendor->favicon_url = $favicon->getUrl();
            $vendor->save();
        }
    }

    private function seedTeacherMedia(array $collection): void
    {
        $avatars = [
            $this->media('avatar-male.svg'),
            $this->media('avatar-female.svg'),
            $this->media('avatar-default.svg'),
        ];

        $teachers = Teacher::whereIn('id', collect($collection)->pluck('id'))->get();
        foreach ($teachers as $i => $teacher) {
            $avatar = $avatars[$i % count($avatars)];
            $teacher->attachMedia($avatar, 'avatar');
            $teacher->avatar_url = $avatar->getUrl();
            $teacher->save();
        }
    }
    private function seedUserMedia(array $collection): void
    {
        $avatars = [
            $this->media('avatar-male.svg'),
            $this->media('avatar-female.svg'),
            $this->media('avatar-default.svg'),
        ];

        $users = User::whereIn('id', collect($collection)->pluck('id'))->get();
        foreach ($users as $i => $user) {
            if (rand(1, 100) <= 30) {
                continue;
            }
            $avatar = $avatars[$i % count($avatars)];
            $user->attachMedia($avatar, 'avatar');
            $user->avatar_url = $avatar->getUrl();
            $user->save();
        }
    }

    private function seedCategoryMedia(array $collection): void
    {
        $image = $this->media('placeholder.svg');
        $icon  = $this->media('icon.svg');

        $categories = Category::whereIn('id', collect($collection)->pluck('id'))->get();
        foreach ($categories as $category) {
            $category->syncMedia($image, 'image');
            $category->syncMedia($icon, 'icon');
            $category->image_url = $image->getUrl();
            $category->icon_url  = $icon->getUrl();
            $category->save();
        }
    }

    private function seedCourseMedia(array $collection): void
    {
        $coverPool = [
            $this->media('cover-tech.svg'),
            $this->media('cover-art.svg'),
            $this->media('cover-science.svg'),
            $this->media('cover-music.svg'),
            $this->media('cover-business.svg'),
            $this->media('cover-humanities.svg'),
        ];
        $galleryPool = [
            $this->media('gallery-tech.svg'),
            $this->media('gallery-art.svg'),
            $this->media('gallery-nature.svg'),
        ];
        $videoPool = [
            $this->media('placeholder1.mp4'),
            $this->media('placeholder2.mp4'),
            $this->media('placeholder3.mp4'),
        ];

        $courses = Course::whereIn('id', collect($collection)->pluck('id'))->get();
        foreach ($courses as $i => $course) {
            $cover   = $coverPool[$i % count($coverPool)];
            $gallery = $galleryPool[$i % count($galleryPool)];
            $video   = $videoPool[$i % count($videoPool)];
            $course->attachMedia($cover, 'cover');
            $course->attachMedia($gallery, 'gallery');
            $course->attachMedia($video, 'video');
            $course->thumbnail_url = $cover->getUrl();
            $course->save();
        }
    }

    private function seedSeminarMedia(array $collection): void
    {
        $coverPool = [
            $this->media('cover-tech.svg'),
            $this->media('cover-art.svg'),
            $this->media('cover-science.svg'),
            $this->media('cover-music.svg'),
            $this->media('cover-business.svg'),
            $this->media('cover-humanities.svg'),
        ];
        $galleryPool = [
            $this->media('gallery-tech.svg'),
            $this->media('gallery-art.svg'),
            $this->media('gallery-nature.svg'),
        ];
        $videoPool = [
            $this->media('placeholder1.mp4'),
            $this->media('placeholder2.mp4'),
            $this->media('placeholder3.mp4'),
        ];

        $seminars = Seminar::whereIn('id', collect($collection)->pluck('id'))->get();
        foreach ($seminars as $i => $seminar) {
            $cover   = $coverPool[$i % count($coverPool)];
            $gallery = $galleryPool[$i % count($galleryPool)];
            $video   = $videoPool[$i % count($videoPool)];
            $seminar->attachMedia($cover, 'cover');
            $seminar->attachMedia($gallery, 'gallery');
            $seminar->attachMedia($video, 'video');
            $seminar->thumbnail_url = $cover->getUrl();
            $seminar->save();
        }
    }

    private function seedDigitalAssetMedia(array $collection): void
    {
        $mainPool = [
            $this->media('cover-tech.svg'),
            $this->media('cover-art.svg'),
            $this->media('cover-science.svg'),
            $this->media('cover-business.svg'),
        ];
        $previewPool = [
            $this->media('cover-humanities.svg'),
            $this->media('cover-music.svg'),
            $this->media('placeholder.svg'),
        ];

        $assets = DigitalAsset::whereIn('id', collect($collection)->pluck('id'))->get();
        foreach ($assets as $i => $asset) {
            $main    = $mainPool[$i % count($mainPool)];
            $preview = $previewPool[$i % count($previewPool)];
            $asset->syncMedia($main, 'main');
            $asset->syncMedia($preview, 'preview');
            $asset->thumbnail_url = $main->getUrl();
            $asset->save();
        }
    }

    private function seedBlogCategoryMedia(array $collection): void
    {
        $icon = $this->media('icon.svg');

        $categories = BlogCategory::whereIn('id', collect($collection)->pluck('id'))->get();
        foreach ($categories as $category) {
            $category->syncMedia($icon, 'icon');
            $category->icon = $icon->getUrl();
            $category->save();
        }
    }

    private function seedBlogPostMedia(array $collection): void
    {
        $coverPool = [
            $this->media('cover-tech.svg'),
            $this->media('cover-art.svg'),
            $this->media('cover-science.svg'),
            $this->media('cover-music.svg'),
            $this->media('cover-business.svg'),
            $this->media('cover-humanities.svg'),
            $this->media('gallery-tech.svg'),
            $this->media('gallery-art.svg'),
        ];

        $posts = BlogPost::whereIn('id', collect($collection)->pluck('id'))->get();
        foreach ($posts as $i => $post) {
            $cover = $coverPool[$i % count($coverPool)];
            $post->attachMedia($cover, 'cover');
            $post->thumbnail_url = $cover->getUrl();
            $post->save();
        }
    }

    private function seedSliderMedia(array $collection): void
    {
        $imagePool = [
            $this->media('slider-main.svg'),
            $this->media('slider-promo.svg'),
        ];

        $sliders = Slider::whereIn('id', collect($collection)->pluck('id'))->get();
        foreach ($sliders as $i => $slider) {
            $image = $imagePool[$i % count($imagePool)];
            $slider->syncMedia($image, 'image');
            $slider->image_url = $image->getUrl();
            $slider->save();
        }
    }

    private function seedHomePageBlockMedia(array $collection): void
    {
        $imagePool = [
            $this->media('banner-course.svg'),
            $this->media('banner-webinar.svg'),
        ];

        $blocks = HomePageBlock::whereIn('id', collect($collection)->pluck('id'))->get();
        foreach ($blocks as $i => $block) {
            if (! in_array($block->type, [HomePageBlockTypeEnum::WEBINAR_BANNER, HomePageBlockTypeEnum::BANNER], true)) {
                continue;
            }
            $image = $imagePool[$i % count($imagePool)];
            $block->syncMedia($image, 'image');
            $content              = $block->content;
            $content['image_url'] = $image->getUrl();
            $content['image_id']  = $image->id;
            $block->content       = $content;
            $block->save();
        }
    }

    private function seedStudentStoryMedia(array $collection): void
    {
        $avatarPool = [
            $this->media('avatar-male.svg'),
            $this->media('avatar-female.svg'),
            $this->media('avatar-default.svg'),
        ];

        $stories = StudentStory::whereIn('id', collect($collection)->pluck('id'))->get();
        foreach ($stories as $i => $story) {
            $avatar = $avatarPool[$i % count($avatarPool)];
            $story->syncMedia($avatar, 'avatar');
            $story->avatar_url = $avatar->getUrl();
            $story->save();
        }
    }

    // private function seedSettingMedia(array $collection): void
    // {
    //    $logoPool = [
    //        $this->media("logo-tech.svg"),
    //        $this->media("bank-mellat.svg"),
    //    ];
    //    $images = [
    //        $this->media("gallery-tech.svg"),
    //        $this->media("gallery-art.svg"),
    //        $this->media("gallery-nature.svg"),
    //    ];
    //
    //    $settings = Setting::whereIn('id', collect($collection)->pluck('id'))->get();
    //    foreach ($settings as $i => $setting) {
    //        if (isset($setting->value['images'])){
    //            $setting->syncMedia($logo, 'logo');
    //        }
    //
    //        $logo = $logoPool[$i % count($logoPool)];
    //        $setting->syncMedia($logo, 'logo');
    //        $setting->image_url = $logo->getUrl();
    //        $setting->image_id  = $logo->id;
    //        $setting->save();
    //    }
    // }
    private function seedPartnerMedia(array $collection): void
    {
        $logoPool = [
            $this->media('logo-tech.svg'),
            $this->media('logo-art.svg'),
            $this->media('logo-humanities.svg'),
            $this->media('logo-lang.svg'),
        ];

        $partners = Partner::whereIn('id', collect($collection)->pluck('id'))->get();
        foreach ($partners as $i => $partner) {
            $logo = $logoPool[$i % count($logoPool)];
            $partner->syncMedia($logo, 'logo');
            $partner->image_url = $logo->getUrl();
            $partner->image_id  = $logo->id;
            $partner->save();
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $collection  Raw JSON collection before modifier
     */
    private function seedModel(
        string $modelClass,
        string $jsonFile,
        ?callable $modifier = null,
        ?callable $afterInsert = null,
    ): void {
        $this->command->info('inserting '.$jsonFile.'...');
        $path = $this->demoDataPath.'/'.$jsonFile;
        if (! FileFacade::exists($path)) {
            $this->command->error("File not found: {$path}");

            return;
        }
        $model = new $modelClass();
        $table = $model->getTable();

        $collection = collect(json_decode(FileFacade::get($path), true));

        // Store original collection (before modification) for afterInsert callback
        $originalCollection = $collection;

        $categorizables = $collection->flatMap(function (array $item) use ($modelClass) {
            if (! isset($item['category_ids']) || ! is_array($item['category_ids'])) {
                return [];
            }

            return collect($item['category_ids'])->map(function ($categoryId) use ($item, $modelClass) {
                return [
                    'category_id'        => $categoryId,
                    'categorizable_id'   => $item['id'],
                    'categorizable_type' => MorphTypeEnum::fromModelClass($modelClass)->value,
                ];
            });
        })->all();

        $preparedData = $collection->map(function (array $item) use ($modifier, $table) {
            if ($modifier) {
                $item = $modifier($item);
            }

            if (Schema::hasColumn($table, 'uuid') && empty($item['uuid'])) {
                $item['uuid'] = (string) Str::uuid();
            }

            unset($item['category_ids']);
            unset($item['teacher_ids']);
            unset($item['course_ids']);
            unset($item['related_productables']);

            foreach ($item as $key => &$value) {
                if (is_array($value)) {
                    $value = json_encode($value);
                }
            }
            unset($value);
            if (Schema::hasColumn($table, 'date_of_birth') && isset($item['date_of_birth'])) {
                $item['date_of_birth'] = Verta::parseFormat('Y-m-d', $item['date_of_birth'])->toCarbon()->toDateString();
            }
            if (Schema::hasColumn($table, 'created_at') && empty($item['created_at'])) {
                $item['created_at'] = now();
            }
            if (Schema::hasColumn($table, 'created_by') && empty($item['created_by'])) {
                $item['created_by'] = Staff::first()->id;
            }

            return $item;
        })->all();

        if (! empty($preparedData)) {
            DB::table($table)->insert($preparedData);
            $this->resetSequence($table, $model->getKeyName());
        }

        if (! empty($categorizables)) {
            DB::table('categorizables')->insert($categorizables);
        }

        // Handle blog post productable pivot
        if ($modelClass === BlogPost::class) {
            $realtedProductables = $originalCollection->flatMap(function (array $item) {
                if (! isset($item['related_productables']) || ! is_array($item['related_productables'])) {
                    return [];
                }

                return collect($item['related_productables'])->map(function ($related) use ($item) {
                    return [
                        'blog_post_id'     => $item['id'],
                        'productable_id'   => $related['id'],
                        'productable_type' => $related['type'],
                    ];
                });
            })->all();

            if (! empty($realtedProductables)) {
                DB::table('blog_post_productables')->insert($realtedProductables);
            }
        }

        // Handle PDO teacher pivot
        if ($modelClass === ProductDeliveryOption::class) {
            $pdoTeacherLinks = $originalCollection->flatMap(function (array $item) {
                if (! isset($item['teacher_ids']) || ! is_array($item['teacher_ids'])) {
                    return [];
                }

                return collect($item['teacher_ids'])->map(function ($teacherId) use ($item) {
                    return [
                        'product_delivery_option_id' => $item['id'],
                        'teacher_id'                 => $teacherId,
                    ];
                });
            })->all();

            if (! empty($pdoTeacherLinks)) {
                DB::table('product_delivery_option_teacher')->insert($pdoTeacherLinks);
            }
        }

        // Handle student story course pivot
        if ($modelClass === StudentStory::class) {
            $storyCourseLinks = $originalCollection->flatMap(function (array $item) {
                if (! isset($item['course_ids']) || ! is_array($item['course_ids'])) {
                    return [];
                }

                return collect($item['course_ids'])->map(function ($courseId) use ($item) {
                    return [
                        'student_story_id' => $item['id'],
                        'course_id'        => $courseId,
                    ];
                });
            })->all();

            if (! empty($storyCourseLinks)) {
                DB::table('course_student_story')->insert($storyCourseLinks);
            }
        }

        if ($modelClass === Setting::class) {

        }

        // Run after-insert callback (used for media attachment)
        if ($afterInsert !== null) {
            $afterInsert($originalCollection->all());
        }

        $this->command->line("  <info>Seeded:</info>  {$jsonFile}");
    }

    private function resetSequence(string $table, string $primaryKey = 'id'): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        $sequenceName = DB::select("SELECT pg_get_serial_sequence('{$table}', '{$primaryKey}') as name;")[0]->name;

        if ($sequenceName) {
            $maxId = DB::table($table)->max($primaryKey);
            if ($maxId) {
                DB::statement("SELECT setval('{$sequenceName}', ?, true)", [$maxId]);
            }
        }
    }

    private function truncateTables(): void
    {
        $this->command->warn('Truncating all relevant tables...');

        $this->disableForeignKeyChecks();

        $tables = [
            'advice_requests', 'collaboration_requests', 'contact_us_requests',
            'reviews', 'partners', 'settings',
            'wallet_transactions', 'wallet_campaigns', 'wallets',
            'payment_transactions', 'payments',
            'enrollments', 'refunds', 'order_items', 'orders',
            'product_delivery_option_discount_prices', 'product_prices',
            'discount_coupons', 'discount_promotion_rules', 'discount_promotions',
            'student_stories', 'home_page_blocks', 'sliders', 'blog_posts', 'blog_categories',
            'product_delivery_options', 'products', 'digital_assets', 'seminars',
            'courses', 'categories', 'teachers', 'terms', 'vendors', 'staff', 'users',
            'categorizables', 'media', 'mediables',
            'product_delivery_option_teacher', 'blog_post_productables', 'blog_post_category',
            'course_student_story',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->truncate();
            }
        }

        $this->enableForeignKeyChecks();

        $this->command->info('Tables truncated successfully.');
    }
}
