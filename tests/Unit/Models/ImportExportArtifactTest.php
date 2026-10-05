<?php

declare(strict_types=1);

use App\Models\ImportExportArtifact;

it('serializes the persisted export inventory fields', function (): void {
    $artifact = ImportExportArtifact::factory()->create();

    expect($artifact->toArray())
        ->toHaveKeys(['resource', 'artifact_uuid', 'path', 'expires_at']);
});
