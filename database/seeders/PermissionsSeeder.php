<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

final class PermissionsSeeder extends Seeder
{
    public function run(): void
    {
        Artisan::call('permissions:sync --guard=staff');
    }
}
