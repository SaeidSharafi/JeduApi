<?php

declare(strict_types=1);

namespace App\Data\Admin\ProductDeliveryOption;

use App\Actions\Admin\ProductDeliveryOption\GetDeliveryDetailsValidationRulesAction;
use App\Data\Transformer\CarbonFromJalaliString;
use App\Enums\Content\PublicationStatusEnum;
use App\Enums\Product\DeliveryMethodEnum;
use App\Enums\Product\FulfillmentTypeEnum;
use App\Rules\ProductDeliveryOptionCheckRule;
use Carbon\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class ProductDeliveryOptionCreateData extends Data
{
    public function __construct(
        public string $name,
        public string $fulfillment_type,
        public string $delivery_method,
        public int $price,
        public array $teachers,
        public string $status,
        #[MapInputName('details')]
        public array $details_json,
        public bool $is_prepayment_available,
        public ?int $capacity,
        public ?int $prepayment_amount,
        public bool $is_featured,
        public ?int $featured_price,
        #[WithCast(CarbonFromJalaliString::class, 'Y-m-d H:i:s')]
        public ?Carbon $featured_price_start_date,
        #[WithCast(CarbonFromJalaliString::class, 'Y-m-d H:i:s')]
        public ?Carbon $featured_price_end_date,
        #[WithCast(CarbonFromJalaliString::class, 'Y-m-d')]
        public ?Carbon $registration_start_date,
        #[WithCast(CarbonFromJalaliString::class, 'Y-m-d')]
        public ?Carbon $registration_end_date,
        #[WithCast(CarbonFromJalaliString::class, 'Y-m-d')]
        public ?Carbon $available_from,
        #[WithCast(CarbonFromJalaliString::class, 'Y-m-d')]
        public ?Carbon $available_to,
        public ?int $access_days,
        public ?string $sku = null,
    ) {
        if (isset($this->details_json['sart_date']) && is_string($this->details_json['sart_date'])) {
            $this->details_json['sart_date'] = verta($this->details_json['sart_date'])->toCarbon()->format('Y-m-d');
        }
    }

    /**
     * Define the validation rules for the data.
     *
     * @return array<string, array<int,mixed>>
     */
    public static function rules(?ValidationContext $context = null): array
    {
        $now       = verta()->format('Y-m-d');
        $baseRules = [
            'name'             => ['required', 'string', 'max:255'],
            'sku'              => ['nullable', 'alpha_dash', 'max:255'],
            'fulfillment_type' => ['required', 'bail', 'string', Rule::enum(FulfillmentTypeEnum::class)],
            'delivery_method'  => [
                'required', 'bail', 'string', Rule::enum(DeliveryMethodEnum::class),
                new ProductDeliveryOptionCheckRule(),
            ],
            'price'                     => ['required', 'integer', 'min:0'],
            'capacity'                  => ['nullable', 'integer', 'min:0'],
            'status'                    => ['required', 'string', Rule::enum(PublicationStatusEnum::class)],
            'is_prepayment_available'   => ['required', 'boolean'],
            'prepayment_amount'         => ['nullable', 'integer', 'min:0'],
            'details'                   => ['present', 'array'],
            'is_featured'               => ['required', 'boolean'],
            'featured_price'            => ['nullable', 'integer', 'min:0'],
            'featured_price_start_date' => ['nullable', 'jdate:Y-m-d H:i:s'],
            'featured_price_end_date'   => [
                'nullable', 'jdate:Y-m-d H:i:s', 'jdate_after:'.request('featured_price_start_date').',Y-m-d H:i:s',
            ],
            'registration_start_date' => ['nullable', 'jdate:Y-m-d'],
            'registration_end_date'   => [
                'nullable', 'jdate:Y-m-d', 'jdate_after:'.request('registration_start_date').',Y-m-d',
            ],
            'available_from' => ['nullable', 'jdate:Y-m-d'],
            'available_to'   => [
                'nullable', 'jdate:Y-m-d', 'jdate_after:'.request('available_from').',Y-m-d',
            ],
            'access_days'             => ['nullable', 'integer', 'min:1'],
            'teachers'                => ['required', 'array'],
            'teachers.*'              => ['required', 'integer', 'exists:teachers,id'],
            'details.ims_course_code' => ['nullable', 'string'],
            'details.sart_date'       => ['nullable', 'jdate:Y-m-d'],
            'details.schedule_days'   => ['nullable', 'array'],
            'details.duration'        => ['sometimes', 'integer', 'min:1'],
        ];

        $detailsRulesAction      = app(GetDeliveryDetailsValidationRulesAction::class);
        $conditionalDetailsRules = $detailsRulesAction->handle(
            $context->payload['fulfillment_type'] ?? null,
            $context->payload['delivery_method']  ?? null,
            $context->payload['details']          ?? null
        );

        return array_merge($baseRules, $conditionalDetailsRules);
    }

    public static function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {});
    }

    public static function attributes(...$args): array
    {
        return [
            'name'                      => __('validation.attributes.product_delivery_option.name'),
            'sku'                       => __('validation.attributes.product_delivery_option.sku'),
            'fulfillment_type'          => __('validation.attributes.product_delivery_option.fulfillment_type'),
            'delivery_method'           => __('validation.attributes.product_delivery_option.delivery_method'),
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
            'registration_start_date'   => __('validation.attributes.product_delivery_option.registration_start_date'),
            'registration_end_date'     => __('validation.attributes.product_delivery_option.registration_end_date'),
            'available_from'            => __('validation.attributes.product_delivery_option.available_from'),
            'available_to'              => __('validation.attributes.product_delivery_option.available_to'),
            'access_days'               => __('validation.attributes.product_delivery_option.access_days'),
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
    public function bodyParameters(): array
    {
        return [
            'name' => [
                'description' => 'Human-readable label for this delivery option, shown to students (e.g. "Online Live - Spring Term").',
                'required'    => true,
                'example'     => 'Online Live Session - Spring 1404',
            ],
            'sku' => [
                'description' => 'Optional unique SKU. Auto-generated from delivery method + term if omitted. Use alpha-dash characters only.',
                'required'    => false,
                'example'     => 'LIVE-SKY-1404-SPRING',
            ],
            'fulfillment_type' => [
                'description' => 'Broad fulfillment category. Must match the delivery_method you choose. Allowed values: `digital`, `online_service`, `offline_service`, `in_person_service`.',
                'required'    => true,
                'example'     => 'online_service',
            ],
            'delivery_method' => [
                'description' => 'Specific delivery mechanism. Must belong to the chosen `fulfillment_type`:
- `digital` → `direct_download`
- `online_service` → `lms_moodle`, `live_session_bbb`, `live_session_skyroom`
- `offline_service` → `video_platform_spotplayer`
- `in_person_service` → `in_person`

Each value determines which `details.*` fields are required.',
                'required' => true,
                'example'  => 'live_session_skyroom',
            ],
            'price' => [
                'description' => 'Full price in Rials (smallest currency unit). Use 0 for free options.',
                'required'    => true,
                'example'     => 5000000,
            ],
            'capacity' => [
                'description' => 'Maximum number of enrollments allowed. Null means unlimited.',
                'required'    => false,
                'example'     => 50,
            ],
            'status' => [
                'description' => 'Publication status controlling visibility in the shop. Allowed: `draft`, `published`, `archived`.',
                'required'    => true,
                'example'     => 'draft',
            ],
            'is_prepayment_available' => [
                'description' => 'Whether students can reserve a seat by paying a partial amount upfront. Requires `prepayment_amount` when true.',
                'required'    => true,
                'example'     => false,
            ],
            'prepayment_amount' => [
                'description' => 'Partial payment amount in Rials required to reserve a seat. Only relevant when `is_prepayment_available` is true.',
                'required'    => false,
                'example'     => 1000000,
            ],
            'is_featured' => [
                'description' => 'Marks this option as featured, enabling a promotional discounted price display. Requires `featured_price` and optionally date bounds.',
                'required'    => true,
                'example'     => false,
            ],
            'featured_price' => [
                'description' => 'Discounted price to display when `is_featured` is true. Must be less than `price`.',
                'required'    => false,
                'example'     => 4500000,
            ],
            'featured_price_start_date' => [
                'description' => 'Jalali datetime (Y-m-d H:i:s) when the featured price becomes active. Null means immediately.',
                'required'    => false,
                'example'     => '1404-06-15 00:00:00',
            ],
            'featured_price_end_date' => [
                'description' => 'Jalali datetime (Y-m-d H:i:s) when the featured price expires. Must be after `featured_price_start_date`.',
                'required'    => false,
                'example'     => '1404-07-15 23:59:59',
            ],
            'registration_start_date' => [
                'description' => 'Jalali date (Y-m-d) when students can start purchasing this option. Null means open immediately.',
                'required'    => false,
                'example'     => '1404-06-01',
            ],
            'registration_end_date' => [
                'description' => 'Jalali date (Y-m-d) after which purchases are no longer accepted. Must be after `registration_start_date`.',
                'required'    => false,
                'example'     => '1404-06-30',
            ],
            'available_from' => [
                'description' => 'Jalali date (Y-m-d) when enrolled students gain access to the content.',
                'required'    => false,
                'example'     => '1404-06-15',
            ],
            'available_to' => [
                'description' => 'Jalali date (Y-m-d) when student access expires. Must be after `available_from`.',
                'required'    => false,
                'example'     => '1404-12-31',
            ],
            'access_days' => [
                'description' => 'Rolling access window in days starting from the enrollment date. Overrides `available_to` for per-student expiry. E.g. 90 means access for 90 days after purchase.',
                'required'    => false,
                'example'     => 90,
            ],
            'teachers' => [
                'description' => 'Array of teacher IDs to associate with this delivery option. Send an empty array if no teachers.',
                'required'    => true,
                'example'     => [1, 2],
            ],
            'teachers.*' => [
                'description' => 'A valid teacher ID (must exist in the teachers table).',
                'required'    => true,
                'example'     => 1,
            ],

            // ── details object ────────────────────────────────────────────────
            'details' => [
                'description' => 'Delivery-method-specific configuration object. Must always be sent (use an empty object `{}` when no sub-fields are needed). The required sub-fields depend on `delivery_method`.',
                'required'    => true,
                'example'     => ['moodle_course_id' => 120, 'activity_id' => 1],
            ],

            // ── common details fields (all delivery methods) ──────────────────
            'details.ims_course_code' => [
                'description' => 'All delivery methods. IMS LTI course code used for external integrations. Optional.',
                'required'    => false,
                'example'     => 'COURSE-001',
            ],
            'details.sart_date' => [
                'description' => 'All delivery methods. Informational session start date in Jalali format (Y-m-d). Does not control access — use `available_from` for access control. Note: field key is `sart_date` (legacy spelling).',
                'required'    => false,
                'example'     => '1404-06-15',
            ],
            'details.schedule_days' => [
                'description' => 'All delivery methods. Days of the week when sessions are held. Informational only.',
                'required'    => false,
                'example'     => ['sat', 'sun'],
            ],
            'details.duration' => [
                'description' => 'All delivery methods. Total course duration in hours. Informational only.',
                'required'    => false,
                'example'     => 24,
            ],
            'details.moodle_quiz_course_id' => [
                'description' => 'For `live_session_bbb`, `live_session_skyroom`, `video_platform_spotplayer`, `in_person`. Moodle course ID used exclusively for the quiz section shown in the student\'s course page. When set, the system provisions a separate Moodle enrollment just for quizzes, independent of the primary delivery method.',
                'required'    => false,
                'example'     => 145,
            ],

            // ── lms_moodle ────────────────────────────────────────────────────
            'details.moodle_course_id' => [
                'description' => 'For `lms_moodle` (**required**). The Moodle course ID students will be enrolled into.',
                'required'    => false,
                'example'     => 120,
            ],
            'details.activity_id' => [
                'description' => 'For `lms_moodle`. Moodle activity/module ID to track for completion. Optional.',
                'required'    => false,
                'example'     => 5,
            ],
            'details.enrollment_start_date' => [
                'description' => 'For `lms_moodle`. Jalali datetime (Y-m-d H:i:s) when the Moodle enrollment becomes active.',
                'required'    => false,
                'example'     => '1404-06-15 09:00:00',
            ],
            'details.enrollment_end_date' => [
                'description' => 'For `lms_moodle`. Jalali datetime (Y-m-d H:i:s) when the Moodle enrollment expires.',
                'required'    => false,
                'example'     => '1404-12-31 23:59:59',
            ],

            // ── direct_download ───────────────────────────────────────────────
            'details.max_downloads' => [
                'description' => 'For `direct_download` (**required**). How many times a student can download the file.',
                'required'    => false,
                'example'     => 5,
            ],
            'details.expiration_date' => [
                'description' => 'For `direct_download`. Jalali datetime (Y-m-d H:i:s) after which the download link expires.',
                'required'    => false,
                'example'     => '1404-12-31 23:59:59',
            ],

            // ── in_person ─────────────────────────────────────────────────────
            'details.address' => [
                'description' => 'For `in_person` (**required**). Full physical address of the venue.',
                'required'    => false,
                'example'     => 'سالن اصلی، خیابان ولیعصر',
            ],
            'details.map_url' => [
                'description' => 'For `in_person`. Google Maps or similar URL to the venue location.',
                'required'    => false,
                'example'     => 'https://maps.google.com/?q=35.6892,51.3890',
            ],
            'details.schedule' => [
                'description' => 'For `in_person` (**required**). Human-readable session schedule.',
                'required'    => false,
                'example'     => 'شنبه‌ها ۹ تا ۱۱',
            ],
            'details.additional_info' => [
                'description' => 'For `in_person`. Extra instructions or notes shown to enrolled students.',
                'required'    => false,
                'example'     => 'لطفاً لپ‌تاپ همراه داشته باشید',
            ],

            // ── video_platform_spotplayer ─────────────────────────────────────
            'details.spot_id' => [
                'description' => 'For `video_platform_spotplayer` (**required**). The SpotPlayer content/course ID used to generate student license keys.',
                'required'    => false,
                'example'     => 'SP-COURSE-001',
            ],
            'details.updated_at' => [
                'description' => 'For `video_platform_spotplayer`. Jalali date (Y-m-d) of the last content update. Informational.',
                'required'    => false,
                'example'     => '1405-01-01',
            ],

            // ── live_session_skyroom ──────────────────────────────────────────
            'details.room_id' => [
                'description' => 'For `live_session_skyroom` (**required**). Numeric ID of the Skyroom room, as shown on the Skyroom admin panel. Rooms must be pre-created on the panel — this ID is used to provision student access and generate join URLs.',
                'required'    => false,
                'example'     => 42,
            ],

            // ── live_session_bbb ──────────────────────────────────────────────
            'details.auto_create_meeting' => [
                'description' => 'For `live_session_bbb`. When true, the provisioning job automatically creates the BBB meeting. When false, you must supply an existing `meeting_id`.',
                'required'    => false,
                'example'     => true,
            ],
            'details.meeting_id' => [
                'description' => 'For `live_session_bbb`. Existing BBB meeting ID to reuse. Only relevant when `auto_create_meeting` is false.',
                'required'    => false,
                'example'     => 'bbb-meeting-spring-1404',
            ],
            'details.moderator_password' => [
                'description' => 'For `live_session_bbb`. Moderator (presenter) password. Falls back to the global BBB default if omitted.',
                'required'    => false,
                'example'     => 'modSecret123',
            ],
            'details.session_duration' => [
                'description' => 'For `live_session_bbb`. Maximum session length in minutes. BBB will end the meeting after this duration.',
                'required'    => false,
                'example'     => 90,
            ],
            'details.allow_start_stop_recording' => [
                'description' => 'For `live_session_bbb`. Allows moderators to start and stop recording mid-session.',
                'required'    => false,
                'example'     => true,
            ],
            'details.allow_mods_to_unmute_users' => [
                'description' => 'For `live_session_bbb`. Allows moderators to unmute participants.',
                'required'    => false,
                'example'     => true,
            ],
            'details.lock_settings_disable_cam' => [
                'description' => 'For `live_session_bbb`. Prevents participants from enabling their camera.',
                'required'    => false,
                'example'     => false,
            ],
            'details.lock_settings_disable_mic' => [
                'description' => 'For `live_session_bbb`. Prevents participants from unmuting their microphone.',
                'required'    => false,
                'example'     => false,
            ],
            'details.lock_settings_disable_private_chat' => [
                'description' => 'For `live_session_bbb`. Disables private (direct) messages between participants.',
                'required'    => false,
                'example'     => false,
            ],
            'details.lock_settings_disable_public_chat' => [
                'description' => 'For `live_session_bbb`. Disables the public chat for all participants.',
                'required'    => false,
                'example'     => false,
            ],
            'details.lock_settings_disable_note' => [
                'description' => 'For `live_session_bbb`. Disables the shared notes panel.',
                'required'    => false,
                'example'     => false,
            ],
            'details.lock_settings_locked_layout' => [
                'description' => 'For `live_session_bbb`. Locks the layout so participants cannot change their view.',
                'required'    => false,
                'example'     => false,
            ],

            'details.planned_duration_minutes' => [
                'description' => 'For `live_session_skyroom`. Expected session length in minutes. Informational;',
                'required'    => false,
                'example'     => 90,
            ],

            // ── shared: live_session_bbb + live_session_skyroom ───────────────
            'details.attendee_password' => [
                'description' => 'For `live_session_bbb`. Password students must enter to join. Falls back to global defaults if omitted.',
                'required'    => false,
                'example'     => 'attend1404',
            ],
            'details.record_session' => [
                'description' => 'For `live_session_bbb`. Whether this session should be recorded.',
                'required'    => false,
                'example'     => true,
            ],
            'details.auto_start_recording' => [
                'description' => 'For `live_session_bbb`. Start recording automatically when the first participant joins.',
                'required'    => false,
                'example'     => true,
            ],
            'details.webcams_only_for_moderator' => [
                'description' => 'For `live_session_bbb`. Restricts video to moderators/presenters only; participants cannot share their camera.',
                'required'    => false,
                'example'     => true,
            ],
            'details.mute_on_start' => [
                'description' => 'For `live_session_bbb`. Mutes all participants when they first join.',
                'required'    => false,
                'example'     => true,
            ],
            'details.welcome_message' => [
                'description' => 'For `live_session_bbb`. Message shown to participants when they enter the room.',
                'required'    => false,
                'example'     => 'به کلاس خوش آمدید!',
            ],
            'details.default_presentation_url' => [
                'description' => 'For `live_session_bbb`. URL of a PDF or presentation file to load automatically when the session starts.',
                'required'    => false,
                'example'     => 'https://example.com/slides/session-1.pdf',
            ],
            'details.admin_notes' => [
                'description' => 'For `live_session_bbb` or `live_session_skyroom`. Internal admin notes about this session setup. Not visible to students.',
                'required'    => false,
                'example'     => 'Backup room: room-id 43',
            ],
        ];
    }
}
