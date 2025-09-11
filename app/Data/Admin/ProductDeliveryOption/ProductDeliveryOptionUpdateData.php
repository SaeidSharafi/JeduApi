<?php

declare(strict_types=1);

namespace App\Data\Admin\ProductDeliveryOption;

use App\Actions\Admin\ProductDeliveryOption\GetDeliveryDetailsValidationRulesAction;
use App\Data\Transformer\CarbonFromJalaliString;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class ProductDeliveryOptionUpdateData extends Data
{
    public function __construct(
        public string $name,
        public string $sku,
        public int $price,
        public string $status,
        #[MapInputName('details')]
        public array $details_json,
        public array $teachers,
        public ?int $capacity = null,
        public bool $is_prepayment_available = false,
        public ?int $prepayment_amount = null,
        public bool $is_featured = false,
        public ?int $featured_price = null,
        #[WithCast(CarbonFromJalaliString::class, 'Y-m-d H:i:s')]
        public ?Carbon $featured_price_start_date = null,
        #[WithCast(CarbonFromJalaliString::class, 'Y-m-d H:i:s')]
        public ?Carbon $featured_price_end_date = null,
    ) {}

    /**
     * Define the validation rules for the data.
     *
     * @return array<string, array<int,mixed>>
     */
    public static function rules(ValidationContext $context): array
    {
        $baseRules = [
            'name' => ['required', 'string', 'max:255'],
            'sku'  => [
                'required', 'alpha_dash', 'max:255',
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
            'featured_price_start_date' => ['nullable', 'jdate:Y-m-d H:i:s'],
            'featured_price_end_date'   => ['nullable', 'jdate:Y-m-d H:i:s', 'jdate_after:'.request('featured_price_start_date').',Y-m-d H:i:s'],
            'teachers'                  => ['required', 'array'],
            'teachers.*'                => ['required', 'integer', 'exists:teachers,id'],
        ];

        // Get the existing delivery option to determine its delivery method for details validation
        $deliveryOption  = request()->route()->parameter('delivery_option');
        $deliveryMethod  = $deliveryOption?->delivery_method?->value  ?? $context->payload['delivery_method'] ?? null;
        $fulfillmentType = $deliveryOption?->fulfillment_type?->value ?? $context->payload['fulfillment_type'] ?? null;

        $detailsRulesAction      = app(GetDeliveryDetailsValidationRulesAction::class);
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
            'teachers'                  => __('validation.attributes.product_delivery_option.teachers'),
            'teachers.*'                => __('validation.attributes.product_delivery_option.teacher_id'),
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
                'description' => 'Dynamic details object that varies based on delivery_method. See delivery method specific examples below.',
                'required'    => true,
                'example'     => [
                    'course_idnumber'       => 'COURSE-001',
                    'activiyId'             => 1,
                    'enrollment_start_date' => '2025-06-15 09:00:00',
                    'enrollment_end_date'   => '2025-12-31 23:59:59',
                ],
            ],
            'details.course_idnumber' => [
                'description' => 'For `lms_moodle`. Course ID number in Moodle (required)',
                'required'    => false,
                'example'     => 'COURSE-001',
            ],
            'details.activity_id' => [
                'description' => 'For `lms_moodle`. Activity ID in Moodle (required)',
                'required'    => false,
                'example'     => 1,
            ],
            'details.enrollment_start_date' => [
                'description' => 'For `lms_moodle`. Enrollment start date for Moodle course',
                'required'    => false,
                'example'     => '2025-06-15 09:00:00',
            ],
            'details.enrollment_end_date' => [
                'description' => 'For `lms_moodle`. Enrollment end date for Moodle course',
                'required'    => false,
                'example'     => '2025-12-31 23:59:59',
            ],
            'details.max_downloads' => [
                'description' => 'For `direct_download`. Maximum number of downloads allowed (required)',
                'required'    => false,
                'example'     => 5,
            ],
            'details.expiration_date' => [
                'description' => 'When delivery_method is direct_download. Download expiration date',
                'required'    => false,
                'example'     => '2025-12-31 23:59:59',
            ],
            'details.location' => [
                'description' => 'For `in_person`. Physical location for in-person sessions (required)',
                'required'    => false,
                'example'     => 'Room 101, Main Building',
            ],
            'details.duration' => [
                'description' => 'For `in_person`. Duration of in-person session (required)',
                'required'    => false,
                'example'     => '2 hours',
            ],
            'details.schedule' => [
                'description' => 'For `in_person`. Schedule for in-person session (required)',
                'required'    => false,
                'example'     => 'Saturdays 9:00-11:00',
            ],
            'details.additional_info' => [
                'description' => 'For `in_person`. Additional information for in-person session',
                'required'    => false,
                'example'     => 'Please bring your own laptop',
            ],
            'details.course_id' => [
                'description' => 'For `video_platform_spotplayer`. Course ID in SpotPlayer (requiredr)',
                'required'    => false,
                'example'     => 'SP-COURSE-001',
            ],
            'details.moderator_password' => [
                'description' => 'For `live_session_bbb`. Moderator password for BigBlueButton session',
                'required'    => false,
                'example'     => 'mod123',
            ],
            'details.attendee_password' => [
                'description' => 'For `live_session_bbb` or `live_session_skyroom`. Attendee password for live session',
                'required'    => false,
                'example'     => 'attend123',
            ],
            'details.record_session' => [
                'description' => 'For `live_session_bbb` or `live_session_skyroom`. Whether to record the live session',
                'required'    => false,
                'example'     => true,
            ],
            'details.meeting_name_identifier' => [
                'description' => 'For `live_session_skyroom`. Meeting name identifier for Skyroom (required)',
                'required'    => false,
                'example'     => 'course-001-session',
            ],
            'details.session_duration' => [
                'description' => 'For `live_session_bbb` or `live_session_skyroom`. Duration of the live session in minutes',
                'required'    => false,
                'example'     => 60,
            ],
            'details.default_presentation_url' => [
                'description' => 'For `live_session_bbb` or `live_session_skyroom`. Default presentation URL for the session',
                'required'    => false,
                'example'     => 'https://example.com/presentation',
            ],
            'details.admin_notes' => [
                'description' => 'For `live_session_bbb` or `live_session_skyroom`. Admin notes for the session',
                'required'    => false,
                'example'     => 'This is a test session for course 001',
            ],
            'details.webcams_only_for_moderator' => [
                'description' => 'For `live_session_bbb`. Whether webcams are only for the moderator',
                'required'    => false,
                'example'     => true,
            ],
            'details.mute_on_start' => [
                'description' => 'For `live_session_bbb`. Whether to mute all participants on start',
                'required'    => false,
                'example'     => true,
            ],
            'details.allow_mods_to_unmute_users' => [
                'description' => 'For `live_session_bbb`. Whether moderators can unmute users',
                'required'    => false,
                'example'     => true,
            ],
            'details.lock_settings_disable_cam' => [
                'description' => 'For `live_session_bbb`. Whether to disable camera for participants',
                'required'    => false,
                'example'     => false,
            ],
            'details.lock_settings_disable_mic' => [
                'description' => 'For `live_session_bbb`. Whether to disable microphone for participants',
                'required'    => false,
                'example'     => false,
            ],
            'details.lock_settings_disable_private_chat' => [
                'description' => 'For `live_session_bbb`. Whether to disable private chat for participants',
                'required'    => false,
                'example'     => false,
            ],
            'details.lock_settings_disable_public_chat' => [
                'description' => 'For `live_session_bbb`. Whether to disable public chat for participants',
                'required'    => false,
                'example'     => false,
            ],
            'details.lock_settings_disable_note' => [
                'description' => 'For `live_session_bbb`. Whether to disable note-taking for participants',
                'required'    => false,
                'example'     => false,
            ],
            'details.lock_settings_locked_layout' => [
                'description' => 'For `live_session_bbb`. Whether to lock the layout settings for the session',
                'required'    => false,
                'example'     => false,
            ],
            'details.welcome_message' => [
                'description' => 'For `live_session_bbb` or `live_session_skyroom`. Welcome message for the session',
                'required'    => false,
                'example'     => 'Welcome to the live session!',
            ],
            'details.planned_duration_minutes' => [
                'description' => 'For `live_session_skyroom`. Planned duration of the session in minutes',
                'required'    => false,
                'example'     => 90,
            ],
            'details.allow_start_stop_recording' => [
                'description' => 'For `live_session_bbb`. Whether moderators can start/stop recording during the session',
                'required'    => false,
                'example'     => true,
            ],
            'details.auto_start_recording' => [
                'description' => 'For `live_session_bbb` or `live_session_skyroom`. Whether to automatically start recording the session',
                'required'    => false,
                'example'     => true,
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
            'teachers' => [
                'description' => 'List of teacher IDs associated with this delivery option',
                'required'    => true,
                'example'     => [1, 2, 3],
            ],
            'teachers.*' => [
                'description' => 'List of teacher IDs associated with this delivery option',
                'required'    => true,
                'example'     => [1, 2, 3],
            ],
        ];
    }
}
