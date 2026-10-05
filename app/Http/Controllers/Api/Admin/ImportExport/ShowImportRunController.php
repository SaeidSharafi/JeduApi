<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\ImportExport;

use App\Actions\Admin\ImportExport\GetImportRunAction;
use App\Contracts\ApiResponseInterface;
use App\Http\Controllers\Controller;
use App\Models\ImportRun;
use Illuminate\Support\Facades\Gate;

final class ShowImportRunController extends Controller
{
    public function __invoke(string $resource, string $run, GetImportRunAction $action): ApiResponseInterface
    {
        Gate::authorize('viewResults', ImportRun::class);

        return apiResponse()->success($action->handle($resource, $run));
    }
}
