<?php

declare(strict_types=1);

namespace App\Observers;

use App\Events\ProductSearchIndexInvalidated;
use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;

final class CategorySearchIndexObserver
{
    public function updated(Category $category): void
    {
        if (! $category->wasChanged('slug')) {
            return;
        }

        $category->products()
            ->select('products.id')
            ->chunkById(200, function (Collection $products): void {
                ProductSearchIndexInvalidated::dispatch($products->pluck('id')->all());
            });
    }
}
