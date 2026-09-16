<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ImportExport\ImportRowActionEnum;
use App\Models\ImportRun;
use App\Models\ImportRunRow;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ImportRunRow> */
final class ImportRunRowFactory extends Factory
{
    protected $model = ImportRunRow::class;

    public function definition(): array
    {
        return [
            'import_run_id'  => ImportRun::factory(),
            'row_number'     => 2,
            'identity_value' => '09123456789',
            'action'         => ImportRowActionEnum::CREATE,
            'is_valid'       => true,
            'errors'         => [],
            'data'           => [
                'phone'            => '09123456789',
                'first_name'       => 'علی',
                'last_name'        => 'محمدی',
                'provision_moodle' => false,
                'provision_ims'    => false,
            ],
        ];
    }
}
