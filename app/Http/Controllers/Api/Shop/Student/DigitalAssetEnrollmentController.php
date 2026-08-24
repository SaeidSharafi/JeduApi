<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\Student;

use App\Contracts\ApiResponseInterface;
use App\Data\Shop\Student\StudentDigitalAssetData;
use App\Enums\MediaTagEnum;
use App\Enums\Product\DeliveryMethodEnum;
use App\Http\Controllers\Controller;
use App\Models\DigitalAsset;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * @group Shop - Student - Digital Assets
 *
 * @authenticated user
 */
final class DigitalAssetEnrollmentController extends Controller
{
    /**
     * List current user's digital asset enrollments as flat per-asset rows.
     *
     * Returns only enrollments where the delivery method is DIRECT_DOWNLOAD.
     * One row per downloadable file; single file per asset constraint (first
     * main media) documented in ADR 0006.
     *
     * @responseFile 200 resources/responses/shop/enrollments/digital-assets-index.json
     */
    public function __invoke(): ApiResponseInterface
    {
        $perPage = max(1, request()->integer('per_page', config('app.page_size')));

        $enrollments = auth()->user()->enrollments()
            ->withWhereHas(
                'productDeliveryOption', function ($query): void {
                    $query->where('delivery_method', DeliveryMethodEnum::DIRECT_DOWNLOAD)
                        ->withWhereHas(
                            'product', function ($query): void {
                                $query->with(['productableWithAllRelations']);
                            }
                        )->with('teachers.media');
                }
            )
            ->with(['orderItem.vendor'])
            ->get();

        $flatRows = $enrollments->flatMap(function ($enrollment) {
            $productable = $enrollment->productDeliveryOption->product->productable;

            if ($productable instanceof DigitalAsset) {
                $assets = collect([$productable]);
            } elseif (method_exists($productable, 'digitalAssets')) {
                $productable->loadMissing('digitalAssets');
                $assets = $productable->digitalAssets ?? collect([]);
            } else {
                $assets = collect([]);
            }

            // Eager load main media for all assets in this enrollment
            $assets->each(function ($asset): void {
                $asset->loadMediaWithVariantsMatchAll([MediaTagEnum::MAIN->value]);
            });

            return $assets->map(function ($asset) use ($enrollment) {
                $media = $asset->getMedia(MediaTagEnum::MAIN->value)->first();

                $sizeBytes = $media?->size !== null ? (int) $media->size : null;

                return new StudentDigitalAssetData(
                    uuid: $asset->uuid,
                    enrollment_uuid: $enrollment->uuid,
                    name: $asset->full_name,
                    thumbnail_url: $asset->thumbnail_url,
                    file_type: $media?->mime_type,
                    file_type_label: $media?->extension !== null ? mb_strtoupper($media->extension) : null,
                    size_bytes: $sizeBytes,
                    size_label: formatFileSize($sizeBytes),
                    download_url: route(
                        'api.v1.shop.student.digital-assets.download',
                        ['enrollment' => $enrollment->uuid, 'digitalAsset' => $asset->uuid],
                        absolute: true
                    ),
                );
            });
        })->values();

        $page  = request()->integer('page', 1);
        $total = $flatRows->count();
        $items = $flatRows->forPage($page, $perPage)->values();

        $paginator = new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );

        return apiResponse()->success(StudentDigitalAssetData::collect($paginator));
    }
}
