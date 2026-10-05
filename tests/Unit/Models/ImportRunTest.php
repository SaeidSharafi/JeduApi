<?php

declare(strict_types=1);

use App\Models\ImportRun;
use App\Models\ImportRunRow;
use App\Models\Staff;

it('serializes import run state', function (): void {
    $run = ImportRun::factory()->create()->fresh();

    expect(array_keys($run->toArray()))->toEqualCanonicalizing([
        'id', 'uuid', 'resource', 'identity_key', 'status', 'staff_id',
        'original_filename', 'file_path', 'file_size', 'file_checksum',
        'rows_total', 'rows_valid', 'rows_invalid', 'created_count', 'updated_count',
        'provider_queued_count', 'approved_at', 'error_report_path', 'error_report_fingerprint',
        'artifacts_expires_at', 'artifacts_redacted_at', 'artifacts_cleanup_retry_after',
        'created_at', 'updated_at',
    ]);
});

it('has its import rows', function (): void {
    $run = ImportRun::factory()->create();
    $row = ImportRunRow::factory()->for($run)->create();

    expect($run->rows->sole())->toBeInstanceOf(ImportRunRow::class)
        ->id->toBe($row->id);
});

it('belongs to its staff member', function (): void {
    $staff = Staff::factory()->create();
    $run   = ImportRun::factory()->for($staff, 'staff')->create();

    expect($run->staff)->toBeInstanceOf(Staff::class)
        ->id->toBe($staff->id);
});
