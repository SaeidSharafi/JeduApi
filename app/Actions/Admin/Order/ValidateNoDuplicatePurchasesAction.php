<?php

declare(strict_types=1);

namespace App\Actions\Admin\Order;

use App\Enums\EnrollmentStatusEnum;
use App\Models\Enrollment;
use App\Models\ProductDeliveryOption;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class ValidateNoDuplicatePurchasesAction
{
    /**
     * Validates that the customer does not already have an active or pending enrollment
     * for the given delivery options. This prevents accidental duplicate purchases while
     * still allowing for legitimate re-purchases later.
     *
     * @param  Collection<int, ProductDeliveryOption>  $deliveryOptions
     *
     * @throws ValidationException
     */
    public function handle(User $customer, Collection $deliveryOptions): void
    {
        // Get all productable_id and productable_type pairs from cart items
        $cartProductables = $deliveryOptions->map(function ($deliveryOption) {
            $product = $deliveryOption->product;

            return [
                'productable_id'   => $product->productable_id,
                'productable_type' => $product->productable_type,
                'product_name'     => $product->name,
            ];
        });

        // Check if user has any active/pending enrollments for these Productables
        $existingEnrollments = Enrollment::query()
            ->where('customer_id', $customer->id)
            ->whereIn('enrollment_status', [
                EnrollmentStatusEnum::PENDING_PROVISIONING,
                EnrollmentStatusEnum::ACTIVE,
            ])
            ->whereHas('productDeliveryOption.product', function ($query) use ($cartProductables): void {
                $query->where(function ($q) use ($cartProductables): void {
                    foreach ($cartProductables as $productable) {
                        $q->orWhere(function ($sq) use ($productable): void {
                            $sq->where('productable_id', $productable['productable_id'])
                                ->where('productable_type', $productable['productable_type']);
                        });
                    }
                });
            })
            ->with('productDeliveryOption.product.productable')
            ->get();

        if ($existingEnrollments->isNotEmpty()) {
            // Get the productable names (Course name, Seminar name, etc.)
            $purchasedProductNames = $existingEnrollments
                ->map(function (Enrollment $e) {
                    $product = $e->productDeliveryOption->product;

                    return $product->productable->title ?? $product->name;
                })
                ->unique()
                ->sort()
                ->join(', ');

            throw ValidationException::withMessages([
                'items' => __('messages.order.items_already_purchased_or_active',
                    ['products' => $purchasedProductNames]),
            ]);
        }
    }
}
