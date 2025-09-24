<?php

declare(strict_types=1);

namespace App\Actions\Admin\Partner;

use App\Models\Partner;
use Illuminate\Support\Facades\DB;

final class DeleteCPartnerAction
{
    public function handle(Partner $partner): void
    {
        DB::transaction(function () use ($partner): void {
            $image = $partner->getMedia('image')->first();
            $partner->delete();
            $image?->delete();
        });
    }
}
