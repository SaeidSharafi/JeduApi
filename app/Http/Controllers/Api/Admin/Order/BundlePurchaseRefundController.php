<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Order;

use App\Actions\Admin\Refund\RefundBundlePurchaseAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Admin\Refund\RefundBundlePurchaseData;
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
final class BundlePurchaseRefundController extends Controller
{
    /**
     * Refund one Bundle Purchase as a single indivisible operation.
     *
     * Every component line is refunded together. The deduction policy is
     * distributed across the component snapshots and the response exposes the
     * Bundle summary plus per-component financial and revocation calculations.
     *
     * @responseFile 201 resources/responses/admin/refund/bundle-store.json
     * @responseFile 403 resources/responses/403.json
     * @responseFile 422 resources/responses/422.json
     */
    public function store(
        RefundBundlePurchaseData $data,
        BundlePurchase $bundlePurchase,
        RefundBundlePurchaseAction $action,
    ): ApiResponseInterface {
        Gate::authorize('create', Refund::class);

        if ($data->skip_gateway) {
            Gate::authorize('skipGateway', Refund::class);
        }

        return apiResponse()->created($action->handle($bundlePurchase, $data));
    }
}
