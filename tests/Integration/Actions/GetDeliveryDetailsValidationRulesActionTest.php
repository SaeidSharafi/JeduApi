<?php

declare(strict_types=1);

use App\Actions\Admin\ProductDeliveryOption\GetDeliveryDetailsValidationRulesAction;
use App\Enums\Product\DeliveryMethodEnum;
use App\Enums\Product\FulfillmentTypeEnum;

it('it return empty array for invalid types', function (): void {
    $fulfillmentType = FulfillmentTypeEnum::DIGITAL->value;
    $deliveryMethod  = DeliveryMethodEnum::DIRECT_DOWNLOAD->value;
    $detailsData     = [
        'max_downloads'   => 10,
        'expiration_date' => '2023-12-31 23:59:59',
    ];
    $expectedRules = App\Data\Admin\ProductDeliveryOption\DetailsData\DirectDownloadDetailsData::getValidationRules($detailsData);
    $action        = new App\Actions\Admin\ProductDeliveryOption\GetDeliveryDetailsValidationRulesAction();
    $rules         = $action->handle(null, $deliveryMethod, $detailsData);
    expect($rules)->toBeArray()->toBeEmpty();

    $rules = $action->handle($fulfillmentType, null, $detailsData);
    expect($rules)->toBeArray()->toBeEmpty();

    $rules = $action->handle(null, null, $detailsData);
    expect($rules)->toBeArray()->toBeEmpty();
    $rules = $action->handle($fulfillmentType, 'invalid_delivery_method', $detailsData);
    expect($rules)->toBeArray()->toBeEmpty();
});

// Dataset for valid fulfillmentType/deliveryMethod pairs
$fulfillmentDeliveryPairs = [
    // DIGITAL
    [FulfillmentTypeEnum::DIGITAL->value, DeliveryMethodEnum::DIRECT_DOWNLOAD->value],
    // ONLINE_SERVICE
    [FulfillmentTypeEnum::ONLINE_SERVICE->value, DeliveryMethodEnum::LIVE_SESSION_BBB->value],
    [FulfillmentTypeEnum::ONLINE_SERVICE->value, DeliveryMethodEnum::LIVE_SESSION_SKYROOM->value],
    [FulfillmentTypeEnum::ONLINE_SERVICE->value, DeliveryMethodEnum::LMS_MOODLE->value],
    // OFFLINE_SERVICE
    [FulfillmentTypeEnum::OFFLINE_SERVICE->value, DeliveryMethodEnum::VIDEO_PLATFORM_SPOTPLAYER->value],
    // IN_PERSON_SERVICE
    [FulfillmentTypeEnum::IN_PERSON_SERVICE->value, DeliveryMethodEnum::IN_PERSON->value],
];

it('creates delivery validation rules for each valid fulfillmentType/deliveryMethod pair with empty details', function ($fulfillmentType, $deliveryMethod): void {
    $detailsData = [];
    $action      = new GetDeliveryDetailsValidationRulesAction();
    $rules       = $action->handle($fulfillmentType, $deliveryMethod, $detailsData);
    expect($rules)->toBeArray();
})->with($fulfillmentDeliveryPairs);

// Dataset for valid fulfillmentType/deliveryMethod pairs with sample details
$fulfillmentDeliveryPairsWithDetails = [
    // DIGITAL - DIRECT_DOWNLOAD
    [
        FulfillmentTypeEnum::DIGITAL->value,
        DeliveryMethodEnum::DIRECT_DOWNLOAD->value,
        [
            'max_downloads'   => 10,
            'expiration_date' => '2023-12-31 23:59:59',
        ],
    ],
    // ONLINE_SERVICE - LIVE_SESSION_BBB
    [
        FulfillmentTypeEnum::ONLINE_SERVICE->value,
        DeliveryMethodEnum::LIVE_SESSION_BBB->value,
        [
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
        ],
    ],
    // ONLINE_SERVICE - LIVE_SESSION_SKYROOM
    [
        FulfillmentTypeEnum::ONLINE_SERVICE->value,
        DeliveryMethodEnum::LIVE_SESSION_SKYROOM->value,
        [
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
        ],
    ],
    // ONLINE_SERVICE - LMS_MOODLE
    [
        FulfillmentTypeEnum::ONLINE_SERVICE->value,
        DeliveryMethodEnum::LMS_MOODLE->value,
        [
            'course_idnumber'       => 'course123',
            'activity_id'           => 1,
            'enrollment_start_date' => '2023-12-01 00:00:00',
            'enrollment_end_date'   => '2023-12-31 23:59:59',
        ],
    ],
    // OFFLINE_SERVICE - VIDEO_PLATFORM_SPOTPLAYER
    [
        FulfillmentTypeEnum::OFFLINE_SERVICE->value,
        DeliveryMethodEnum::VIDEO_PLATFORM_SPOTPLAYER->value,
        [
            'course_id' => 'course123',
        ],
    ],
    // IN_PERSON_SERVICE - IN_PERSON
    [
        FulfillmentTypeEnum::IN_PERSON_SERVICE->value,
        DeliveryMethodEnum::IN_PERSON->value,
        [
            'location'        => 'Test Location',
            'duration'        => '20 Minute',
            'schedule'        => 'Sun-Mon',
            'additional_info' => null,
        ],
    ],
];

it('creates delivery validation rules for each valid fulfillmentType/deliveryMethod pair with sample details', function ($fulfillmentType, $deliveryMethod, $detailsData): void {
    $action = new App\Actions\Admin\ProductDeliveryOption\GetDeliveryDetailsValidationRulesAction();
    $rules  = $action->handle($fulfillmentType, $deliveryMethod, $detailsData);
    expect($rules)->toBeArray();

})->with($fulfillmentDeliveryPairsWithDetails);

it('creates delivery validation rules for DIRECT_DOWNLOAD', function (): void {
    $fulfillmentType = FulfillmentTypeEnum::DIGITAL->value;
    $deliveryMethod  = DeliveryMethodEnum::DIRECT_DOWNLOAD->value;
    $detailsData     = [
        'max_downloads'   => 10,
        'expiration_date' => '2023-12-31 23:59:59',
    ];
    $expectedRules = App\Data\Admin\ProductDeliveryOption\DetailsData\DirectDownloadDetailsData::getValidationRules($detailsData);
    $action        = new App\Actions\Admin\ProductDeliveryOption\GetDeliveryDetailsValidationRulesAction();
    $rules         = $action->handle($fulfillmentType, $deliveryMethod, $detailsData);
    foreach ($expectedRules as $key => $expected) {
        expect($rules)->toHaveKey('details.'.$key);
        expect($rules['details.'.$key])->toBe($expected);
    }
    expect($rules)->toHaveKey('details');
    expect($rules['details'][0])->toBe('required');
    expect($rules['details'][1])->toStartWith('array:');
});

it('creates delivery validation rules for IN_PERSON', function (): void {
    $fulfillmentType = FulfillmentTypeEnum::IN_PERSON_SERVICE->value;
    $deliveryMethod  = DeliveryMethodEnum::IN_PERSON->value;
    $detailsData     = [
        'location'        => 'Test Location',
        'duration'        => '20 Minute',
        'schedule'        => 'Sun-Mon',
        'additional_info' => null,
    ];
    $expectedRules = App\Data\Admin\ProductDeliveryOption\DetailsData\InPersonDetailsData::getValidationRules($detailsData);
    $action        = new App\Actions\Admin\ProductDeliveryOption\GetDeliveryDetailsValidationRulesAction();
    $rules         = $action->handle($fulfillmentType, $deliveryMethod, $detailsData);
    foreach ($expectedRules as $key => $expected) {
        expect($rules)->toHaveKey('details.'.$key);
        expect($rules['details.'.$key])->toBe($expected);
    }
    expect($rules)->toHaveKey('details');
    expect($rules['details'][0])->toBe('required');
    expect($rules['details'][1])->toStartWith('array:');
});

it('creates delivery validation rules for LMS_MOODLE', function (): void {
    $fulfillmentType = FulfillmentTypeEnum::ONLINE_SERVICE->value;
    $deliveryMethod  = DeliveryMethodEnum::LMS_MOODLE->value;
    $detailsData     = [
        'course_idnumber'       => 'course123',
        'activity_id'           => 1,
        'enrollment_start_date' => '2023-12-01 00:00:00',
        'enrollment_end_date'   => '2023-12-31 23:59:59',
    ];
    $expectedRules = App\Data\Admin\ProductDeliveryOption\DetailsData\LmsMoodleDetailsData::getValidationRules($detailsData);
    $action        = new App\Actions\Admin\ProductDeliveryOption\GetDeliveryDetailsValidationRulesAction();
    $rules         = $action->handle($fulfillmentType, $deliveryMethod, $detailsData);
    foreach ($expectedRules as $key => $expected) {
        expect($rules)->toHaveKey('details.'.$key);
        expect($rules['details.'.$key])->toBe($expected);
    }
    expect($rules)->toHaveKey('details');
    expect($rules['details'][0])->toBe('required');
    expect($rules['details'][1])->toStartWith('array:');
});

it('creates delivery validation rules for LIVE_SESSION_BBB', function (): void {
    $fulfillmentType = FulfillmentTypeEnum::ONLINE_SERVICE->value;
    $deliveryMethod  = DeliveryMethodEnum::LIVE_SESSION_BBB->value;
    $detailsData     = [
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
    $expectedRules = App\Data\Admin\ProductDeliveryOption\DetailsData\LiveSessionBbbDetailsData::getValidationRules($detailsData);
    $action        = new App\Actions\Admin\ProductDeliveryOption\GetDeliveryDetailsValidationRulesAction();
    $rules         = $action->handle($fulfillmentType, $deliveryMethod, $detailsData);
    foreach ($expectedRules as $key => $expected) {
        expect($rules)->toHaveKey('details.'.$key);
        expect($rules['details.'.$key])->toBe($expected);
    }
    expect($rules)->toHaveKey('details');
    expect($rules['details'][0])->toBe('required');
    expect($rules['details'][1])->toStartWith('array:');
});

it('creates delivery validation rules for LIVE_SESSION_SKYROOM', function (): void {
    $fulfillmentType = FulfillmentTypeEnum::ONLINE_SERVICE->value;
    $deliveryMethod  = DeliveryMethodEnum::LIVE_SESSION_SKYROOM->value;
    $detailsData     = [
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
    $expectedRules = App\Data\Admin\ProductDeliveryOption\DetailsData\LiveSessionSkyroomDetailsData::getValidationRules($detailsData);
    $action        = new App\Actions\Admin\ProductDeliveryOption\GetDeliveryDetailsValidationRulesAction();
    $rules         = $action->handle($fulfillmentType, $deliveryMethod, $detailsData);
    foreach ($expectedRules as $key => $expected) {
        expect($rules)->toHaveKey('details.'.$key);
        expect($rules['details.'.$key])->toBe($expected);
    }
    expect($rules)->toHaveKey('details');
    expect($rules['details'][0])->toBe('required');
    expect($rules['details'][1])->toStartWith('array:');
});

it('creates delivery validation rules for VIDEO_PLATFORM_SPOTPLAYER', function (): void {
    $fulfillmentType = FulfillmentTypeEnum::OFFLINE_SERVICE->value;
    $deliveryMethod  = DeliveryMethodEnum::VIDEO_PLATFORM_SPOTPLAYER->value;
    $detailsData     = [
        'course_id' => 'course123',
    ];
    $expectedRules = App\Data\Admin\ProductDeliveryOption\DetailsData\VideoPlatformSpotplayerDetailsData::getValidationRules($detailsData);
    $action        = new App\Actions\Admin\ProductDeliveryOption\GetDeliveryDetailsValidationRulesAction();
    $rules         = $action->handle($fulfillmentType, $deliveryMethod, $detailsData);

    foreach ($expectedRules as $key => $expected) {
        expect($rules)->toHaveKey('details.'.$key);
        expect($rules['details.'.$key])->toBe($expected);
    }
    expect($rules)->toHaveKey('details');
    expect($rules['details'][0])->toBe('required');
    expect($rules['details'][1])->toStartWith('array:');
});
