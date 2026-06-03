<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\MyCourses;

use App\Contracts\ApiResponseInterface;
use App\Data\Shop\MyCourses\EnrollmentData;
use App\Enums\Product\DeliveryMethodEnum;
use App\Http\Controllers\Controller;

/**
 * @group Shop - Student Dash - My Digital Assets
 *
 * @authenticated user
 */
final class DigitalAssetEnrollmentController extends Controller
{
    /**
     * List current user's digital asset enrollments.
     *
     * Returns only enrollments where the delivery method is DIRECT_DOWNLOAD.
     *
     * @responseFile 200 resources/responses/shop/enrollments/digital-assets-index.json
     */
    public function __invoke(): ApiResponseInterface
    {
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
            ->paginate(request()->integer('per_page', 15))
            ->withQueryString();

        return response()->success(EnrollmentData::collect($enrollments));
    }
}
