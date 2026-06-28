<?php

declare(strict_types=1);

namespace App\Jobs\Discounts;

use App\Models\DiscountPromotion;
use App\Services\Discounts\ProductDiscountIndexer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Job to regenerate product discount prices when a promotion is created or updated.
 * Inspired by Bagisto's UpdateCreateCatalogRuleIndex job.
 */
final class RegeneratePromotionDiscountPricesJob implements ShouldQueue
{
    use \Illuminate\Foundation\Queue\Queueable;

    /**
     * Default batch size for processing products.
     */
    protected const BATCH_SIZE = 1000;

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected DiscountPromotion $promotion
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $indexer = app(ProductDiscountIndexer::class);

        if ($this->promotion->is_active) {
            // If promotion is active, reindex it
            $indexer->reIndexPromotion($this->promotion);
        } else {
            // If promotion is disabled, clean up its indices and reindex affected products
            $affectedProductIds = $this->promotion->discountedPrices()
                ->pluck('product_delivery_option_id')
                ->unique();

            $indexer->cleanPromotionIndices($this->promotion);

            if ($affectedProductIds->isNotEmpty()) {
                $indexer->reIndexProductsByDeliveryOptionIds($affectedProductIds);
            }
        }
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'discount-promotion',
            'promotion:'.$this->promotion->id,
            'type:'.$this->promotion->type->value,
        ];
    }
}
