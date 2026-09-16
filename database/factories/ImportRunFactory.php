<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ImportExport\ImportIdentityKeyEnum;
use App\Enums\ImportExport\ImportRunStatusEnum;
use App\Enums\ImportExport\SpreadsheetResourceEnum;
use App\Models\ImportRun;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ImportRun> */
final class ImportRunFactory extends Factory
{
    protected $model = ImportRun::class;

    public function definition(): array
    {
        return [
            'resource'          => SpreadsheetResourceEnum::USERS,
            'identity_key'      => ImportIdentityKeyEnum::PHONE,
            'status'            => ImportRunStatusEnum::PREVIEWED,
            'staff_id'          => Staff::factory(),
            'original_filename' => 'users.xlsx',
            'file_path'         => 'imports/'.fake()->uuid().'/users.xlsx',
            'file_size'         => 2048,
            'rows_total'        => 0,
            'rows_valid'        => 0,
            'rows_invalid'      => 0,
        ];
    }
}
