<?php

declare(strict_types=1);

namespace App\Actions\Order;

use App\Data\Order\OrderCreateData;
use App\Data\Order\OrderItemCreateData;
use App\Data\ProductDeliveryOption\ProductDeliveryOptionShowData;
use App\Enums\OrderItemPaymentTypeEnum;
use App\Enums\OrderPaymentStatusEnum;
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
            $totalAmountPaid = 0;


            foreach ($data->items as $itemData) {
                $deliveryOption = $deliveryOptions->get($itemData->product_delivery_option_id);

                if (!$deliveryOption) {
                    throw new \InvalidArgumentException(
                        "Delivery option with ID {$itemData->product_delivery_option_id} not found."
                    );
                }

                $itemTotal = ($deliveryOption->price * $itemData->qty_ordered);
                $subtotal += $itemTotal;
                $totalDiscountAmount += ($itemData->discount_amount * $itemData->qty_ordered);
                $taxAmount += ($itemData->tax_amount * $itemData->qty_ordered);

                $amountPaidForItem = match ($itemData->payment_type) {
                    OrderItemPaymentTypeEnum::PRE_PAYMENT->value => $this->getPrePaymentAmount($deliveryOption, $itemData),
                    OrderItemPaymentTypeEnum::FULL_PAYMENT->value => $this->getFullPaymentAmount($deliveryOption, $itemData),
                };
                $totalAmountPaid += $amountPaidForItem;


                $orderItemsData->push([
                    'product_delivery_option_id' => $itemData->product_delivery_option_id,
                    'vendor_id'                  => $deliveryOption->product->vendor_id,
                    'qty_ordered'                => $itemData->qty_ordered,
                    'name'                       => $deliveryOption->product->name,
                    'sku'                        => $deliveryOption->sku,
                    'product_data_snapshot_json' => ProductDeliveryOptionShowData::from($deliveryOption)->toArray(),
                    'price'                      => $deliveryOption->price,
                    'prepayment_amount'          => $deliveryOption->prepayment_amount,
                    'discount_amount'            => $itemData->discount_amount,
                    'tax_amount'                 => $itemData->tax_amount,
                    'total'                      => $itemTotal,
                    'payment_type'               => $itemData->payment_type,
                ]);
            }

            $grandTotal = ($subtotal - $totalDiscountAmount) + $taxAmount;
            $balanceDue = $grandTotal - $totalAmountPaid;
            $paymentStatus = $this->determinePaymentStatus($grandTotal, $totalAmountPaid);

            $totalItemCount = $orderItemsData->count();
            $totalQtyOrdered = $orderItemsData->sum('qty_ordered');

            $order = Order::create([
                'increment_id'                 => Order::generateIncrementId(),
                'status'                       => $data->status,
                'payment_status'               => $paymentStatus,
                'customer_id'                  => $data->customer_id,
                'customer_email'               => $customer->email,
                'customer_phone'               => $customer->phone,
                'customer_first_name'          => $customer->first_name,
                'customer_last_name'           => $customer->last_name,
                'customer_snapshot_json'       => $customer->toArray(),

                'total_item_count'             => $totalItemCount,
                'total_qty_ordered'            => $totalQtyOrdered,

                'subtotal'                     => $subtotal,
                'discount_amount'              => $totalDiscountAmount,
                'tax_amount'                   => $taxAmount,
                'grand_total'                  => $grandTotal,

                'amount_paid'                  => $totalAmountPaid,
                'amount_refunded'              => 0,
                'balance_due'                  => $balanceDue,

                'applied_coupon_code'          => $data->applied_coupon_code,
                'admin_notes'                  => $data->admin_notes,
            ]);

            $order->items()->createMany($orderItemsData->all());
            return $order->fresh();
        });

        OrderCreatedEvent::dispatch($order);
        return $order;
    }

    private function getPrePaymentAmount(ProductDeliveryOption $deliveryOption, OrderItemCreateData $itemData): int
    {
        if (is_null($deliveryOption->prepayment_amount)) {
            throw ValidationException::withMessages([
                'items' => "Product '{$deliveryOption->product->name}' does not allow pre-payment.",
            ]);
        }
        return $deliveryOption->prepayment_amount * $itemData->qty_ordered;
    }

    private function getFullPaymentAmount(ProductDeliveryOption $deliveryOption, OrderItemCreateData $itemData): int
    {
        $total = ($deliveryOption->price - $itemData->discount_amount + $itemData->tax_amount);
        return $total * $itemData->qty_ordered;
    }

    private function determinePaymentStatus(int $grandTotal, int $amountPaid): string
    {
        if ($amountPaid <= 0) {
            return OrderPaymentStatusEnum::PENDING->value;
        }
        if ($amountPaid >= $grandTotal) {
            return OrderPaymentStatusEnum::PAID->value;
        }
        return OrderPaymentStatusEnum::PARTIALLY_PAID->value;
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
