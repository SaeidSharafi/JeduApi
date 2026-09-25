<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ImportExport\ImportRowActionEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One validated spreadsheet row of an import run.
 *
 * Row data is the resource owned, normalized representation consumed by
 * approval; credentials are never stored here.
 */
final class ImportRunRow extends Model
{
    /** @use HasFactory<\Database\Factories\ImportRunRowFactory> */
    use HasFactory;

    protected $fillable = [
        'import_run_id',
        'row_number',
        'identity_value',
        'target_resource_id',
        'action',
        'is_valid',
        'errors',
        'data',
    ];

    /** @return BelongsTo<ImportRun, $this> */
    public function importRun(): BelongsTo
    {
        return $this->belongsTo(ImportRun::class);
    }

    protected function casts(): array
    {
        return [
            'action'     => ImportRowActionEnum::class,
            'is_valid'   => 'boolean',
            'row_number' => 'integer',
            'errors'     => 'array',
            'data'       => 'array',
        ];
    }
}
