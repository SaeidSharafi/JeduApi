<?php

declare(strict_types=1);

namespace App\Actions\Shop\LearningPath;

use App\Data\Shop\PaginationRequestData;
use App\Enums\Content\PublicationStatusEnum;
use App\Models\LearningPath;
use Illuminate\Pagination\LengthAwarePaginator;

final readonly class ListPublishedLearningPathsAction
{
    /**
     * @return LengthAwarePaginator<int, LearningPath>
     */
    public function handle(PaginationRequestData $requestData): LengthAwarePaginator
    {
        return LearningPath::query()
            ->where('status', PublicationStatusEnum::PUBLISHED)
            ->withProductableMedia()
            ->withCount('steps')
            ->orderBy('display_order')
            ->orderBy('id')
            ->paginate(
                perPage: $requestData->per_page ?? 15,
                page: $requestData->page,
            )
            ->withQueryString();
    }
}
