<?php

declare(strict_types=1);

namespace App\Traits;

use App\Enums\Content\PublicationStatusEnum;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;

trait HasProductListingPresets
{
    #[Scope]
    protected function forListing(Builder $query): Builder
    {
        return $query->with([
            'vendor:id,name',
            'categories:id,name,slug',
            'productDeliveryOptions' => function ($query): void {
                $query->where('status', PublicationStatusEnum::PUBLISHED)
                    ->with([
                        'productDeliveryOptionDiscountPrice',
                        'teachers:id,first_name,last_name,gender,uuid,avatar_url,rate',
                    ]);
            },
            'productable',
        ]);
    }

    #[Scope]
    protected function forDetail(Builder $query): Builder
    {
        return $query->with([
            'vendor:id,name',
            'categories:id,name,slug',
            'productDeliveryOptions' => function ($query): void {
                $query->where('status', PublicationStatusEnum::PUBLISHED)
                    ->with([
                        'productDeliveryOptionDiscountPrice',
                        'teachers:id,first_name,last_name,gender,uuid,avatar_url,rate',
                    ]);
            },
            'productableWithAllRelations',
        ]);
    }
}
