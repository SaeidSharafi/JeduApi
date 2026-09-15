<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\SelectOptions;

use App\Contracts\ApiResponseInterface;
use App\Data\Admin\SelectOptions\DigitalAssetSelectOptionData;
use App\Enums\Content\PublicationStatusEnum;
use App\Enums\MediaTagEnum;
use App\Http\Controllers\Controller;
use App\Models\DigitalAsset;
use Illuminate\Database\Eloquent\Builder;

/**
 * @group Admin - Select Options
 *
 * retrieve a list of digital assets for select options
 *
 * @authenticated
 */
final class DigitalAssetSelectOptionController extends Controller
{
    /**
     * Digital assets list
     *
     * Only published assets are returned.
     *
     * @queryParam  q string The search query for filtering digital assets (match full name and short name). Example: "advanced"
     * @queryParam  limit integer The maximum number of results to return. Default is 10. Example: 10
     * @queryParam  is_attachable_to_course boolean When present, only assets matching this attachability are returned. Example: true
     *
     * @responseFile 200 resources/responses/admin/select-options/digital-asset.json
     */
    public function __invoke(): ApiResponseInterface
    {
        $query = request()->string('q', '');
        $limit = request()->integer('limit', 10);

        $digitalAssets = DigitalAsset::query()
            ->where('status', PublicationStatusEnum::PUBLISHED)
            ->when($query->isNotEmpty(), function (Builder $builder) use ($query): void {
                $term = '%'.addcslashes((string) $query, '%_').'%';
                $builder->where(function (Builder $builder) use ($term): void {
                    $builder->whereLike('full_name', $term)
                        ->orWhereLike('short_name', $term);
                });
            })
            ->when(
                request()->filled('is_attachable_to_course'),
                fn (Builder $builder): Builder => $builder->where(
                    'is_attachable_to_course',
                    request()->boolean('is_attachable_to_course')
                )
            )
            ->withMediaAndVariants([MediaTagEnum::MAIN->value])
            ->orderBy('full_name')
            ->limit($limit)
            ->get(['id', 'full_name', 'thumbnail_url']);

        return apiResponse()->success(
            DigitalAssetSelectOptionData::collect($digitalAssets)
        );
    }
}
