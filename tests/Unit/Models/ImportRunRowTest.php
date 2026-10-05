<?php

declare(strict_types=1);

use App\Models\ImportRun;
use App\Models\ImportRunRow;

it('serializes preview and committed row state', function (): void {
    $row = ImportRunRow::factory()->create()->fresh();

    expect(array_keys($row->toArray()))->toEqualCanonicalizing([
        'id', 'import_run_id', 'row_number', 'identity_value', 'target_resource_id', 'action',
        'is_valid', 'errors', 'data', 'providers', 'local_resource_id', 'local_result_data',
        'created_at', 'updated_at',
    ]);
});

it('belongs to its import run', function (): void {
    $run = ImportRun::factory()->create();
    $row = ImportRunRow::factory()->for($run)->create();

    expect($row->importRun)->toBeInstanceOf(ImportRun::class);
    expect($row->importRun->id)->toBe($run->id);
});
