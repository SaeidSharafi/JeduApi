<?php

declare(strict_types=1);

namespace App\Actions\Admin\Order;

use App\Data\Admin\Discounts\OrderContextData;
use App\Data\Admin\Order\OrderCreateData;
use App\Data\Admin\ProductDeliveryOption\ProductDeliveryOptionShowData;
use App\Enums\Content\PublicationStatusEnum;
use App\Enums\EnrollmentStatusEnum;
use App\Enums\Order\OrderItemStatusEnum;
use App\Events\OrderCreatedEvent;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\ProductDeliveryOption;
use App\Models\User;
use App\Services\Discounts\OrderCalculationService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// Make sure this is imported

final readonly class CreateOrderAction
{
    public function __construct(
        private OrderCalculationService $orderCalculationService,
        private ValidateNoDuplicatePurchasesAction $validateNoDuplicatePurchases,
    ) {}

    /**
     * Creates an Order (a bill).
     * This action's only responsibility is to record what a customer is buying.
     * It does NOT handle payments. Payments are applied separately in a different action.
     */
    public function handle(OrderCreateData $data): Order
    {
        $context                  = $this->orderCalculationService->calculate($data);
        $initialDeliveryOptionIds = $context->items->pluck('product_delivery_option.id');
        $deliveryOptions       = ProductDeliveryOption::query()
            ->whereIn('id', $initialDeliveryOptionIds)
            ->with('product')
            ->get();
        $this->validateNoDuplicatePurchases->handle($context->customer, $deliveryOptions);

        $order = DB::transaction(function () use ($data, $context): Order {
            $originalInputItems = collect($data->items)->keyBy('product_delivery_option_id');
            $orderItemsData     = new Collection();

            foreach ($context->items as $key => $calculatedItem) {
                // --- PESSIMISTIC LOCK AND RE-FETCH (PRESERVED) ---
                // This critical concurrency check remains unchanged.
                $deliveryOption = ProductDeliveryOption::query()
                    ->with([
                        'product.vendor', 'product.productable', 'product.term',
                    ])
                    ->where('id', $calculatedItem->product_delivery_option->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                // --- VALIDATION LOGIC (PRESERVED) ---
                // We find the original input data for this item to validate against the user's intent.
                $originalItemData = $originalInputItems->get($deliveryOption->id);
                $this->validateItem($key, $originalItemData, $deliveryOption);

                // =================================================================
                // STEP 3: BUILD THE ORDER ITEM DATA FROM THE CONTEXT
                // All manual calculation is removed. We just map the results.
                // =================================================================
                $orderItemsData->push([
                    'product_delivery_option_id'    => $calculatedItem->product_delivery_option->id,
                    'vendor_id'                     => $deliveryOption->product->vendor_id,
                    'qty_ordered'                   => $calculatedItem->qty,
                    'name'                          => $deliveryOption->product->name,
                    'sku'                           => $deliveryOption->sku,
                    'product_data_snapshot_json'    => ProductDeliveryOptionShowData::from($deliveryOption)->toArray(),
                    'payment_type'                  => $calculatedItem->payment_type,
                    'status'                        => OrderItemStatusEnum::PENDING->value,
                    'price'                         => $calculatedItem->product_delivery_option->price,
                    'discount_amount'               => $calculatedItem->discount_amount,
                    'total'                         => $calculatedItem->total, // This is the final value AFTER discount
                    'applied_discount_details_json' => ! empty($calculatedItem->applied_discount_details)
                        ? $calculatedItem->applied_discount_details
                        : null,
                    'prepayment_amount' => $deliveryOption->prepayment_amount,
                    'tax_amount'        => 0, // Placeholder
                ]);
            }
            $grandTotal = $context->calculateGrandTotal();
            $order      = Order::create([
                'increment_id'           => Order::generateIncrementId(),
                'status'                 => $data->status, // This can be an initial status from the form
                'customer_id'            => $context->customer->id,
                'customer_email'         => $context->customer->email,
                'customer_phone'         => $context->customer->phone,
                'customer_first_name'    => $context->customer->first_name,
                'customer_last_name'     => $context->customer->last_name,
                'customer_snapshot_json' => $context->customer->toArray(),
                'total_item_count'       => $context->items->count(),
                'total_qty_ordered'      => $context->items->sum('qty'),

                // --- TOTALS CALCULATED FROM CONTEXT (SINGLE SOURCE OF TRUTH) ---
                'grand_total'            => $grandTotal, // The final, authoritative bill amount from context
                'full_value_grand_total' => $context->subtotal_all_items,
                'subtotal'               => $context->calculateSubtotal(),
                'discount_amount'        => $context->calculateTotalDiscount(),
                'tax_amount'             => 0, // Placeholder

                // --- AUDIT TRAIL FOR CART DISCOUNTS ---
                'applied_cart_discounts_json' => ! empty($context->applied_cart_discounts)
                    ? $context->applied_cart_discounts
                    : null,

                'applied_coupon_code' => $context->triggered_by_coupon_code, // Get code from context
                'admin_notes'         => $data->admin_notes,
            ]);

            $order->items()->createMany($orderItemsData->all());
            $order->refresh();

            // --- ENROLLMENT CREATION LOGIC (PRESERVED) ---
            // This logic is unchanged as it depends only on the created order items.
            $order->load('items');
            $order->items->each(function ($item) use ($context): void {
                Enrollment::create([
                    'order_id'                   => $item->order_id,
                    'order_item_id'              => $item->id,
                    'customer_id'                => $context->customer->id,
                    'product_delivery_option_id' => $item->product_delivery_option_id,
                    'enrollment_status'          => EnrollmentStatusEnum::AWAITING_PAYMENT,
                ]);
            });

            return $order->fresh();

        });

        if ($context->evaluating_promotion) {
            $this->incrementUsageCounts($context);
        }
        OrderCreatedEvent::dispatch($order);

        return $order->load('items', 'payments', 'enrollments');
    }

    private function incrementUsageCounts(OrderContextData $context): void
    {
        $promotion = $context->evaluating_promotion;

        $promotion->increment('total_usage_count');

        // Assuming coupon code is passed into the context by the calculation service
        if ($context->triggered_by_coupon_code) {
            $promotion->coupons()
                ->where('code', $context->triggered_by_coupon_code)
                ->increment('usage_count');
        }
    }

    /**
     * Groups all validation logic for a single item.
     *
     * @throws ValidationException
     */
    private function validateItem(int $key, object $itemData, ProductDeliveryOption $deliveryOption): void
    {
        if ($deliveryOption->status             !== PublicationStatusEnum::PUBLISHED
            || $deliveryOption->product->status !== PublicationStatusEnum::PUBLISHED
        ) {
            throw ValidationException::withMessages([
                "items.{$key}" => __('messages.order.item_not_available',
                    ['product' => $deliveryOption->name]),
            ]);
        }
        if ($deliveryOption->capacity !== null) {
            $enrolledCount = $deliveryOption->enrolled_count;
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

        if (! $deliveryOption->allow_multiple_quantity && $itemData->qty_ordered > 1) {
            throw ValidationException::withMessages([
                "items.{$key}" => __('messages.order.quantity_not_allowed',
                    ['product' => $deliveryOption->name]),
            ]);
        }

        // --- Validate Payment Intent ---
        // If admin chose 'pre_payment', make sure the product allows it.
        if ($itemData->payment_type === 'pre_payment'
            && ! $deliveryOption->is_prepayment_available
        ) {
            throw ValidationException::withMessages([
                "items.{$key}" => __('messages.order.prepayment_not_available', [
                    'product' => $deliveryOption->name,
                ]),
            ]);
        }
    }
}
