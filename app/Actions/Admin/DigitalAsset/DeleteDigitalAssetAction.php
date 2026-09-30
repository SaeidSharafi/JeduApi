<?php

declare(strict_types=1);

namespace App\Actions\Admin\DigitalAsset;

use App\Contracts\Cache\CacheStore;
use App\Enums\System\CacheTag;
use App\Enums\System\MorphTypeEnum;
use App\Exceptions\ModelHasRelationshipDataException;
use App\Models\DigitalAsset;
use App\Models\LearningPath;
use App\Models\LearningPathStep;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

final readonly class DeleteDigitalAssetAction
{
    public function __construct(private CacheStore $cache) {}

    /**
     * Execute the action.
     */
    public function handle(DigitalAsset $digitalAsset): void
    {
        DB::transaction(function () use ($digitalAsset): void {
            if ($digitalAsset->products()->exists()) {
                throw new ModelHasRelationshipDataException(Product::class);
            }
            if (LearningPathStep::query()
                ->where('productable_type', MorphTypeEnum::DIGITAL_ASSET->value)
                ->where('productable_id', $digitalAsset->getKey())
                ->exists()) {
                throw new ModelHasRelationshipDataException(LearningPath::class);
            }
            $digitalAsset->media()->delete();
            $digitalAsset->delete();
        });

        $this->cache->invalidate(CacheTag::Search);
    }
}
