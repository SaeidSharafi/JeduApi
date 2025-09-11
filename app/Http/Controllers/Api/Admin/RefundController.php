<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Admin\Refund\CreateRefundAction;
use App\Actions\Admin\Refund\DeletePendingRefundAction;
use App\Actions\Admin\Refund\UpdateRefundAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Admin\Refund\RefundCreateData;
use App\Data\Admin\Refund\RefundData;
use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Models\Refund;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * @group Admin - Refunds
 *
 * APIs for managing refunds in the admin panel.
 */
final class RefundController extends Controller
{
    /**
     * Display a listing of the refunds.
     *
     * @responseFile 200 responses/refund/index.json
     * @responseFile 404 responses/404.json
     * @responseFile 403 responses/403.json
     */
    public function index(OrderItem $orderItem): ApiResponseInterface
    {
        Gate::authorize('view-any', Refund::class);
        $refunds = Refund::with(['order', 'customer'])
            ->where('order_item_id', $orderItem->id)
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->success(RefundData::collect($refunds));
    }

    /**
     * Store a newly created refund.
     *
     * @responseFile 201 responses/refund/show.json
     * @responseFile 403 responses/403.json
     * @responseFile 422 responses/refund/store-422.json
     */
    public function store(RefundCreateData $data, OrderItem $orderItem, CreateRefundAction $action): ApiResponseInterface
    {
        Gate::authorize('create', Refund::class);
        $refund = $action->handle($data, $orderItem);

        return response()->created(RefundData::from($refund));
    }

    /**
     * Display the specified refund.
     *
     * @responseFile 200 responses/refund/show.json
     * @responseFile 403 responses/403.json
     * @responseFile 404 responses/404.json
     */
    public function show(OrderItem $orderItem, Refund $refund)
    {
        Gate::authorize('view', $refund);

        return response()->success(RefundData::from($refund));
    }

    /**
     * Update the specified refund.
     *
     * @responseFile 200 responses/refund/show.json
     * @responseFile 403 responses/403.json
     * @responseFile 422 responses/refund/update-422.json
     */
    public function update(RefundCreateData $data, OrderItem $orderItem, Refund $refund, UpdateRefundAction $action): ApiResponseInterface
    {
        Gate::authorize('update', $refund);
        $updatedRefund = $action->handle($refund, $data);

        return response()->success(RefundData::from($updatedRefund));
    }

    /**
     * Remove the specified refund.
     *
     * @response 204
     *
     * @responseFile 403 responses/403.json
     * @responseFile 404 responses/404.json
     * @responseFile 422 responses/refund/delete-422.json
     */
    public function destroy(OrderItem $orderItem, Refund $refund, DeletePendingRefundAction $action): JsonResponse
    {
        Gate::authorize('delete', $refund);
        $action->handle($refund);

        return response()->noContentJson();
    }
}
