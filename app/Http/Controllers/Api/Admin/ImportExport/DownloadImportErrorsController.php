<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\ImportExport;

use App\Actions\Admin\ImportExport\DownloadImportErrorsAction;
use App\Http\Controllers\Controller;
use App\Models\ImportRun;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class DownloadImportErrorsController extends Controller
{
    public function __invoke(string $resource, string $run, DownloadImportErrorsAction $action): StreamedResponse
    {
        Gate::authorize('viewResults', ImportRun::class);

        return $action->handle($resource, $run);
    }
}
