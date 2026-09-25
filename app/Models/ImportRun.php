<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ImportExport\ImportIdentityKeyEnum;
use App\Enums\ImportExport\ImportRunStatusEnum;
use App\Enums\ImportExport\SpreadsheetResourceEnum;
use Database\Factories\ImportRunFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Immutable record of one uploaded spreadsheet and its preview result.
 */
final class ImportRun extends Model
{
    /** @use HasFactory<ImportRunFactory> */
    use HasFactory;

    protected $fillable = [
        'uuid',
        'resource',
        'identity_key',
        'status',
        'staff_id',
        'original_filename',
        'file_path',
        'file_size',
        'file_checksum',
        'rows_total',
        'rows_valid',
        'rows_invalid',
        'created_count',
        'updated_count',
        'provider_queued_count',
        'approved_at',
    ];

    /** @return HasMany<ImportRunRow, $this> */
    public function rows(): HasMany
    {
        return $this->hasMany(ImportRunRow::class);
    }

    /** @return BelongsTo<Staff, $this> */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    protected static function booted(): void
    {
        self::creating(function (self $run): void {
            $run->uuid ??= (string) Str::uuid7();
        });
    }

    protected function casts(): array
    {
        return [
            'resource'              => SpreadsheetResourceEnum::class,
            'identity_key'          => ImportIdentityKeyEnum::class,
            'status'                => ImportRunStatusEnum::class,
            'file_size'             => 'integer',
            'rows_total'            => 'integer',
            'rows_valid'            => 'integer',
            'rows_invalid'          => 'integer',
            'created_count'         => 'integer',
            'updated_count'         => 'integer',
            'provider_queued_count' => 'integer',
            'approved_at'           => 'datetime',
        ];
    }
}
