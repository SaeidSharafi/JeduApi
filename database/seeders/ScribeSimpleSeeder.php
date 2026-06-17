<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\PermissionEnum;
use App\Models\Category;
use App\Models\Course;
use App\Models\DigitalAsset;
use App\Models\Order;
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

final class ScribeSimpleSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(DemoSeeder::class);
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
