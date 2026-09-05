<?php

declare(strict_types=1);

namespace App\Actions\Admin\Bundle;

use App\Data\Admin\Bundle\BundleCreateData;
use App\Models\Bundle;
use Illuminate\Support\Facades\DB;

final readonly class CreateBundleAction
{
    public function handle(BundleCreateData $data): Bundle
    {
        return DB::transaction(function () use ($data): Bundle {
            return Bundle::create([
                'full_name'  => $data->name,
                'short_name' => $data->short_name ?? $data->name,
                ...$data->except('name', 'short_name')->toArray(),
            ]);
        });
    }
}
