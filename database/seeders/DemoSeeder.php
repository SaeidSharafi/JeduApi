<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\PermissionEnum;
use App\Enums\System\MorphTypeEnum;
use App\Models\Blog\BlogCategory;
use App\Models\Blog\BlogPost;
use App\Models\Category;
use App\Models\Course;
use App\Models\DigitalAsset;
use App\Models\HomePageBlock;
use App\Models\Product;
use App\Models\ProductDeliveryOption;
use App\Models\Seminar;
use App\Models\Slider;
use App\Models\Staff;
use App\Models\StudentStory;
use App\Models\Teacher;
use App\Models\Term;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

final class DemoSeeder extends Seeder
{
    private string $demoDataPath;

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
        $this->seedModel(User::class, 'users.json', fn($data) => [...$data, 'password' => Hash::make('password')]);
        $this->seedModel(Staff::class, 'staff.json',
            fn($data) => [...$data, 'password' => Hash::make($data['password'])]);
        $this->seedModel(Vendor::class, 'vendors.json', function ($data) {
            // Check if the keys exist to avoid errors with partially filled data
            if (isset($data['social_links'])) {
                $data['social_links'] = json_encode($data['social_links']);
            }
            if (isset($data['theme_options'])) {
                $data['theme_options'] = json_encode($data['theme_options']);
            }

            return $data;
        });
        $this->seedModel(Term::class, 'terms.json');
        $this->seedModel(Teacher::class, 'teachers.json', function ($data) {
            if (isset($data['social_links'])) {
                $data['social_links'] = json_encode($data['social_links']);
            }

            return $data;
        });
        $this->seedModel(Category::class, 'categories.json');
        $this->seedModel(Course::class, 'courses.json', function ($data) {
            foreach ($data as $key => &$value) {
                // If the value for any column is an array, encode it to a JSON string.
                if (is_array($value)) {
                    $value = json_encode($value);
                }
            }

            return $data;
        });
        $this->seedModel(Seminar::class, 'seminars.json', function ($data) {
            foreach ($data as $key => &$value) {
                // If the value for any column is an array, encode it to a JSON string.
                if (is_array($value)) {
                    $value = json_encode($value);
                }
            }

            return $data;
        });
        $this->seedModel(DigitalAsset::class, 'digital_assets.json', function ($data) {
            foreach ($data as $key => &$value) {
                // If the value for any column is an array, encode it to a JSON string.
                if (is_array($value)) {
                    $value = json_encode($value);
                }
            }

            return $data;
        });
        $this->seedModel(Product::class, 'products.json', function ($data) {
            if (empty($data['term_id'])) {
                $data['term_id'] = random_int(1, 4);
            }
            foreach ($data as $key => &$value) {
                // If the value for any column is an array, encode it to a JSON string.
                if (is_array($value)) {
                    $value = json_encode($value);
                }
            }

            return $data;
        });
        $this->seedModel(ProductDeliveryOption::class, 'product_delivery_options.json');
        $this->seedModel(BlogCategory::class, 'blog_categories.json');
        $this->seedModel(BlogPost::class, 'blog_posts.json');
        $this->seedModel(Slider::class, 'sliders.json');
        $this->seedModel(HomePageBlock::class, 'home_page_blocks.json');
        $this->seedModel(StudentStory::class, 'student_stories.json');

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
                'first_name' => 'Staff',
                'last_name'  => 'Member',
                'password'   => Hash::make('password'),
                'status'     => true,
            ]
        );
        $staff->assignRole('admin');
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

    private function seedModel(string $modelClass, string $jsonFile, ?callable $modifier = null): void
    {
        $this->command->info('inserting '.$jsonFile.'...');
        $path = $this->demoDataPath.'/'.$jsonFile;
        if (!File::exists($path)) {
            $this->command->error("File not found: {$path}");

            return;
        }
        $model = new $modelClass();
        $table = $model->getTable();

        $collection = collect(json_decode(File::get($path), true));
        $categorizables = $collection->flatMap(function (array $item) use ($modelClass) {
            if (!isset($item['category_ids']) || !is_array($item['category_ids'])) {
                return []; // If no category_ids, return an empty set for this item.
            }

            // For each category ID, create a new pivot record.
            // `flatMap` will merge all the returned arrays into a single, flat collection.
            return collect($item['category_ids'])->map(function ($categoryId) use ($item, $modelClass) {
                return [
                    'category_id'        => $categoryId,
                    'categorizable_id'   => $item['id'],
                    'categorizable_type' => MorphTypeEnum::fromModelClass($modelClass)->value,
                ];
            });
        })->all();
        $preparedData = $collection->map(function (array $item) use ($modifier, $table, &$teacherIds) {
            // Apply the initial custom modifier first (e.g., for hashing passwords)
            if ($modifier) {
                $item = $modifier($item);
            }

            // Add UUID if the column exists and it's not already set
            if (Schema::hasColumn($table, 'uuid') && empty($item['uuid'])) {
                $item['uuid'] = (string) Str::uuid();
            }

            // The temporary key is no longer needed in the final data
            unset($item['category_ids']);
            unset($item['teacher_ids']);
            unset($item['course_ids']);

            // Robustly encode ANY remaining array values to JSON strings.
            // This permanently solves the "Array to string conversion" error.
            foreach ($item as $key => &$value) {
                if (is_array($value)) {
                    $value = json_encode($value);
                }
            }
            unset($value); // Good practice after a loop by reference.
            if (Schema::hasColumn($table, 'created_at') && empty($item['created_at'])) {
                $item['created_at'] = now();
            }
            if (Schema::hasColumn($table, 'created_by') && empty($item['created_by'])) {
                $item['created_by'] = Staff::first()->id;
            }
            return $item;
        })->all();

        if (!empty($preparedData)) {

            DB::table($table)->insert($preparedData);
        }

        if (!empty($categorizables)) {
            DB::table('categorizables')->insert($categorizables);
        }
        if ($modelClass === ProductDeliveryOption::class) {
            // For ProductDeliveryOption, we also need to handle the pivot table for teachers
            $pdoTeacherLinks = $collection->flatMap(function (array $item) {
                if (!isset($item['teacher_ids']) || !is_array($item['teacher_ids'])) {
                    return []; // If no teacher_ids, return an empty set for this item.
                }

                return collect($item['teacher_ids'])->map(function ($teacherId) use ($item) {
                    return [
                        'product_delivery_option_id' => $item['id'],
                        'teacher_id'                 => $teacherId,
                    ];
                });
            })->all();

            if (!empty($pdoTeacherLinks)) {
                DB::table('product_delivery_option_teacher')->insert($pdoTeacherLinks);
            }
        }
        if ($modelClass === StudentStory::class) {
            // For StudentStory, we also need to handle the pivot table for courses
            $storyCourseLinks = $collection->flatMap(function (array $item) {
                if (!isset($item['course_ids']) || !is_array($item['course_ids'])) {
                    return []; // If no course_ids, return an empty set for this item.
                }

                return collect($item['course_ids'])->map(function ($courseId) use ($item) {
                    return [
                        'student_story_id' => $item['id'],
                        'course_id'        => $courseId,
                    ];
                });
            })->all();

            if (!empty($storyCourseLinks)) {
                DB::table('course_student_story')->insert($storyCourseLinks);
            }
        }

        $this->command->line("  <info>Seeded:</info>  {$jsonFile}");
    }

    private function truncateTables(): void
    {
        $this->command->warn('Truncating all relevant tables...');

        $this->disableForeignKeyChecks();

        $tables = [
            'home_page_blocks', 'sliders', 'blog_posts', 'blog_categories',
            'product_delivery_options', 'products', 'digital_assets', 'seminars',
            'courses', 'categories', 'teachers', 'terms', 'vendors', 'staff', 'users',
            // Add pivot tables or others that need clearing
            'categorizables', 'mediables', 'enrollments', 'order_items', 'orders',
            'product_delivery_option_teacher',
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
