<?php

declare(strict_types=1);

namespace App\Actions\Order;

use App\Data\Order\OrderCreateData;
use App\Data\ProductDeliveryOption\ProductDeliveryOptionShowData;
use App\Events\OrderCreatedEvent;
use App\Models\Order;
use App\Models\ProductDeliveryOption;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Models\OrderItem;

// Make sure this is imported

final readonly class CreateOrderAction
{
    /**
     * Creates an Order (a bill).
     * This action's only responsibility is to record what a customer is buying.
     * It does NOT handle payments. Payments are applied separately in a different action.
     */
    public function handle(OrderCreateData $data): Order
    {
        $deliveryOptionIds = collect($data->items)->pluck('product_delivery_option_id')->unique();
        $this->validateNoDuplicatePurchases($data->customer_id, $deliveryOptionIds);

        $order = DB::transaction(function () use ($data): Order {
            $customer = User::findOrFail($data->customer_id);
            $deliveryOptionIds = collect($data->items)->pluck('product_delivery_option_id');
            $deliveryOptions = ProductDeliveryOption::with(['product.vendor', 'product.productable', 'product.term'])
                ->find($deliveryOptionIds)
                ->keyBy('id');

            $orderItemsData = new Collection();
            $subtotal = 0;
            $totalDiscountAmount = 0;
            $taxAmount = 0;

            foreach ($data->items as $key => $itemData) {
                $deliveryOption = $deliveryOptions->get($itemData->product_delivery_option_id);
                if (!$deliveryOption) {
                    throw new \InvalidArgumentException("Delivery option with ID {$itemData->product_delivery_option_id} not found.");
                }

                // --- Validate Payment Intent ---
                // If admin chose 'pre_payment', make sure the product allows it.
                if ($itemData->payment_type === 'pre_payment'
                    && !$deliveryOption->is_prepayment_available
                ) {
                    throw ValidationException::withMessages([
                        "items.{$key}" => __('messages.order.prepayment_not_available',[
                            'product' => $deliveryOption->product->name,
                        ]),
                    ]);
                }

                $itemTotal = ($deliveryOption->price * $itemData->qty_ordered);
                $subtotal += $itemTotal;
                $totalDiscountAmount += ($itemData->discount_amount * $itemData->qty_ordered);
                $taxAmount += ($itemData->tax_amount * $itemData->qty_ordered);

                $orderItemsData->push([
                    'product_delivery_option_id' => $itemData->product_delivery_option_id,
                    'vendor_id'                  => $deliveryOption->product->vendor_id,
                    'qty_ordered'                => $itemData->qty_ordered,
                    'name'                       => $deliveryOption->product->name,
                    'sku'                        => $deliveryOption->sku,
                    'product_data_snapshot_json' => ProductDeliveryOptionShowData::from($deliveryOption)->toArray(),
                    'price'                      => $deliveryOption->price,
                    'discount_amount'            => $itemData->discount_amount,
                    'tax_amount'                 => $itemData->tax_amount,
                    'total'                      => $itemTotal,
                    'prepayment_amount'          => $deliveryOption->prepayment_amount,
                    'payment_type'               => $itemData->payment_type,
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
                'total_item_count'       => $orderItemsData->count(),
                'total_qty_ordered'      => $orderItemsData->sum('qty_ordered'),
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
        // Return the order with its relations so the controller can access them.
        return $order->load('items', 'payments');
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
