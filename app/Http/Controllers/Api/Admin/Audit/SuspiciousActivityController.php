<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Audit;

use App\Actions\Admin\Audit\DetectSuspiciousActivityAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Admin\Audit\SuspiciousActivityRequestData;
use App\Enums\PermissionEnum;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Gate;

final class SuspiciousActivityController extends Controller
{
    /**
     * Detect and list suspicious activities in wallet transactions
     */
    public function __invoke(
        SuspiciousActivityRequestData $data,
        DetectSuspiciousActivityAction $action
    ): ApiResponseInterface {
        abort_unless(auth()->user()->can(PermissionEnum::AUDIT_SUSPICIOUS_ACTIVITY_VIEW->value), 403);

        $activities = $action->handle($data);

        return response()->success(
            data: $activities,
            message: __('messages.audit.suspicious_activity_detected_successfully')
        );
    }
}
