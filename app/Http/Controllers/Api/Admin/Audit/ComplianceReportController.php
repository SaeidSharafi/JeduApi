<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Audit;

use App\Actions\Admin\Audit\GenerateComplianceReportAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Admin\Audit\ComplianceReportRequestData;
use App\Enums\PermissionEnum;
use App\Http\Controllers\Controller;

/**
 * @group Admin - Audit Logs
 *
 * @authenticated
 *
 * APIs for generating compliance reports for financial transactions.
 */
final class ComplianceReportController extends Controller
{
    /**
     * Generate compliance report for financial transactions.
     *
     * @responseFile 200 resources/responses/admin/compliance-report/index.json
     *
     * @authenticated
     */
    public function __invoke(
        ComplianceReportRequestData $data,
        GenerateComplianceReportAction $action
    ): ApiResponseInterface {
        abort_unless(auth()->user()->can(PermissionEnum::AUDIT_COMPLIANCE_REPORTS_VIEW->value), 403);

        $report = $action->execute($data);

        return apiResponse()->success(
            data: $report,
            message: __('messages.audit.compliance_report_generated_successfully')
        );
    }
}
