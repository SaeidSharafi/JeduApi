<?php

declare(strict_types=1);

namespace App\Actions\Order\OrderItem;

use App\Data\Admin\Order\OrderItemCreateData;
use App\Data\Admin\ProductDeliveryOption\ProductDeliveryOptionShowData;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductDeliveryOption;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final readonly class CreateOrderItemAction
{
    /**
     * Execute the action.
     */
    public function handle(OrderItemCreateData $data, Order $order): OrderItem
    {

        // Check if the customer has already purchased any of the items before starting a transaction.
        $this->validateNoDuplicatePurchases($order->customer_id, $data->product_delivery_option_id);

        $orderItem = DB::transaction(function () use ($order, $data): OrderItem {

            $deliveryOption = ProductDeliveryOption::with(['product.productable', 'product.vendor', 'product.term'])
                ->find($data->product_delivery_option_id);
            if (! $deliveryOption) {
                throw new InvalidArgumentException(
                    "Delivery option with ID {$data->product_delivery_option_id} not found."
                );
            }
            $itemTotal = ($deliveryOption->price * $data->qty_ordered);

            $orderItem = $order->items()->create([
                'product_delivery_option_id' => $data->product_delivery_option_id,
                'vendor_id'                  => $deliveryOption->product->vendor_id,
                'qty_ordered'                => $data->qty_ordered,
                'name'                       => $deliveryOption->product->name,
                'sku'                        => $deliveryOption->sku,
                'product_data_snapshot_json' => ProductDeliveryOptionShowData::from($deliveryOption)->toArray(),
                'price'                      => $deliveryOption->price,
                'prepayment_amount'          => $deliveryOption->prepayment_amount,
                'discount_amount'            => $data->discount_amount,
                'tax_amount'                 => $data->tax_amount,
                'total'                      => $itemTotal,
                'payment_type'               => $data->payment_type,
            ]);

            $order->subtotal        += $itemTotal;
            $order->discount_amount += ($data->discount_amount * $data->qty_ordered);
            $order->tax_amount      += ($data->tax_amount * $data->qty_ordered);
            $order->save();

            return $orderItem->fresh();
        });

        return $orderItem;
    }

    /**
     * @param  Collection<int, int>  $deliveryOptionIds
     *
     * @throws ValidationException
     */
    private function validateNoDuplicatePurchases(int $customerId, int $deliveryOptionId): void
    {
        $existingItems = OrderItem::query()
            ->where('product_delivery_option_id', $deliveryOptionId)
            ->whereHas('order', function ($query) use ($customerId) {
                $query->where('customer_id', $customerId);
            })
            ->get();

        if ($existingItems->isNotEmpty()) {
            throw ValidationException::withMessages([
                'items' => __('messages.order.item_already_purchased'),
            ]);
        }
    }
}
