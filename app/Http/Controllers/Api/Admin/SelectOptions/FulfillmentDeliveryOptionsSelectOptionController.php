<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\SelectOptions;

use App\Contracts\ApiResponseInterface;
use App\Data\Admin\SelectOptions\FulfillmentDeliveryOptionsSelectOptionData;
use App\Enums\Product\FulfillmentTypeEnum;
use App\Http\Controllers\Controller;
use Spatie\LaravelData\DataCollection;

/**
 * @group Admin - Select Options
 *
 * @authenticated
 */
final class FulfillmentDeliveryOptionsSelectOptionController extends Controller
{
    /**
     * Fulfillment and delivery options list
     *
     * Retrieve fulfillment types and their compatible delivery options for select inputs.
     *
     * @responseFile 200 resources/responses/admin/select-options/delivery-options.json
     */
    public function __invoke(): ApiResponseInterface
    {
        $options = array_map(
            static fn (FulfillmentTypeEnum $fulfillmentType): FulfillmentDeliveryOptionsSelectOptionData => FulfillmentDeliveryOptionsSelectOptionData::fromFulfillmentType($fulfillmentType),
            FulfillmentTypeEnum::cases(),
        );

        return apiResponse()->success(
            new DataCollection(FulfillmentDeliveryOptionsSelectOptionData::class, $options)
        );
    }
}
