<?php

declare(strict_types=1);

namespace App\Actions\Order;

use App\Data\Order\OrderCreateData;
use App\Data\Product\ProductData;
use App\Data\ProductDeliveryOption\ProductDeliveryOptionShowData;
use App\Enums\OrderItemStatusEnum;
use App\Events\OrderCreatedEvent;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductDeliveryOption;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class CreateOrderAction
{
    /**
     * Execute the action.
     */
    public function handle(OrderCreateData $data): Order
    {
        $deliveryOptionIds = collect($data->items)->pluck('product_delivery_option_id')->unique();

        // Check if the customer has already purchased any of the items before starting a transaction.
        $this->validateNoDuplicatePurchases($data->customer_id, $deliveryOptionIds);

        $order = DB::transaction(function () use ($data): Order {

            $customer = User::find($data->customer_id);
            $deliveryOptionIds = collect($data->items)->pluck('product_delivery_option_id');

            $deliveryOptions = ProductDeliveryOption::with(['product.productable', 'product.vendor', 'product.term'])
                ->find($deliveryOptionIds)
                ->keyBy('id');
            $orderItemsData = new Collection();
            $subtotal = 0;
            $totalDiscountAmount = 0;
            $taxAmount = 0;

            foreach ($data->items as $itemData) {
                $deliveryOption = $deliveryOptions->get($itemData->product_delivery_option_id);

                if (!$deliveryOption) {
                    throw new \InvalidArgumentException(
                        "Delivery option with ID {$itemData->product_delivery_option_id} not found."
                    );
                }

                $itemTotal = ($deliveryOption->price * $itemData->quantity);
                $subtotal += $itemTotal;
                $totalDiscountAmount += $itemData->discount_amount;
                $taxAmount += $itemData->tax_amount;

                $orderItemsData->push([
                    'product_delivery_option_id' => $itemData->product_delivery_option_id,
                    'quantity'                   => $itemData->quantity,
                    'name'                       => $deliveryOption->product->name,
                    'sku'                        => $deliveryOption->sku,
                    'vendor_id'                  => $deliveryOption->product->vendor_id,
                    'product_data_snapshot_json' => ProductDeliveryOptionShowData::from($deliveryOption)->toArray(),
                    'price'                      => $deliveryOption->price,
                    'discount_amount'            => $itemData->discount_amount,
                    'tax_amount'                 => $itemData->tax_amount,
                    'total'                      => ($itemTotal - $itemData->discount_amount) + $itemData->tax_amount,
                    'status'                     => OrderItemStatusEnum::ACTIVE->value,
                ]);
            }

            $grandTotal = ($subtotal - $totalDiscountAmount) + $taxAmount;

            $order = Order::create([
                'increment_id'           => Order::generateIncrementId(),
                'status'                 => $data->status,
                'customer_id'            => $data->customer_id,
                'customer_email'         => $customer->email,
                'customer_phone'         => $customer->phone,
                'customer_first_name'    => $customer->first_name,
                'customer_last_name'     => $customer->last_name,
                'customer_snapshot_json' => $customer->toArray(),
                'subtotal'               => $subtotal,
                'discount_amount'        => $totalDiscountAmount,
                'tax_amount'             => $taxAmount,
                'grand_total'            => $grandTotal,
                'applied_coupon_code'    => $data->applied_coupon_code,
                'admin_notes'            => $data->admin_notes,
            ]);

            $order->items()->createMany($orderItemsData->all());
            return $order->fresh();
        });

        OrderCreatedEvent::dispatch($order);
        return $order;
    }

    /**
     * @param  Collection<int, int>  $deliveryOptionIds
     *
     * @throws ValidationException
     */
    private function validateNoDuplicatePurchases(int $customerId, Collection $deliveryOptionIds): void
    {
        $existingItems = OrderItem::query()
            ->whereIn('product_delivery_option_id', $deliveryOptionIds)
            ->whereHas('order', function ($query) use ($customerId) {
                $query->where('customer_id', $customerId);
            })
            ->get();

        if ($existingItems->isNotEmpty()) {
            $purchasedProductNames = $existingItems->pluck('name')->implode(', ');

            throw ValidationException::withMessages([
                'items' => __('messages.order.items_already_purchased', ['products' => $purchasedProductNames]),
            ]);
        }
    }
}
