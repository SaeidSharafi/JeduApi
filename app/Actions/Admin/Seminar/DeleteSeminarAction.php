<?php

declare(strict_types=1);

namespace App\Actions\Admin\Seminar;

use App\Contracts\Cache\CacheStore;
use App\Enums\System\CacheTag;
use App\Exceptions\ModelHasRelationshipDataException;
use App\Models\Product;
use App\Models\Seminar;
use Illuminate\Support\Facades\DB;

final readonly class DeleteSeminarAction
{
    public function __construct(private CacheStore $cache) {}

    /**
     * Execute the action.
     */
    public function handle(Seminar $seminar): void
    {
        DB::transaction(function () use ($seminar): void {
            if ($seminar->products()->exists()) {
                throw new ModelHasRelationshipDataException(Product::class);
            }
            $seminar->media()->delete();
            $seminar->digitalAssets()->detach();
            $seminar->categories()->detach();
            $seminar->delete();
        });

        // The model observer that used to clear search on a Seminar delete is gone.
        $this->cache->invalidate(CacheTag::Search);
    }
}
