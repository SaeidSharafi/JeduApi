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
     * @queryParam  page integer The page number for pagination. Example: 2
     * @queryParam  per_page integer The number of results per page. Default is 15. Example: 10
     * @queryParam  is_attachable_to_course boolean When present, only assets matching this attachability are returned. Example: true
     *
     * @responseFile 200 resources/responses/admin/select-options/digital-asset.json
     */
    public function __invoke(): ApiResponseInterface
    {
        $query   = request()->string('q', '');
        $perPage = request()->integer('per_page', config('app.page_size')) ?: (int) config('app.page_size');

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
            ->paginate($perPage, ['id', 'full_name', 'thumbnail_url'])
            ->withQueryString();

        return apiResponse()->success(
            DigitalAssetSelectOptionData::collect($digitalAssets)
        );
    }
}
