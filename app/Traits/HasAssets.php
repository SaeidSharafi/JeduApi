<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\DigitalAsset;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

trait HasAssets
{
    public function digitalAssets(): MorphToMany
    {
        return $this->morphToMany(DigitalAsset::class, 'assetable');
    }
}
