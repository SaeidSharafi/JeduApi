<?php

declare(strict_types=1);

namespace App\Actions\Seminar;

use App\Models\Seminar;
use Illuminate\Support\Facades\DB;

final readonly class DeleteSeminarAction
{
    /**
     * Execute the action.
     */
    public function handle(Seminar $seminar): void
    {
        DB::transaction(function () use ($seminar): void {
            $seminar->media()->delete();
            $seminar->digitalAssets()->detach();
            $seminar->categories()->detach();
            $seminar->delete();
        });
    }
}
