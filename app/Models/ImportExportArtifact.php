<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ImportExportArtifactFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class ImportExportArtifact extends Model
{
    /** @use HasFactory<ImportExportArtifactFactory> */
    use HasFactory;

    protected $fillable = ['resource', 'artifact_uuid', 'path', 'expires_at', 'cleanup_retry_after'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'cleanup_retry_after' => 'datetime'];
    }
}
