<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Order;

use App\Actions\Admin\Refund\RetryBundlePurchaseRevocationAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Admin\Enrollment\EnrollmentRevocationData;
use App\Http\Controllers\Controller;
use App\Models\BundlePurchase;
use App\Models\Refund;
use Illuminate\Support\Facades\Gate;

/**
 * @group Admin - Refunds
 *
 * @subgroup Bundle Refunds
 *
 * @authenticated
 */
final class RetryBundlePurchaseRevocationController extends Controller
{
    /**
     * Retry every outstanding component revocation of one Bundle Purchase.
     *
     * Components whose revocation already succeeded are never contacted again.
     * Providers without a revocation API stay visible as manual work.
     *
     * @responseFile 200 resources/responses/admin/enrollment/revocation-index.json
     * @responseFile 403 resources/responses/403.json
     */
    public function __invoke(
        BundlePurchase $bundlePurchase,
        RetryBundlePurchaseRevocationAction $action,
    ): ApiResponseInterface {
        // Bundle-level retry is part of the Bundle refund operation.
        Gate::authorize('create', Refund::class);

        return apiResponse()->success(
            data: EnrollmentRevocationData::collect($action->handle($bundlePurchase)),
            message: __('messages.enrollment.revocation_retried'),
        );
    }
}
