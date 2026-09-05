<?php

declare(strict_types=1);

namespace App\Actions\Admin\Bundle;

use App\Data\Admin\Bundle\BundleUpdateData;
use App\Models\Bundle;
use Illuminate\Support\Facades\DB;

final readonly class UpdateBundleAction
{
    public function handle(BundleUpdateData $data, Bundle $bundle): Bundle
    {
        return DB::transaction(function () use ($data, $bundle): Bundle {
            $bundle->update([
                'full_name'  => $data->name,
                'short_name' => $data->short_name ?? $data->name,
                ...$data->except('name', 'short_name')->toArray(),
            ]);

            return $bundle->fresh();
        });
    }
}
