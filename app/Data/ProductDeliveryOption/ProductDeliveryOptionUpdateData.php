<?php

namespace App\Data\ProductDeliveryOption;

use App\Actions\ProductDeliveryOption\GetDeliveryDetailsValidationRulesAction;
use App\Data\Transformer\CarbonFromJalaliString;
use App\Enums\FulfillmentTypeEnum;
use App\Rules\ProductDeliveryOptionCheckRule;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

class ProductDeliveryOptionUpdateData extends Data
{
    public function __construct(
        public string $name,
        public string $sku,
        public int $price,
        public ?int $capacity = null,
        public string $status = 'draft',
        public bool $is_prepayment_available = false,
        public ?int $prepayment_amount = null,
        #[MapInputName('details')]
        public ?array $details_json = null,
        public bool $is_featured = false,
        public ?int $featured_price = null,
        #[WithCast(CarbonFromJalaliString::class, 'Y-m-d H:i:s')]
        public ?Carbon $featured_price_start_date = null,
        #[WithCast(CarbonFromJalaliString::class, 'Y-m-d H:i:s')]
        public ?Carbon $featured_price_end_date = null,
    ) {
    }

    /**
     * Define the validation rules for the data.
     *
     * @return array<string, array<int,mixed>>
     */
    public static function rules(ValidationContext $context): array
    {
        $baseRules = [
            'name'                      => ['required', 'string', 'max:255'],
            'sku'                       => [
                'required', 'string', 'max:255',
                Rule::unique('product_delivery_options', 'sku')->where(function (Builder $query) {
                    $delivery_option = request()->route()->parameter('delivery_option');
                    if ($delivery_option && $delivery_option->id) {
                        $query->whereNot('id', $delivery_option->id);
                    }

                    return $query;
                }),
            ],
            'price'                     => ['required', 'integer', 'min:0'],
            'capacity'                  => ['nullable', 'integer', 'min:0'],
            'status'                    => ['required', 'string', 'in:draft,published,archived'],
            'is_prepayment_available'   => ['boolean'],
            'prepayment_amount'         => ['nullable', 'integer', 'min:0'],
            'details'                   => ['present', 'array'],
            'is_featured'               => ['required', 'boolean'],
            'featured_price'            => ['nullable', 'integer', 'min:0'],
            'featured_price_start_date' => ['nullable', 'date_format:Y-m-d H:i:s'],
            'featured_price_end_date'   => ['nullable', 'date_format:Y-m-d H:i:s', 'after:featured_price_start_date'],
        ];

        // Get the existing delivery option to determine its delivery method for details validation
        $deliveryOption = request()->route()->parameter('delivery_option');
        $deliveryMethod = $deliveryOption?->delivery_method?->value ?? $context->payload['delivery_method'] ?? null;
        $fulfillmentType = $deliveryOption?->fulfillment_type?->value ?? $context->payload['fulfillment_type'] ?? null;

        $detailsRulesAction = app(GetDeliveryDetailsValidationRulesAction::class);
        $conditionalDetailsRules = $detailsRulesAction->handle(
            $fulfillmentType,
            $deliveryMethod,
            $context->payload['details'] ?? null,
            'details'
        );
        return array_merge($baseRules, $conditionalDetailsRules);
    }

    public static function attributes(...$args): array
    {
        return [
            'name'                      => __('validation.attributes.product_delivery_option.name'),
            'sku'                       => __('validation.attributes.product_delivery_option.sku'),
            'price'                     => __('validation.attributes.product_delivery_option.price'),
            'capacity'                  => __('validation.attributes.product_delivery_option.capacity'),
            'status'                    => __('validation.attributes.product_delivery_option.status'),
            'is_prepayment_available'   => __('validation.attributes.product_delivery_option.is_prepayment_available'),
            'prepayment_amount'         => __('validation.attributes.product_delivery_option.prepayment_amount'),
            'details'                   => __('validation.attributes.product_delivery_option.details_json'),
            'is_featured'               => __('validation.attributes.product_delivery_option.is_featured'),
            'featured_price'            => __('validation.attributes.product_delivery_option.featured_price'),
            'featured_price_start_date' => __('validation.attributes.product_delivery_option.featured_price_start_date'),
            'featured_price_end_date'   => __('validation.attributes.product_delivery_option.featured_price_end_date'),
        ];
    }

    public static function messages(...$args): array
    {
        return [
            'details.array' => __('validation.custom.product_delivery_option.details_json.array'),
        ];
    }

    /**
     * @codeCoverageIgnore
     *
     * @return array<string, array<string, mixed>>
     */
    public function bodyParameters(): array
    {
        return [
            'name' => [
                'description' => 'Name of the delivery option',
                'required'    => true,
                'example'     => 'Online Live Session',
            ],
            'sku' => [
                'description' => 'Unique SKU for the delivery option',
                'required'    => true,
                'example'     => 'COURSE-001-ONLINE',
            ],
            'price' => [
                'description' => 'Price of the delivery option in the smallest currency unit (e.g., cents)',
                'required'    => true,
                'example'     => 5000000,
            ],
            'capacity' => [
                'description' => 'Maximum capacity for this delivery option',
                'required'    => false,
                'example'     => 50,
            ],
            'status' => [
                'description' => 'Publication status of the delivery option',
                'required'    => true,
                'example'     => 'draft',
            ],
            'is_prepayment_available' => [
                'description' => 'Whether prepayment is available for this option',
                'required'    => false,
                'example'     => true,
            ],
            'prepayment_amount' => [
                'description' => 'Amount required for prepayment in the smallest currency unit',
                'required'    => false,
                'example'     => 1000000,
            ],
            'details' => [
                'description' => 'Dynamic details object that varies based on delivery_method. See delivery method specific examples below. Note: delivery_method and fulfillment_type cannot be changed during update.',
                'required'    => false,
                'example'     => [
                    'course_idnumber' => 'COURSE-001',
                    'activiyId' => 1,
                    'enrollment_start_date' => '2025-06-15 09:00:00',
                    'enrollment_end_date' => '2025-12-31 23:59:59',
                ],
            ],
            'details.course_idnumber' => [
                'description' => 'Course ID number in Moodle (required when delivery_method is lms_moodle)',
                'required'    => false,
                'example'     => 'COURSE-001',
            ],
            'details.activiyId' => [
                'description' => 'Activity ID in Moodle (required when delivery_method is lms_moodle)',
                'required'    => false,
                'example'     => 1,
            ],
            'details.enrollment_start_date' => [
                'description' => 'Enrollment start date for Moodle course (when delivery_method is lms_moodle)',
                'required'    => false,
                'example'     => '2025-06-15 09:00:00',
            ],
            'details.enrollment_end_date' => [
                'description' => 'Enrollment end date for Moodle course (when delivery_method is lms_moodle)',
                'required'    => false,
                'example'     => '2025-12-31 23:59:59',
            ],
            'details.max_downloads' => [
                'description' => 'Maximum number of downloads allowed (required when delivery_method is direct_download)',
                'required'    => false,
                'example'     => 5,
            ],
            'details.expiration_date' => [
                'description' => 'Download expiration date (when delivery_method is direct_download)',
                'required'    => false,
                'example'     => '2025-12-31 23:59:59',
            ],
            'details.location' => [
                'description' => 'Physical location for in-person sessions (required when delivery_method is in_person)',
                'required'    => false,
                'example'     => 'Room 101, Main Building',
            ],
            'details.duration' => [
                'description' => 'Duration of in-person session (required when delivery_method is in_person)',
                'required'    => false,
                'example'     => '2 hours',
            ],
            'details.schedule' => [
                'description' => 'Schedule for in-person session (required when delivery_method is in_person)',
                'required'    => false,
                'example'     => 'Saturdays 9:00-11:00',
            ],
            'details.additional_info' => [
                'description' => 'Additional information for in-person session (when delivery_method is in_person)',
                'required'    => false,
                'example'     => 'Please bring your own laptop',
            ],
            'details.course_id' => [
                'description' => 'Course ID in SpotPlayer (required when delivery_method is video_platform_spotplayer)',
                'required'    => false,
                'example'     => 'SP-COURSE-001',
            ],
            'details.moderator_password' => [
                'description' => 'Moderator password for BigBlueButton session (when delivery_method is live_session_bbb)',
                'required'    => false,
                'example'     => 'mod123',
            ],
            'details.attendee_password' => [
                'description' => 'Attendee password for live session (when delivery_method is live_session_bbb or live_session_skyroom)',
                'required'    => false,
                'example'     => 'attend123',
            ],
            'details.record_session' => [
                'description' => 'Whether to record the live session (when delivery_method is live_session_bbb or live_session_skyroom)',
                'required'    => false,
                'example'     => true,
            ],
            'details.meeting_name_identifier' => [
                'description' => 'Meeting name identifier for Skyroom (required when delivery_method is live_session_skyroom)',
                'required'    => false,
                'example'     => 'course-001-session',
            ],
            'is_featured' => [
                'description' => 'Whether this delivery option is featured',
                'required'    => true,
                'example'     => false,
            ],
            'featured_price' => [
                'description' => 'Featured price for the delivery option in the smallest currency unit',
                'required'    => false,
                'example'     => 4500000,
            ],
            'featured_price_start_date' => [
                'description' => 'Start date for featured pricing',
                'required'    => false,
                'example'     => '2025-06-15 00:00:00',
            ],
            'featured_price_end_date' => [
                'description' => 'End date for featured pricing',
                'required'    => false,
                'example'     => '2025-07-15 23:59:59',
            ],
        ];
    }
}
