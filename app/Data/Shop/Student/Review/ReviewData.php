<?php

declare(strict_types=1);

namespace App\Data\Shop\Student\Review;

use App\Data\Transformer\TranslatableEnumData;
use App\Enums\Content\ReviewStatusEnum;
use App\Models\Review;
use Hekmatinasser\Verta\Verta;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Data;

final class ReviewData extends Data
{
    public function __construct(
        public int $id,
        public ?int $rating,
        public string $title,
        public string $comment,
        #[WithTransformer(TranslatableEnumData::class)]
        public ReviewStatusEnum $status,
        public ?Verta $created_at = null,
    ) {}

    public static function fromModel(Review $review): self
    {
        return new self(
            id: $review->id,
            rating: $review->rating,
            title: $review->title,
            comment: $review->comment,
            status: $review->status,
            created_at: $review->created_at ? Verta::instance($review->created_at) : null,
        );
    }
}
