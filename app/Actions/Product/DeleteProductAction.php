<?php
declare(strict_types=1);

namespace App\Actions\Product;

use App\Data\Product\ProductCreateData;
use App\Data\Product\ProductUpdateData;
use App\Enums\ProductableEnum;
use App\Enums\PublicationStatusEnum;
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
