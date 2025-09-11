<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\PermissionRegistrar;

class PermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $pid = getmypid();
        $processLogger = Log::build([
            'driver' => 'single',
            'path' => storage_path("logs/test-debug-{$pid}.log"),
        ]);

        $processLogger->info("Seeder is running!");
        // Sync the permissions
        Artisan::call('permissions:sync --guard=staff');
    }
}
