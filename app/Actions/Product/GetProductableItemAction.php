<?php

declare(strict_types=1);

namespace App\Actions\Product;

use Illuminate\Support\Facades\DB;

final readonly class GetProductableItemAction
{
    /**
     * Execute the action.
     */
    public function handle(): void
    {
        DB::transaction(function (): void {
            //
        });
    }
}
