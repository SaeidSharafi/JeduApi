<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\Course;
use Faker\Factory;
use Illuminate\Database\Seeder;

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

        Course::query()->truncate();
        Course::factory(100)->create([
            'created_by' => Admin::query()->first()->id,
        ]);

    }
}
