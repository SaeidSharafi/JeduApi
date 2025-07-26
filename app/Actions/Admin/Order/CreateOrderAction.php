<?php

declare(strict_types=1);

namespace App\Actions\Admin\Order;

use App\Data\Admin\Order\OrderCreateData;
use App\Data\Admin\Order\OrderItemCreateData;
use App\Data\Admin\ProductDeliveryOption\ProductDeliveryOptionShowData;
use App\Enums\EnrolmentStatusEnum;
use App\Enums\Order\OrderItemPaymentTypeEnum;
use App\Enums\Order\OrderItemStatusEnum;
use App\Enums\PublicationStatusEnum;
use App\Events\OrderCreatedEvent;
use App\Models\Enrolment;
use App\Models\Order;
use App\Models\ProductDeliveryOption;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

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

        $order = DB::transaction(function () use ($data): Order {
            $customer = User::findOrFail($data->customer_id);
            $initialDeliveryOptions = ProductDeliveryOption::with([
                'product.vendor', 'product.productable', 'product.term'
            ])
                ->whereIn('id', collect($data->items)->pluck('product_delivery_option_id'))
                ->get()
                ->keyBy('id');
            $this->validateNoDuplicatePurchases($data->customer_id, $initialDeliveryOptions->pluck('id'));

            $orderItemsData = new Collection();
            $grandTotal = 0;
            $fullValueGrandTotal = 0;
            $subtotal = 0;
            $totalDiscountAmount = 0;
            $taxAmount = 0;

            foreach ($data->items as $key => $itemData) {
                // --- Pessimistic Lock and Re-fetch for Validation ---
                // This is the single most important step for preventing race conditions.
                $deliveryOption = ProductDeliveryOption::with('product')
                    ->with('product',fn($q) => $q->with(['vendor', 'productable', 'term']))
                    ->where('id', $itemData->product_delivery_option_id)
                    ->lockForUpdate()
                    ->first();

                if (!$deliveryOption) {
                    throw new InvalidArgumentException("Delivery option with ID {$itemData->product_delivery_option_id} not found.");
                }

                $this->validateItem($key, $itemData, $deliveryOption);

                $lineItemNetTotal = $this->calculateLineItemTotal($itemData, $deliveryOption);
                $lineItemFullValue = max(0, ($deliveryOption->price - $itemData->discount_amount + $itemData->tax_amount) * $itemData->qty_ordered);
                $fullValueGrandTotal += $lineItemFullValue;

                $grandTotal += $lineItemNetTotal;

                // Aggregate values for storing on the Order model for display/reporting.
                $subtotal += $deliveryOption->price * $itemData->qty_ordered;
                $totalDiscountAmount += $itemData->discount_amount * $itemData->qty_ordered;
                $taxAmount += $itemData->tax_amount * $itemData->qty_ordered;

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
                    'total'                      => $lineItemNetTotal,
                    'prepayment_amount'          => $deliveryOption->prepayment_amount,
                    'payment_type'               => $itemData->payment_type,
                    'status'                     => OrderItemStatusEnum::PENDING->value,
                ]);
            }

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
                'full_value_grand_total' => $fullValueGrandTotal,
                'applied_coupon_code'    => $data->applied_coupon_code,
                'admin_notes'            => $data->admin_notes,
            ]);

            $order->items()->createMany($orderItemsData->all());
            $order->refresh();

            $order->load('items');
            $order->items->each(function ($item) use ($data) {
                Enrolment::create([
                    'order_id'                   => $item->order_id,
                    'order_item_id'              => $item->id,
                    'customer_id'                => $data->customer_id,
                    'product_delivery_option_id' => $item['product_delivery_option_id'],
                    'enrollment_status'          => EnrolmentStatusEnum::PENDING_PROVISIONING,
                    'access_start_date'          => null,
                    'access_end_date'            => null,
                    'external_enrollment_id'     => null,
                    'provisioning_data'          => [],
                    'notes'                      => null,
                ]);
            });

            return $order->fresh();
        });

        OrderCreatedEvent::dispatch($order);

        return $order->load('items', 'payments', 'enrolments');
    }

    /**
     * Groups all validation logic for a single item.
     *
     * @throws ValidationException
     */
    private function validateItem(int $key, object $itemData, ProductDeliveryOption $deliveryOption): void
    {
        if ($deliveryOption->status !== PublicationStatusEnum::PUBLISHED
            || $deliveryOption->product->status !== PublicationStatusEnum::PUBLISHED
        ) {
            throw ValidationException::withMessages([
                "items.{$key}" => __('messages.order.item_not_available',
                    ['product' => $deliveryOption->name]),
            ]);
        }
        if ($deliveryOption->capacity !== null) {
            $enrolledCount = $deliveryOption->enrolments()
                ->where('enrollment_status', '!=', EnrolmentStatusEnum::CANCELLED)->count();
            if (($enrolledCount + $itemData->qty_ordered) > $deliveryOption->capacity) {
                $available = $deliveryOption->capacity - $enrolledCount;
                throw ValidationException::withMessages([
                    "items.{$key}" => __('messages.order.insufficient_capacity', [
                        'product'   => $deliveryOption->name,
                        'available' => $available,
                    ]),
                ]);
            }
        }

        if (!$deliveryOption->allow_multiple_quantity && $itemData->qty_ordered > 1) {
            throw ValidationException::withMessages([
                "items.{$key}" => __('messages.order.quantity_not_allowed',
                    ['product' => $deliveryOption->name]),
            ]);
        }

        // --- Validate Payment Intent ---
        // If admin chose 'pre_payment', make sure the product allows it.
        if ($itemData->payment_type === 'pre_payment'
            && !$deliveryOption->is_prepayment_available
        ) {
            throw ValidationException::withMessages([
                "items.{$key}" => __('messages.order.prepayment_not_available', [
                    'product' => $deliveryOption->name,
                ]),
            ]);
        }
        if ($itemData->discount_amount > $deliveryOption->price) {
            throw ValidationException::withMessages([
                "items.{$key}" => __('messages.order.discount_exceeds_price',
                    ['product' => $deliveryOption->name]),
            ]);
        }

        if ($itemData->payment_type === OrderItemPaymentTypeEnum::PRE_PAYMENT->value && $itemData->discount_amount > 0) {
            throw ValidationException::withMessages([
                "items.{$key}" => __('messages.order.discount_not_allowed_for_prepayment'),
            ]);
        }
    }

    /**
     * Validates that the customer does not already have an active or pending enrollment
     * for the given delivery options. This prevents accidental duplicate purchases while
     * still allowing for legitimate re-purchases later.
     *
     * @param  Collection<int, int>  $deliveryOptionIds
     *
     * @throws ValidationException
     */
    private function validateNoDuplicatePurchases(int $customerId, Collection $deliveryOptionIds): void
    {
        $existingEnrollments = Enrolment::query()
            ->where('customer_id', $customerId)
            ->whereIn('product_delivery_option_id', $deliveryOptionIds)
            ->whereIn('enrollment_status', [
                EnrolmentStatusEnum::PENDING_PROVISIONING,
                EnrolmentStatusEnum::ACTIVE,
            ])
            ->with('productDeliveryOption.product') // Load for better error message
            ->get();

        if ($existingEnrollments->isNotEmpty()) {
            $purchasedProductNames = $existingEnrollments
                ->map(fn(Enrolment $e) => $e->productDeliveryOption->name)
                ->unique()
                ->implode(', ');

            throw ValidationException::withMessages([
                'items' => __('messages.order.items_already_purchased_or_active',
                    ['products' => $purchasedProductNames]),
            ]);
        }
    }

    private function calculateLineItemTotal(OrderItemCreateData $orderItem, ProductDeliveryOption $deliveryOption): int
    {
        if ($orderItem->payment_type === OrderItemPaymentTypeEnum::FULL_PAYMENT->value){
            return max(0, ($deliveryOption->price - $orderItem->discount_amount + $orderItem->tax_amount) * $orderItem->qty_ordered);
        }

        return ($deliveryOption->prepayment_amount) * $orderItem->qty_ordered;

    }
}
