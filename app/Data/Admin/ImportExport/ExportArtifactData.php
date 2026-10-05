<?php

declare(strict_types=1);

namespace App\Data\Admin\ImportExport;

use Spatie\LaravelData\Data;

final class ExportArtifactData extends Data
{
    public function __construct(
        public string $resource,
        public string $filename,
        public string $download_url,
        public string $expires_at,
    ) {}
}
