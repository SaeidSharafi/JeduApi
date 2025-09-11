<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class PermissionsSeeder extends Seeder
{
    public function run(): void
    {
        Artisan::call('permissions:sync --guard=staff');
    }
}
