<?php

declare(strict_types=1);

namespace App\Actions\Admin\Bundle;

use App\Models\Bundle;
use Illuminate\Validation\ValidationException;

final readonly class DeleteBundleAction
{
    public function handle(Bundle $bundle): void
    {
        if ($bundle->products()->whereHas('productDeliveryOptions.orderItems')->exists()) {
            $bundle->update(['status' => 'archived']);

            return;
        }

        if ($bundle->products()->whereHas('productDeliveryOptions.enrollments')->exists()) {
            throw ValidationException::withMessages(['bundle' => 'A used Bundle can only be archived.']);
        }

        $bundle->products()->each(function ($product): void {
            $product->productDeliveryOptions()->delete();
            $product->delete();
        });
        $bundle->delete();
    }
}
