<?php

declare(strict_types=1);

namespace App\Actions\Shop\Student;

use App\Data\Shop\Student\ProductAccessData;
use App\Enums\EnrollmentStatusEnum;
use App\Enums\Product\ProductableEnum;
use App\Models\DigitalAsset;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class LookupProductAccessAction
{
    /**
     * Find the customer's active or suspended enrollments for exact non-Bundle PDO UUIDs.
     * Bundle products have no standalone enrollment; their component enrollments remain valid access.
     *
     * @param  array<int, string>  $optionUuids
     * @return Collection<int, ProductAccessData>
     */
    public function handle(User $customer, array $optionUuids): Collection
    {
        return Enrollment::query()
            ->where('customer_id', $customer->getKey())
            ->whereIn('enrollment_status', [EnrollmentStatusEnum::ACTIVE, EnrollmentStatusEnum::SUSPENDED])
            ->whereHas('productDeliveryOption', function (Builder $query) use ($optionUuids): void {
                $query->whereIn('uuid', $optionUuids)
                    ->whereHas('product', static function (Builder $query): void {
                        $query->whereIn('productable_type', [
                            ProductableEnum::COURSE->value,
                            ProductableEnum::SEMINAR->value,
                            ProductableEnum::DIGITAL_ASSET->value,
                        ]);
                    });
            })
            ->with('productDeliveryOption.product.productable')
            ->orderByDesc('id')
            ->get()
            ->unique(static fn (Enrollment $enrollment): string => $enrollment->productDeliveryOption->uuid)
            ->values()
            ->map(static function (Enrollment $enrollment): ProductAccessData {
                $option      = $enrollment->productDeliveryOption;
                $product     = $option->product;
                $productType = ProductableEnum::from($product->productable_type);
                $productable = $product->productable;

                return new ProductAccessData(
                    option_uuid: $option->uuid,
                    enrollment_uuid: $enrollment->uuid,
                    status: $enrollment->enrollment_status->value,
                    product_type: $productType->value,
                    asset_uuid: $productType === ProductableEnum::DIGITAL_ASSET && $productable instanceof DigitalAsset
                        ? $productable->uuid
                        : null,
                );
            });
    }
}
