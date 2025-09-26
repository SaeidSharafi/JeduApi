<?php

declare(strict_types=1);

namespace App\Data\Admin\Review;

use App\Contracts\ReviewableDataContract;
use App\Data\Casts\ReviewableCast;
use App\Data\Shop\Customer\CustomerData;
use App\Data\Transformer\TranslatableEnumData;
use App\Enums\Content\ReviewStatusEnum;
use App\Enums\System\MorphTypeEnum;
use Hekmatinasser\Verta\Verta;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Data;

final class ReviewData extends Data
{
    public function __construct(
        public int $id,
        public int $user_id,
        #[WithTransformer(TranslatableEnumData::class)]
        public MorphTypeEnum $reviewable_type,
        public int $reviewable_id,
        public int $rating,
        public ?string $title,
        #[WithTransformer(TranslatableEnumData::class)]
        public ReviewStatusEnum $status,
        public bool $is_featured,
        #[WithCast(ReviewableCast::class)]
        public ?ReviewableDataContract $reviewable = null,
        public ?CustomerData $user = null,
        public ?Verta $created_at = null,
        public ?Verta $updated_at = null,
    ) {}
}
