<?php

namespace App\Data\Admin\Review;

use App\Contracts\ReviewableDataContract;
use App\Data\Casts\ReviewableCast;
use App\Data\Shop\Customer\CustomerData;
use App\Data\Transformer\TranslatableEnumData;
use App\Enums\MorphTypeEnum;
use App\Enums\ReviewStatusEnum;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Data;

class ReviewListItemData extends Data
{
    public function __construct(
        public int $id,
        public int $user_id,
        #[WithTransformer(TranslatableEnumData::class)]
        public MorphTypeEnum $reviewable_type,
        public int $reviewable_id,
        public int $rating,
        public ?string $title,
        public ?string $comment,
        #[WithTransformer(TranslatableEnumData::class)]
        public ReviewStatusEnum $status,
        public bool $is_featured,
        #[WithCast(ReviewableCast::class, short: true)]
        public ?ReviewableDataContract $reviewable = null,
        public ?CustomerData $user = null,
        public ?string $created_at = null,
        public ?string $updated_at = null,
    )
    {
    }
}
