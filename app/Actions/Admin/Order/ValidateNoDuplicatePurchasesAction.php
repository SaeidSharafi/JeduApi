<?php

declare(strict_types=1);

namespace App\Actions\Admin\Order;

use App\Enums\EnrollmentStatusEnum;
use App\Enums\Product\ProductableEnum;
use App\Models\Enrollment;
use App\Models\Product;
use App\Models\ProductDeliveryOption;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class ValidateNoDuplicatePurchasesAction
{
    /**
     * Validate ownership and overlap by underlying Productable identity.
     *
     * @param  Collection<int, ProductDeliveryOption>  $deliveryOptions
     *
     * @throws ValidationException
     */
    public function handle(?User $customer, Collection $deliveryOptions): void
    {
        $selectedProductables = [];
        $overlappingNames     = [];

        foreach ($deliveryOptions as $deliveryOption) {
            $offeringProductables = $this->expandProductables($deliveryOption);

            foreach ($offeringProductables as $identity => $productable) {
                if (isset($selectedProductables[$identity])) {
                    $overlappingNames[] = $productable['product_name'];
                }

                $selectedProductables[$identity] = $productable;
            }
        }

        if ($overlappingNames !== []) {
            $this->throwAlreadyPurchased(collect($overlappingNames));
        }

        if (! $customer || $selectedProductables === []) {
            return;
        }

        $existingEnrollments = Enrollment::query()
            ->where('customer_id', $customer->id)
            ->whereIn('enrollment_status', EnrollmentStatusEnum::occupyingStatuses())
            ->whereHas('productDeliveryOption.product', function ($query) use ($selectedProductables): void {
                $query->where(function ($productQuery) use ($selectedProductables): void {
                    foreach ($selectedProductables as $productable) {
                        $productQuery->orWhere(function ($identityQuery) use ($productable): void {
                            $identityQuery->where('productable_id', $productable['productable_id'])
                                ->where('productable_type', $productable['productable_type']);
                        });
                    }
                });
            })
            ->with('productDeliveryOption.product.productable')
            ->get();

        if ($existingEnrollments->isNotEmpty()) {
            $purchasedProductNames = $existingEnrollments
                ->map(fn (Enrollment $enrollment): string => $this->productName($enrollment->productDeliveryOption->product));

            $this->throwAlreadyPurchased($purchasedProductNames);
        }
    }

    /**
     * @return Collection<string, array{productable_id: int, productable_type: string, product_name: string}>
     */
    private function expandProductables(ProductDeliveryOption $deliveryOption): Collection
    {
        $deliveryOption->loadMissing('product');

        if ($deliveryOption->product->productable_type !== ProductableEnum::BUNDLE->value) {
            return collect([$this->productableIdentity($deliveryOption->product)])
                ->keyBy(fn (array $productable): string => $this->identityKey($productable));
        }

        $deliveryOption->loadMissing('bundleComponents.product');

        return $deliveryOption->bundleComponents
            ->map(fn (ProductDeliveryOption $component): array => $this->productableIdentity($component->product))
            ->keyBy(fn (array $productable): string => $this->identityKey($productable));
    }

    /** @return array{productable_id: int, productable_type: string, product_name: string} */
    private function productableIdentity(Product $product): array
    {
        return [
            'productable_id'   => (int) $product->productable_id,
            'productable_type' => $product->productable_type,
            'product_name'     => $this->productName($product),
        ];
    }

    /** @param array{productable_id: int, productable_type: string} $productable */
    private function identityKey(array $productable): string
    {
        return $productable['productable_type'].':'.$productable['productable_id'];
    }

    private function productName(Product $product): string
    {
        return $product->name;
    }

    /** @param Collection<int, string> $productNames */
    private function throwAlreadyPurchased(Collection $productNames): never
    {
        throw ValidationException::withMessages([
            'items' => __('messages.order.items_already_purchased_or_active', [
                'products' => $productNames->unique()->sort()->join(', '),
            ]),
        ]);
    }
}
