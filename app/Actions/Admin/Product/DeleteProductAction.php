<?php

declare(strict_types=1);

namespace App\Actions\Admin\Product;

use App\Models\Product;
use Illuminate\Support\Facades\DB;

final readonly class DeleteProductAction
{
    public function handle(Product $product): void
    {
        DB::transaction(function () use ($product): void {
            $product->productDeliveryOptions()->delete();
            $product->delete();
        });
    }
}
