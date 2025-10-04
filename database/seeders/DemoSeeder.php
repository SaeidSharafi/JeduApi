<?php

declare(strict_types=1);

namespace Database\Seeders;

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
use App\Models\Teacher;
use App\Models\Term;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;


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
        $this->seedModel(User::class, 'users.json', fn ($data) => [...$data, 'password' => Hash::make('password')]);
        $this->seedModel(Staff::class, 'staff.json',
            fn ($data) => [...$data, 'password' => Hash::make($data['password'])]);
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

        $this->command->info('Farsi demo data seeding complete.');
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
        if (! File::exists($path)) {
            $this->command->error("File not found: {$path}");

            return;
        }
        $model = new $modelClass();
        $table = $model->getTable();

        $collection     = collect(json_decode(File::get($path), true));
        $categorizables = $collection->flatMap(function (array $item) use ($modelClass) {
            if (! isset($item['category_ids']) || ! is_array($item['category_ids'])) {
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

        $preparedData = $collection->map(function (array $item) use ($modifier, $table) {
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

            // Robustly encode ANY remaining array values to JSON strings.
            // This permanently solves the "Array to string conversion" error.
            foreach ($item as $key => &$value) {
                if (is_array($value)) {
                    $value = json_encode($value);
                }
            }
            unset($value); // Good practice after a loop by reference.

            return $item;
        })->all();

        if (! empty($preparedData)) {
            DB::table($table)->insert($preparedData);
        }

        if (! empty($categorizables)) {
            DB::table('categorizables')->insert($categorizables);
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
