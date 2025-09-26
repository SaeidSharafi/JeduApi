<?php

declare(strict_types=1);

use App\Data\Admin\ProductDeliveryOption\DetailsData\LiveSessionBbbDetailsData;
use App\Data\Admin\ProductDeliveryOption\DetailsData\LiveSessionSkyroomDetailsData;
use App\Data\Admin\ProductDeliveryOption\DetailsData\VideoPlatformSpotplayerDetailsData;

beforeEach(function (): void {
    $this->mockProperty = Mockery::mock(Spatie\LaravelData\Support\DataProperty::class);
    $this->mockContext  = Mockery::mock(Spatie\LaravelData\Support\Creation\CreationContext::class);
    Storage::fake('public');
});
it('return EmptyDetailsData if value is null', function (): void {
    $caster                         = new App\Data\Casts\DeliveryOptionDetailCast();
    $properties['fulfillment_type'] = \App\Enums\Product\FulfillmentTypeEnum::DIGITAL;
    $properties['delivery_method']  = \App\Enums\Product\DeliveryMethodEnum::DIRECT_DOWNLOAD;
    $delivery_option                = $caster->cast($this->mockProperty, null, $properties, $this->mockContext);
    expect($delivery_option)->toBeInstanceOf(
        App\Data\Admin\ProductDeliveryOption\DetailsData\EmptyDetailsData::class
    );
});

it('return EmptyDetailsData if fulfillment_type or delivery_method is null', function (): void {
    $caster                         = new App\Data\Casts\DeliveryOptionDetailCast();
    $properties['fulfillment_type'] = null;
    $properties['delivery_method']  = \App\Enums\Product\DeliveryMethodEnum::DIRECT_DOWNLOAD;
    $details                        = [
        'max_downloads' => 10,
    ];
    $delivery_option = $caster->cast($this->mockProperty, $details, $properties, $this->mockContext);
    expect($delivery_option)->toBeInstanceOf(
        App\Data\Admin\ProductDeliveryOption\DetailsData\EmptyDetailsData::class
    );
});

it('return DirectDownloadDetailsData if delivery_method is DIRECT_DOWNLOAD', function (): void {
    $caster                         = new App\Data\Casts\DeliveryOptionDetailCast();
    $properties['fulfillment_type'] = \App\Enums\Product\FulfillmentTypeEnum::DIGITAL;
    $properties['delivery_method']  = \App\Enums\Product\DeliveryMethodEnum::DIRECT_DOWNLOAD;
    $details                        = [
        'max_downloads'   => 10,
        'expiration_date' => '2023-12-31 23:59:59',
    ];
    $delivery_option = $caster->cast($this->mockProperty, $details, $properties, $this->mockContext);
    expect($delivery_option)->toBeInstanceOf(
        App\Data\Admin\ProductDeliveryOption\DetailsData\DirectDownloadDetailsData::class
    );
});

it('return InPersonDetailsData if delivery_method is IN_PERSON', function (): void {
    $caster                         = new App\Data\Casts\DeliveryOptionDetailCast();
    $properties['fulfillment_type'] = \App\Enums\Product\FulfillmentTypeEnum::PHYSICAL;
    $properties['delivery_method']  = \App\Enums\Product\DeliveryMethodEnum::IN_PERSON;
    $details                        = [
        'location'        => 'Test Location',
        'duration'        => '20 Minute',
        'schedule'        => 'Sun-Mon',
        'additional_info' => null,
    ];
    $delivery_option = $caster->cast($this->mockProperty, $details, $properties, $this->mockContext);
    expect($delivery_option)
        ->toBeInstanceOf(App\Data\Admin\ProductDeliveryOption\DetailsData\InPersonDetailsData::class)
        ->and($delivery_option->location)->toBe('Test Location')
        ->and($delivery_option->duration)->toBe('20 Minute')
        ->and($delivery_option->schedule)->toBe('Sun-Mon');
});
it('return LmsMoodleDetailsData if delivery_method is LMS_MOODLE', function (): void {
    $caster                         = new App\Data\Casts\DeliveryOptionDetailCast();
    $properties['fulfillment_type'] = \App\Enums\Product\FulfillmentTypeEnum::ONLINE_SERVICE;
    $properties['delivery_method']  = \App\Enums\Product\DeliveryMethodEnum::LMS_MOODLE;
    $details                        = [
        'course_idnumber'       => 'course123',
        'activity_id'           => 1,
        'enrollment_start_date' => '2023-12-01 00:00:00',
        'enrollment_end_date'   => '2023-12-31 23:59:59',
    ];
    $delivery_option = $caster->cast($this->mockProperty, $details, $properties, $this->mockContext);
    expect($delivery_option)
        ->toBeInstanceOf(App\Data\Admin\ProductDeliveryOption\DetailsData\LmsMoodleDetailsData::class)
        ->and($delivery_option->course_idnumber)->toBe($details['course_idnumber'])
        ->and($delivery_option->activity_id)->toBe($details['activity_id'])
        ->and($delivery_option->enrollment_start_date->format('Y-m-d H:i:s'))
        ->toBe(verta($details['enrollment_start_date'])->format('Y-m-d H:i:s'))
        ->and($delivery_option->enrollment_end_date->format('Y-m-d H:i:s'))
        ->toBe(verta($details['enrollment_end_date'])->format('Y-m-d H:i:s'));
});

it('return LiveSessionBbbDetailsData if delivery_method is LIVE_SESSION_BBB', function (): void {
    $caster                         = new App\Data\Casts\DeliveryOptionDetailCast();
    $properties['fulfillment_type'] = \App\Enums\Product\FulfillmentTypeEnum::ONLINE_SERVICE;
    $properties['delivery_method']  = \App\Enums\Product\DeliveryMethodEnum::LIVE_SESSION_BBB;
    $details                        = [
        'moderator_password'                 => 'mod',
        'attendee_password'                  => 'att',
        'record_session'                     => true,
        'auto_start_recording'               => false,
        'allow_start_stop_recording'         => true,
        'webcams_only_for_moderator'         => false,
        'mute_on_start'                      => true,
        'allow_mods_to_unmute_users'         => false,
        'lock_settings_disable_cam'          => true,
        'lock_settings_disable_mic'          => false,
        'lock_settings_disable_private_chat' => true,
        'lock_settings_disable_public_chat'  => false,
        'lock_settings_disable_note'         => true,
        'lock_settings_locked_layout'        => false,
        'welcome_message'                    => 'Welcome to the session',
        'session_duration'                   => 180,
        'default_presentation_url'           => 'https://example.com/presentation',
        'admin_notes'                        => 'Admin Note',
    ];
    $delivery_option = $caster->cast($this->mockProperty, $details, $properties, $this->mockContext);
    expect($delivery_option)
        ->toBeInstanceOf(LiveSessionBbbDetailsData::class)
        ->and($delivery_option->toArray())->toBe($details);
});

it('return LiveSessionSkyroomDetailsData if delivery_method is LIVE_SESSION_SKYROOM', function (): void {
    $caster                         = new App\Data\Casts\DeliveryOptionDetailCast();
    $properties['fulfillment_type'] = \App\Enums\Product\FulfillmentTypeEnum::ONLINE_SERVICE;
    $properties['delivery_method']  = \App\Enums\Product\DeliveryMethodEnum::LIVE_SESSION_SKYROOM;
    $details                        = [
        'meeting_name_identifier'     => 'meeting123',
        'moderator_password_override' => 'mod123',
        'attendee_password'           => 'att123',
        'record_session'              => true,
        'auto_start_recording'        => false,
        'webcams_only_for_moderator'  => true,
        'mute_on_start'               => true,
        'welcome_message'             => 'Welcome to the Skyroom session',
        'planned_duration_minutes'    => 60,
        'default_presentation_url'    => 'https://example.com/skyroom-presentation',
        'admin_notes'                 => 'Admin notes for Skyroom session',
    ];
    $delivery_option = $caster->cast($this->mockProperty, $details, $properties, $this->mockContext);
    expect($delivery_option)
        ->toBeInstanceOf(LiveSessionSkyroomDetailsData::class)
        ->and($delivery_option->toArray())->toBe($details);
});

it('return VideoPlatformSpotplayerDetailsData if delivery_method is VIDEO_PLATFORM_SPOTPLAYER', function (): void {
    $caster                         = new App\Data\Casts\DeliveryOptionDetailCast();
    $properties['fulfillment_type'] = \App\Enums\Product\FulfillmentTypeEnum::OFFLINE_SERVICE;
    $properties['delivery_method']  = \App\Enums\Product\DeliveryMethodEnum::VIDEO_PLATFORM_SPOTPLAYER;
    $details                        = [
        'course_id' => 'course123',
    ];
    $delivery_option = $caster->cast($this->mockProperty, $details, $properties, $this->mockContext);
    expect($delivery_option)
        ->toBeInstanceOf(VideoPlatformSpotplayerDetailsData::class)
        ->and($delivery_option->toArray())->toBe($details);
});
