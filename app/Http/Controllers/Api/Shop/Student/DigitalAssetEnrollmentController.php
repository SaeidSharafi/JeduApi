<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\Student;

use App\Contracts\ApiResponseInterface;
use App\Data\Shop\Student\StudentDigitalAssetData;
use App\Enums\MediaTagEnum;
use App\Enums\Product\DeliveryMethodEnum;
use App\Enums\Product\ProductableEnum;
use App\Http\Controllers\Controller;

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
                                $query->whereIn('productable_type', [
                                    ProductableEnum::DIGITAL_ASSET->value,
                                    \App\Models\DigitalAsset::class,
                                ]);
                            }
                        );
                }
            )
            ->with([
                'productDeliveryOption.product.productable',
                'orderItem.vendor',
            ])
            ->paginate($perPage)
            ->withQueryString();

        $enrollments->getCollection()->each(function ($enrollment): void {
            $enrollment->productDeliveryOption->product->productable
                ->loadMediaWithVariantsMatchAll([MediaTagEnum::MAIN->value]);
        });

        $data = $enrollments->getCollection()->map(function ($enrollment) {
            $asset = $enrollment->productDeliveryOption->product->productable;
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

        $enrollments->setCollection($data);

        return apiResponse()->success(StudentDigitalAssetData::collect($enrollments));
    }
}
