<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ImportExport\SpreadsheetResourceEnum;
use App\Models\ImportExportArtifact;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ImportExportArtifact> */
final class ImportExportArtifactFactory extends Factory
{
    protected $model = ImportExportArtifact::class;

    public function definition(): array
    {
        return [
            'resource'      => SpreadsheetResourceEnum::USERS,
            'artifact_uuid' => $this->faker->uuid,
            'path'          => $this->faker->filePath(),
            'expires_at'    => $this->faker->dateTimeBetween('+1 week', '+1 month'),
        ];
    }
}
