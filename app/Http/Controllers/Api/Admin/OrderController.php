<?php

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Order\CreateOrderAction;
use App\Actions\Order\DeleteOrderAction;
use App\Actions\Order\UpdateOrderAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Order\OrderCreateData;
use App\Data\Order\OrderData;
use App\Data\Order\OrderListItemData;
use App\Data\Order\OrderUpdateData;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @group Admin - Orders
 *
 * APIs for managing orders in the admin panel.
 *
 * @authenticated
 */
class OrderController extends Controller
{
    /**
     * Display a listing of the orders.
     *
     * @queryParam filter[status] string Filter by order status. Example: completed
     * @queryParam filter[payment_status] string Filter by payment status. Example: paid
     * @queryParam filter[customer_first_name] string Filter by customer's first name. Example: John
     * @queryParam filter[customer_last_name] string Filter by customer's last name. Example: Doe
     * @queryParam filter[customer_email] string Filter by customer's email. Example: John@example.com
     * @queryParam filter[customer_phone] string Filter by customer's phone number. Example: +1234567890
     * @queryParam filter[increment_id] string Filter by order increment ID. Example: 1001
     * @queryParam filter[product_name] string Filter by item name in the order. Example: Widget
     * @queryParam filter[product_sku] string Filter by item SKU in the order. Example: SKU123
     *
     * @queryParam sort string Sort by a field. Allowed values: created_at. Prefix with '-' for descending order (e.g.,
     *     -created_at).
     * @queryParam page integer Page number for pagination. Example: 2
     * @queryParam per_page integer Number of results per page. Example: 15
     *
     * @responseFile 200 responses/order/index.json
     *
     */
    public function index(): ApiResponseInterface
    {
        Gate::authorize('view-any', Order::class);
        $orders = QueryBuilder::for(Order::class)
            ->allowedFilters([
                'customer_first_name',
                'customer_last_name', 'customer_email',
                'customer_phone',
                'increment_id',
                AllowedFilter::exact('status'),
                AllowedFilter::exact('customer_id'),
                AllowedFilter::exact('payment_status','payments.status'),
                AllowedFilter::partial('product_name', 'items.name'),
                AllowedFilter::partial('product_sku', 'items.sku'),
            ])
            ->allowedSorts(['created_at','payments.status'])
            ->defaultSort('-created_at')
            ->with(['items.vendor','payments'])
            ->paginate(request()->integer('per_page', 15));

        return response()->success(OrderListItemData::collect($orders));
    }

    /**
     * Store a newly created order.
     *
     * @responseFile 201 responses/order/show.json
     * @responseFile 422 responses/422.json
     * @responseFile 403 responses/403.json
     */
    public function store(OrderCreateData $data, CreateOrderAction $action): ApiResponseInterface
    {
        Gate::authorize('create', Order::class);
        $order = $action->handle($data);
        $order->load('items.vendor');
        return response()->created(OrderData::from($order));
    }

    /**
     * Display the specified order.
     *
     * @responseFile 200 responses/order/show.json
     * @responseFile 404 responses/404.json
     * @responseFile 422 responses/422.json
     */
    public function show(Order $order): ApiResponseInterface
    {
        Gate::authorize('view', $order);
        $order->load('items.vendor');
        return response()->success(OrderData::from($order));
    }

    /**
     * Update the specified order.
     *
     * @responseFile 200 responses/order/show.json
     * @responseFile 404 responses/404.json
     * @responseFile 422 responses/422.json
     * @responseFile 403 responses/403.json
     */
    public function update(OrderUpdateData $data, Order $order, UpdateOrderAction $action): ApiResponseInterface
    {
        Gate::authorize('update', $order);
        $order = $action->handle($data, $order);
        $order->load('items.vendor');
        return response()->success(OrderData::from($order));
    }

    /**
     * Remove the specified order.
     *
     * @response 204
     * @responseFile 404 responses/404.json
     * @responseFile 403 responses/403.json
     */
    public function destroy(Order $order, DeleteOrderAction $action): JsonResponse
    {
        Gate::authorize('delete', $order);
        $action->handle($order);
        return response()->noContentJson();
    }
}
