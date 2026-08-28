<?php

declare(strict_types=1);

namespace App\Query;

use App\Enums\Content\PublicationStatusEnum;
use App\Enums\Product\AvailabilityStatusEnum;
use App\Enums\Product\ProductableEnum;
use App\Enums\TermStatusEnum;
use App\Models\Product;
use App\Models\ProductDeliveryOption;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

final class ProductAvailabilityFilter
{
    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public static function applyPublishedAndVisible(Builder $query): Builder
    {
        return $query->where('products.status', PublicationStatusEnum::PUBLISHED)
            ->where('products.is_visible', true);
    }

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public static function applyHasPublishedDeliveryOption(Builder $query): Builder
    {
        if (config('products.availability.use_denormalized')) {
            return $query->where('products.has_published_delivery_option', true);
        }

        return $query->whereHas('productDeliveryOptions', fn (Builder $optionQuery): Builder => $optionQuery
            ->where('status', PublicationStatusEnum::PUBLISHED));
    }

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public static function applyPublishedProductable(Builder $query): Builder
    {
        if (config('products.availability.use_denormalized')) {
            return $query->where('products.productable_status', PublicationStatusEnum::PUBLISHED);
        }

        return $query->whereHasMorph('productable', ProductableEnum::getAllValues(), fn (Builder $productableQuery): Builder => $productableQuery
            ->where('status', PublicationStatusEnum::PUBLISHED));
    }

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public static function applyActiveTerm(Builder $query): Builder
    {
        if (config('products.availability.use_denormalized')) {
            return $query->where('products.is_term_active', true);
        }

        return $query->where(function (Builder $termConstraint): void {
            $termConstraint->whereNull('term_id')
                ->orWhereHas('term', fn (Builder $termQuery): Builder => $termQuery
                    ->where('status', TermStatusEnum::ACTIVE));
        });
    }

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public static function applyAvailableNow(Builder $query): Builder
    {
        $today = today();

        if (! config('products.availability.use_denormalized')) {
            return $query->whereHas('productDeliveryOptions', function (Builder $optionQuery) use ($today): void {
                /** @var Builder<ProductDeliveryOption> $optionQuery */
                self::applyOptionDateWindow($optionQuery, 'registration_start_date', 'registration_end_date', $today);
                self::applyOptionDateWindow($optionQuery, 'available_from', 'available_to', $today);
            });
        }

        return $query
            ->where(fn (Builder $dateQuery): Builder => $dateQuery
                ->whereNull('earliest_registration_start')
                ->orWhere('earliest_registration_start', '<=', $today))
            ->where(fn (Builder $dateQuery): Builder => $dateQuery
                ->whereNull('latest_registration_end')
                ->orWhere('latest_registration_end', '>=', $today))
            ->where(fn (Builder $dateQuery): Builder => $dateQuery
                ->whereNull('earliest_availability_start')
                ->orWhere('earliest_availability_start', '<=', $today))
            ->where(fn (Builder $dateQuery): Builder => $dateQuery
                ->whereNull('latest_availability_end')
                ->orWhere('latest_availability_end', '>=', $today));
    }

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public static function applyContentAvailableNow(Builder $query): Builder
    {
        $today = today();

        if (config('products.availability.use_denormalized')) {
            return $query
                ->where(fn (Builder $dateQuery): Builder => $dateQuery
                    ->whereNull('earliest_availability_start')
                    ->orWhere('earliest_availability_start', '<=', $today->startOfDay()))
                ->where(fn (Builder $dateQuery): Builder => $dateQuery
                    ->whereNull('latest_availability_end')
                    ->orWhere('latest_availability_end', '>=', $today->endOfDay()));
        }

        return $query->whereHas('productDeliveryOptions', function (Builder $optionQuery) use ($today): void {
            /** @var Builder<ProductDeliveryOption> $optionQuery */
            self::applyOptionDateWindow($optionQuery, 'available_from', 'available_to', $today);
        });
    }

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public static function applyEventStatus(Builder $query, AvailabilityStatusEnum $status): Builder
    {
        return $query->availabilityStatus($status);
    }

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public static function applyEventNotEnded(Builder $query): Builder
    {
        return $query->eventNotEnded();
    }

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public static function applyRegistrationWindow(Builder $query, ?Carbon $from, ?Carbon $to): Builder
    {
        if ($from === null && $to === null) {
            return $query;
        }

        if (config('products.availability.use_denormalized')) {
            return self::applySnapshotWindow($query, 'earliest_registration_start', 'latest_registration_end', $from, $to);
        }

        return $query->whereHas('productDeliveryOptions', function (Builder $optionQuery) use ($from, $to): Builder {
            /** @var Builder<ProductDeliveryOption> $optionQuery */
            return self::applyRelationshipWindow(
                $optionQuery,
                'registration_start_date',
                'registration_end_date',
                $from,
                $to,
            );
        });
    }

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public static function applyAvailabilityWindow(Builder $query, ?Carbon $from, ?Carbon $to): Builder
    {
        if ($from === null && $to === null) {
            return $query;
        }

        if (config('products.availability.use_denormalized')) {
            return self::applySnapshotWindow($query, 'earliest_availability_start', 'latest_availability_end', $from, $to);
        }

        return $query->whereHas('productDeliveryOptions', function (Builder $optionQuery) use ($from, $to): Builder {
            /** @var Builder<ProductDeliveryOption> $optionQuery */
            return self::applyRelationshipWindow(
                $optionQuery,
                'available_from',
                'available_to',
                $from,
                $to,
            );
        });
    }

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public static function applyNearCapacity(Builder $query, float $threshold = 0.8): Builder
    {
        $threshold = max(0.0, min(1.0, $threshold));

        if (config('products.availability.use_denormalized')) {
            return $query->where('products.max_capacity_utilization', '>=', $threshold);
        }

        return $query->whereHas('productDeliveryOptions', fn (Builder $optionQuery): Builder => $optionQuery
            ->whereNotNull('capacity')
            ->where('capacity', '>', 0)
            ->whereRaw('(((enrolled_count + reserved_count) * 1.0) / NULLIF(capacity, 0)) >= ?', [$threshold]));
    }

    /**
     * @param  Builder<ProductDeliveryOption>  $query
     */
    private static function applyOptionDateWindow(Builder $query, string $startColumn, string $endColumn, CarbonInterface $date): void
    {
        $query->where(fn (Builder $dateQuery): Builder => $dateQuery
            ->whereNull($startColumn)
            ->orWhere($startColumn, '<=', $date->startOfDay()))
            ->where(fn (Builder $dateQuery): Builder => $dateQuery
                ->whereNull($endColumn)
                ->orWhere($endColumn, '>=', $date->endOfDay()));
    }

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    private static function applySnapshotWindow(Builder $query, string $startColumn, string $endColumn, ?Carbon $from, ?Carbon $to): Builder
    {
        if ($from !== null) {
            $query->where(fn (Builder $dateQuery): Builder => $dateQuery
                ->whereNull($endColumn)
                ->orWhere($endColumn, '>=', $from));
        }

        if ($to !== null) {
            $query->where(fn (Builder $dateQuery): Builder => $dateQuery
                ->whereNull($startColumn)
                ->orWhere($startColumn, '<=', $to));
        }

        return $query;
    }

    /**
     * @param  Builder<ProductDeliveryOption>  $query
     * @return Builder<ProductDeliveryOption>
     */
    private static function applyRelationshipWindow(Builder $query, string $startColumn, string $endColumn, ?Carbon $from, ?Carbon $to): Builder
    {
        return $query->where(function (Builder $dateQuery) use ($startColumn, $endColumn, $from, $to): void {
            $dateQuery->whereNull($startColumn)
                ->orWhereNull($endColumn)
                ->orWhere(function (Builder $boundedQuery) use ($startColumn, $endColumn, $from, $to): void {
                    if ($from !== null) {
                        $boundedQuery->where($endColumn, '>=', $from);
                    }

                    if ($to !== null) {
                        $boundedQuery->where($startColumn, '<=', $to);
                    }
                });
        });
    }
}
