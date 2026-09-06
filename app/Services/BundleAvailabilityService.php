<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Content\PublicationStatusEnum;
use App\Enums\Product\BundleReviewReasonEnum;
use App\Enums\Product\BundleUnavailableReasonEnum;
use App\Enums\Product\ProductableEnum;
use App\Models\ProductDeliveryOption;
use Illuminate\Validation\ValidationException;

final class BundleAvailabilityService
{
    private const array PROVIDER_IDENTIFIER_KEYS = [
        'ims_course_code',
        'meeting_id',
        'moodle_course_id',
        'moodle_quiz_course_id',
        'nili_room_id',
        'room_id',
        'spot_id',
    ];

    public function isAvailable(ProductDeliveryOption $bundleOption): bool
    {
        $bundleOption->loadMissing([
            'product.productable',
            'product.term',
            'bundleComponents.product.productable',
            'bundleComponents.product.term',
        ]);

        if ($bundleOption->product?->productable_type   !== ProductableEnum::BUNDLE->value
            || $bundleOption->bundle_review_required_at !== null
            || ! $this->optionIsOpen($bundleOption)
            || ! $bundleOption->product?->isEligibleForBundleSale()
            || $bundleOption->bundleComponents->isEmpty()
        ) {
            return false;
        }

        return $bundleOption->bundleComponents->every(
            fn (ProductDeliveryOption $component): bool => $this->optionIsOpen($component)
                && $component->product?->isEligibleForBundleSale()
                && ($component->effectiveRemainingCapacity() === null || $component->effectiveRemainingCapacity() > 0)
        );
    }

    public function remainingCapacity(ProductDeliveryOption $bundleOption): ?int
    {
        $bundleOption->loadMissing('bundleComponents');

        return $bundleOption->effectiveRemainingCapacity();
    }

    /**
     * @return array{available: bool, reason: ?BundleUnavailableReasonEnum, remaining: ?int}
     */
    public function bundlePurchaseStatus(ProductDeliveryOption $deliveryOption, ?int $snapshotVersion, int $quantity): array
    {
        if ($snapshotVersion !== null && (int) $snapshotVersion !== (int) $deliveryOption->composition_version) {
            return ['available' => false, 'reason' => BundleUnavailableReasonEnum::VERSION_CHANGED, 'remaining' => null];
        }

        if (! $this->isAvailable($deliveryOption)) {
            return ['available' => false, 'reason' => BundleUnavailableReasonEnum::UNAVAILABLE, 'remaining' => null];
        }

        $remaining = $this->remainingCapacity($deliveryOption);
        if ($remaining !== null && $quantity > $remaining) {
            return ['available' => false, 'reason' => BundleUnavailableReasonEnum::CAPACITY_EXCEEDED, 'remaining' => $remaining];
        }

        return ['available' => true, 'reason' => null, 'remaining' => $remaining];
    }

    public function validateReview(ProductDeliveryOption $bundleOption): void
    {
        $bundleOption->loadMissing([
            'product.productable',
            'product.term',
            'bundleComponents.product.productable',
            'bundleComponents.product.term',
        ]);

        if ($bundleOption->product?->productable_type !== ProductableEnum::BUNDLE->value
            || $bundleOption->bundleComponents->isEmpty()
            || ! $bundleOption->product?->isEligibleForBundleSale()
            || ! $bundleOption->bundleComponents->every(
                fn (ProductDeliveryOption $component): bool => $component->status === PublicationStatusEnum::PUBLISHED
                    && $component->product?->isEligibleForBundleSale()
            )
        ) {
            throw ValidationException::withMessages([
                'bundle' => [__('messages.product.bundle_review_components_ineligible')],
            ]);
        }
    }

    /** @return array<int, string> */
    public function materialReasons(ProductDeliveryOption $before, ProductDeliveryOption $after): array
    {
        $reasons = [];

        if ($before->price !== $after->price) {
            $reasons[] = BundleReviewReasonEnum::BASE_PRICE_CHANGED->value;
        }
        if ($before->delivery_method !== $after->delivery_method) {
            $reasons[] = BundleReviewReasonEnum::DELIVERY_METHOD_CHANGED->value;
        }
        if ($this->providerIdentifiers($before) !== $this->providerIdentifiers($after)) {
            $reasons[] = BundleReviewReasonEnum::PROVIDER_IDENTIFIER_CHANGED->value;
        }
        if ($before->access_days !== $after->access_days) {
            $reasons[] = BundleReviewReasonEnum::ACCESS_DURATION_CHANGED->value;
        }
        if ($before->product_id !== $after->product_id) {
            $reasons[] = BundleReviewReasonEnum::PRODUCT_ASSOCIATION_CHANGED->value;
        }
        if ($before->status !== $after->status && $after->status === PublicationStatusEnum::ARCHIVED) {
            $reasons[] = BundleReviewReasonEnum::COMPONENT_ARCHIVED->value;
        }

        return $reasons;
    }

    /** @return array<string, mixed> */
    private function providerIdentifiers(ProductDeliveryOption $option): array
    {
        return collect($option->details_json ?? [])->only(self::PROVIDER_IDENTIFIER_KEYS)->all();
    }

    private function optionIsOpen(ProductDeliveryOption $option): bool
    {
        $now = now();

        return $option->status === PublicationStatusEnum::PUBLISHED
            && (! $option->registration_start_date || $now->greaterThanOrEqualTo($option->registration_start_date))
            && (! $option->registration_end_date || $now->lessThanOrEqualTo($option->registration_end_date))
            && (! $option->available_from || $now->greaterThanOrEqualTo($option->available_from))
            && (! $option->available_to || $now->lessThanOrEqualTo($option->available_to));
    }
}
