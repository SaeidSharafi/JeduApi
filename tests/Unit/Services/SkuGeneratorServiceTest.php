<?php

use App\Data\Admin\ProductDeliveryOption\ProductDeliveryOptionCreateData;
use App\Enums\Content\PublicationStatusEnum;
use App\Enums\Product\DeliveryMethodEnum;
use App\Enums\Product\FulfillmentTypeEnum;
use App\Enums\System\MorphTypeEnum;
use App\Enums\TermStatusEnum;
use App\Models\Course;
use App\Models\Product;
use App\Models\ProductDeliveryOption;
use App\Models\Term;
use App\Services\SkuGeneratorService;

describe('SkuGeneratorService', function (): void {
    beforeEach(function () {
        $this->skuService = new SkuGeneratorService();
        $this->course = Course::factory()->create(
            [
                'short_name' => 'دوره آموزش پایتون',
                'full_name'  => 'دوره آموزش برنامه نویسی پایتون از مقدماتی تا پیشرفته',
                'slug'       => 'python-course'
            ]
        );
        $this->term = Term::factory()->create([
            'name'          => 'ترم پاییز',
            'status'        => TermStatusEnum::ACTIVE,
            'academic_year' => '1402-1403',
            'start_date' => '2025-09-23',
            'end_date' => '2025-12-20',
        ]);
        $this->product = Product::factory()->create([
            'productable_type' => MorphTypeEnum::COURSE,
            'productable_id'   => $this->course->id,
            'slug'             => $this->course->slug,
            'short_name'       => $this->course->short_name,
            'name'             => $this->course->full_name,
            'term_id'          => $this->term->id
        ]);
    });
    it('generates SKUs', function (): void {
        $this->skuService = new SkuGeneratorService();

        $data = ProductDeliveryOptionCreateData::from([
            'name'                      => 'گزینه جدید',
            'fulfillment_type'          => FulfillmentTypeEnum::OFFLINE_SERVICE->value,
            'delivery_method'           => DeliveryMethodEnum::VIDEO_PLATFORM_SPOTPLAYER->value,
            'price'                     => 150000,
            'is_active'                 => true,
            'teachers'                  => [
                \App\Models\Teacher::factory()->create()->fresh()->id
            ],
            'status'                    => PublicationStatusEnum::PUBLISHED->value,
            'details'                   => [],
            'is_prepayment_available'   => false,
            'capacity'                  => 20,
            'prepayment_amount'         => 0,
            'is_featured'               => false,
            'featured_price'            => null,
            'featured_price_start_date' => null,
            'featured_price_end_date'   => null,
            'registration_start_date'   => null,
            'registration_end_date'     => null,
            'available_from'            => null,
            'available_to'              => null,
        ]);
        $sku1 = $this->skuService->generateBaseSku($data, $this->product);

        expect($sku1)->toBe("PYT-F1402-OFF-VID");
    });

    it('crrect code for term', function (string $termCode, array $termData): void {
        $term = Term::factory()->create($termData);
        $this->product->term_id = $term->id;
        $this->product->save();
        $this->product->fresh();
        $data = ProductDeliveryOptionCreateData::from([
            'name'                      => 'گزینه جدید',
            'fulfillment_type'          => FulfillmentTypeEnum::OFFLINE_SERVICE->value,
            'delivery_method'           => DeliveryMethodEnum::VIDEO_PLATFORM_SPOTPLAYER->value,
            'price'                     => 150000,
            'is_active'                 => true,
            'teachers'                  => [
                \App\Models\Teacher::factory()->create()->fresh()->id
            ],
            'status'                    => PublicationStatusEnum::PUBLISHED->value,
            'details'                   => [],
            'is_prepayment_available'   => false,
            'capacity'                  => 20,
            'prepayment_amount'         => 0,
            'is_featured'               => false,
            'featured_price'            => null,
            'featured_price_start_date' => null,
            'featured_price_end_date'   => null,
            'registration_start_date'   => null,
            'registration_end_date'     => null,
            'available_from'            => null,
            'available_to'              => null,
        ]);
        $sku1 = $this->skuService->generateBaseSku($data, $this->product);
        $expectedSkuStart = "PYT-{$termCode}-OFF-VID";
        expect($sku1)->toStartWith($expectedSkuStart);

    })->with([
        ['F1402', ['academic_year' => '1402-1403', 'start_date' => '2025-09-23', 'end_date' => '2025-12-20']],
        ['W1402', ['academic_year' => '1402-1403', 'start_date' => '2025-01-10', 'end_date' => '2025-05-20']],
        ['SU1403', ['academic_year' => '1403-1404', 'start_date' => '2025-07-23', 'end_date' => '2025-08-20']],
        ['S1403', ['academic_year' => '1403-1404', 'start_date' => '2025-03-26', 'end_date' => '2025-05-20']],
        ['X0000', ['academic_year' => null, 'start_date' => null, 'end_date' => null]],
        ['X0000', ['academic_year' => '', 'start_date' => null, 'end_date' => null]],
        ['X0000', ['academic_year' => 'invalid-year', 'start_date' => null, 'end_date' => null]],
        ['F1402', ['academic_year' => '1402-1403', 'start_date' => '2023-09-23', 'end_date' => null]],
        ['X1402', ['academic_year' => '1402-1403', 'start_date' => null, 'end_date' => null]],
    ]);

    it('crrect code for delviey and fulfillment type', function (array $codes, FulfillmentTypeEnum $ftype,DeliveryMethodEnum $dmethod): void {
        $data = ProductDeliveryOptionCreateData::from([
            'name'                      => 'گزینه جدید',
            'fulfillment_type'          => $ftype->value,
            'delivery_method'           => $dmethod->value,
            'price'                     => 150000,
            'is_active'                 => true,
            'teachers'                  => [
                \App\Models\Teacher::factory()->create()->fresh()->id
            ],
            'status'                    => PublicationStatusEnum::PUBLISHED->value,
            'details'                   => [],
            'is_prepayment_available'   => false,
            'capacity'                  => 20,
            'prepayment_amount'         => 0,
            'is_featured'               => false,
            'featured_price'            => null,
            'featured_price_start_date' => null,
            'featured_price_end_date'   => null,
            'registration_start_date'   => null,
            'registration_end_date'     => null,
            'available_from'            => null,
            'available_to'              => null,
        ]);
        $sku1 = $this->skuService->generateBaseSku($data, $this->product);
        $expectedSkuStart = "PYT-F1402-{$codes[0]}-{$codes[1]}";
        expect($sku1)->toStartWith($expectedSkuStart);
    })
    ->with([
        [['OFF','VID'], FulfillmentTypeEnum::OFFLINE_SERVICE, DeliveryMethodEnum::VIDEO_PLATFORM_SPOTPLAYER],
        [['OFF','DL'], FulfillmentTypeEnum::OFFLINE_SERVICE, DeliveryMethodEnum::DIRECT_DOWNLOAD],
        [['ONL','VID'], FulfillmentTypeEnum::ONLINE_SERVICE, DeliveryMethodEnum::VIDEO_PLATFORM_SPOTPLAYER],
        [['ONL','DL'], FulfillmentTypeEnum::ONLINE_SERVICE, DeliveryMethodEnum::DIRECT_DOWNLOAD],
        [['DIG','VID'], FulfillmentTypeEnum::DIGITAL, DeliveryMethodEnum::VIDEO_PLATFORM_SPOTPLAYER],
        [['DIG','DL'], FulfillmentTypeEnum::DIGITAL, DeliveryMethodEnum::DIRECT_DOWNLOAD],
        [['INP','VID'], FulfillmentTypeEnum::IN_PERSON_SERVICE, DeliveryMethodEnum::VIDEO_PLATFORM_SPOTPLAYER],
        [['INP','DL'], FulfillmentTypeEnum::IN_PERSON_SERVICE, DeliveryMethodEnum::DIRECT_DOWNLOAD],
        [['OTH','DL'], FulfillmentTypeEnum::PHYSICAL, DeliveryMethodEnum::DIRECT_DOWNLOAD],
    ])
    ;
});
