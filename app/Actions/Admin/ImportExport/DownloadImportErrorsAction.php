<?php

declare(strict_types=1);

namespace App\Actions\Admin\ImportExport;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class DownloadImportErrorsAction
{
    public function __construct(private BuildImportErrorReportAction $builder) {}

    public function handle(string $resource, string $uuid): StreamedResponse
    {
        $path = $this->builder->handle($resource, $uuid);
        abort_if($path === null, 404);
        $disk = Storage::disk('local');
        abort_unless($disk->exists($path), 404);

        $response = $disk->download($path, "{$resource}-import-errors.xlsx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }
}
