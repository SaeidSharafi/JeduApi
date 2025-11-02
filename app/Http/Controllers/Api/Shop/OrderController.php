<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop;

use App\Contracts\ApiResponseInterface;
use App\Data\Shop\Order\OrderData;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Policies\Shop\CustomerOrderPolicy as CustomerOrderPolicy;
use Illuminate\Support\Facades\Auth;
use Spatie\LaravelData\DataCollection;

/**
 * @group Order History
 *
 * @authenticated
 */
final class OrderController extends Controller
{
    public function __construct(private readonly CustomerOrderPolicy $orderPolicy) {}

    /**
     * List authenticated user's orders.
     *
     * This endpoint returns a paginated list of all orders belonging to the authenticated user.
     * Orders are sorted by creation date (newest first).
     *
     * @responseFile storage/responses/shop/order/index.json
     */
    public function index(): ApiResponseInterface
    {
        $user = Auth::guard('user')->user();
        $orders = $user
            ->orders()
            ->with(['items.productDeliveryOption.product', 'payments'])
            ->latest()
            ->paginate(15);

        $orderData = OrderData::collect($orders->items(), DataCollection::class);
        $orderData->wrap('items');

        return response()->success([
            'orders' => $orderData->toArray(),
            'meta'   => [
                'current_page' => $orders->currentPage(),
                'last_page'    => $orders->lastPage(),
                'per_page'     => $orders->perPage(),
                'total'        => $orders->total(),
            ],
        ]);
    }

    /**
     * Show a specific order.
     *
     * This endpoint returns detailed information about a specific order.
     * Users can only view their own orders.
     *
     * @responseFile storage/responses/shop/order/show.json
     */
    public function show(string $incrementId): ApiResponseInterface
    {
        $user = Auth::guard('user')->user();
        $order = $user->orders()
            ->where('increment_id', $incrementId)
            ->with(['items.productDeliveryOption.product', 'payments'])
            ->firstOrFail();

        return response()->success(OrderData::from($order)->include('items'));
    }
}
