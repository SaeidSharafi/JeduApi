<?php

declare(strict_types=1);

namespace App\Actions\Product;

use App\Data\Admin\Product\ProductCreateData;
use App\Enums\PublicationStatusEnum;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

final readonly class CreateProductAction
{
    public function handle(ProductCreateData $data): Product
    {
        return DB::transaction(function () use ($data): Product {
            $forceCreate = $data->force_create ?? false;
            if ($forceCreate) {
                Product::query()
                    ->where('productable_id', $data->productable_id)
                    ->where('productable_type', $data->productable_type)
                    ->where('status', PublicationStatusEnum::PUBLISHED)
                    ->update(
                        ['status' => PublicationStatusEnum::ARCHIVED]
                    );
            }
            $product = Product::create($data->except('force_create', 'categories')->toArray())->fresh();
            $product->categories()->sync($data->categories);

            return $product;
        });
    }
}
