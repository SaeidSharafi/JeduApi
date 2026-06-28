<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Admin\ProductDeliveryOption\ProductDeliveryOptionCreateData;
use App\Enums\Product\DeliveryMethodEnum;
use App\Enums\Product\FulfillmentTypeEnum;
use App\Models\Product;
use App\Models\Term;
use Illuminate\Support\Str;

/**
 * Service class responsible for generating unique and informative SKUs
 * for ProductDeliveryOption models.
 */
final class SkuGeneratorService
{
    /**
     * Generates a base SKU from product, term, and delivery option details.
     * This base SKU is informative but is NOT guaranteed to be unique.
     * The calling action is responsible for handling potential collisions.
     *
     * @param  ProductDeliveryOptionCreateData  $data  The DTO containing the new delivery option's data.
     * @param  Product  $product  The parent product model.
     * @return string The generated base SKU (e.g., "PYT-F25-LIVE").
     */
    public function generateBaseSku(ProductDeliveryOptionCreateData $data, Product $product): string
    {
        // 1. Generate a code from the underlying "blueprint" (Course, Seminar, etc.)
        $product->load('productable', 'term');
        $productableCode = $this->generateProductableCode($product->productable);

        // 2. Generate a code from the academic term.
        $termCode = $this->generateTermCode($product->term);

        // 3. Generate a code from the delivery option's name.
        $deliveryCode = $this->generateDeliveryCode($data);

        return mb_strtoupper("{$productableCode}-{$termCode}-{$deliveryCode}");
    }

    /**
     * Generates a short code based on the productable's (e.g., Course) slug.
     *
     * @param  object  $productable  The productable model instance (Course, Seminar, etc.).
     * @return string A short code like "PYT" from "introduction-to-python".
     */
    private function generateProductableCode(object $productable): string
    {
        $slug = $productable->slug ?? Str::slug($productable->name ?? 'product');

        $parts   = explode('-', $slug);
        $acronym = '';
        foreach ($parts as $part) {
            if (! empty($part)) {
                $acronym .= $part[0];
            }
        }

        // If the acronym is too short, supplement it from the start of the slug.
        if (mb_strlen($acronym) < 3) {
            $acronym = mb_substr(str_replace('-', '', $slug), 0, 3);
        }

        return mb_substr($acronym, 0, 5); // Max length of 5
    }

    /**
     * Generates a short code from a Term model.
     *
     * @param  Term  $term  The term model instance.
     * @return string A short code like "F25" from "Fall 2025". Returns "NA" if no term.
     */
    private function generateTermCode(Term $term): string
    {

        $month  = $term->start_date ? verta($term->start_date)->month : null;
        $season = match ($month) {
            1, 2, 3    => 'S',  // Spring
            4, 5, 6    => 'SU',  // Summer
            7, 8, 9    => 'F',  // Fall
            10, 11, 12 => 'W', // Winter
            default    => 'X', // Unknown
        };

        $year = $term->academic_year ? mb_substr($term->academic_year, 0, 4) : '0000';

        if ($term->academic_year && ! preg_match('/^\d{4}-\d{4}$/', $term->academic_year)) {
            $year = '0000';
        }

        return "{$season}{$year}";
    }

    /**
     * Generates a short code from the delivery option's fulfillment, type and deliverymethod.
     *
     * @param  ProductDeliveryOptionCreateData  $data  .
     * @return string A short code like "LOC".
     */
    private function generateDeliveryCode(ProductDeliveryOptionCreateData $data): string
    {
        $typeCode = match ($data->fulfillment_type) {
            FulfillmentTypeEnum::IN_PERSON_SERVICE->value => 'INP',
            FulfillmentTypeEnum::ONLINE_SERVICE->value    => 'ONL',
            FulfillmentTypeEnum::OFFLINE_SERVICE->value   => 'OFF',
            FulfillmentTypeEnum::DIGITAL->value           => 'DIG',
            default                                       => 'OTH',
        };

        $methodCode = match ($data->delivery_method) {
            DeliveryMethodEnum::IN_PERSON->value                 => 'INP',
            DeliveryMethodEnum::LIVE_SESSION_BBB->value          => 'LIVE-BBB',
            DeliveryMethodEnum::LIVE_SESSION_SKYROOM->value      => 'LIVE-SKY',
            DeliveryMethodEnum::LMS_MOODLE->value                => 'LMS',
            DeliveryMethodEnum::VIDEO_PLATFORM_SPOTPLAYER->value => 'VID',
            DeliveryMethodEnum::DIRECT_DOWNLOAD->value           => 'DLD',
            // @codeCoverageIgnoreStart
            // This should never happen because of validation, but just in case...
            default => 'OTH',
            // @codeCoverageIgnoreEnd
        };

        return "{$typeCode}-{$methodCode}";
    }
}
