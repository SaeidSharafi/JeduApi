<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

final class BundleComponent extends Pivot
{
    protected $table = 'bundle_components';

    protected $fillable = ['bundle_product_delivery_option_id', 'component_product_delivery_option_id', 'allocation'];

    /** @return BelongsTo<ProductDeliveryOption, $this> */
    public function bundleOption(): BelongsTo
    {
        return $this->belongsTo(ProductDeliveryOption::class, 'bundle_product_delivery_option_id');
    }

    /** @return BelongsTo<ProductDeliveryOption, $this> */
    public function componentOption(): BelongsTo
    {
        return $this->belongsTo(ProductDeliveryOption::class, 'component_product_delivery_option_id');
    }
}
