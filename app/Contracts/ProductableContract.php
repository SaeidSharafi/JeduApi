<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @template TModel of Model
 */
interface ProductableContract
{
    /**
     * @return MorphMany<Product, TModel>
     */
    public function products(): MorphMany;

    /**
     * @return array<string, mixed>|null
     */
    public function getAllMedia(): ?array;

    /**
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function scopeWithProductableMedia(Builder $query): Builder;

    /**
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function scopeWithProductableCategories(Builder $query): Builder;

    /**
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function scopeWithProductableAssets(Builder $query): Builder;
    // public function scopeWithProductableAuditor(Builder $query): Builder;

    public function loadMediaWitVariant(): void;

    public function loadProductableCategories(): void;
}
