<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\ImportExport;

use App\Actions\Admin\ImportExport\ApproveImportRunAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Admin\ImportExport\ImportApprovalRequestData;
use App\Http\Controllers\Controller;
use App\Models\ImportRun;
use Illuminate\Support\Facades\Gate;

final class ApproveImportController extends Controller
{
    public function __invoke(
        string $resource,
        string $run,
        ImportApprovalRequestData $data,
        ApproveImportRunAction $action,
    ): ApiResponseInterface {
        Gate::authorize('approve', ImportRun::class);

        return apiResponse()->success(
            $action->handle($resource, $run, $data),
            __('messages.import.approved'),
        );
    }
}
