<?php

declare(strict_types=1);

it('ignore checks if civil_id_type is empty', function (): void {
    $rule      = new App\Rules\CivilIdRule();
    $validator = Validator::make(
        [
            'civil_id_type' => null,
            'civil_id'      => 1,
        ],
        [
            'civil_id' => [$rule],
        ]
    );
    expect($validator->passes())->toBeTrue();
});

it('ignore checks if civil_id_type is invalid', function (): void {
    $rule      = new App\Rules\CivilIdRule();
    $validator = Validator::make(
        [
            'civil_id_type' => 'invalid_type',
            'civil_id'      => '1',
        ],
        [
            'civil_id' => [$rule],
        ]
    );
    expect($validator->passes())->toBeTrue();
});
it('ignore checks if civil_id is empty', function (): void {
    $rule      = new App\Rules\CivilIdRule();
    $validator = Validator::make(
        [
            'civil_id_type' => \App\Enums\User\CivilIdTypeEnum::PASSPORT->value,
            'civil_id'      => null,
        ],
        [
            'civil_id' => [$rule],
        ]
    );
    expect($validator->passes())->toBeTrue();
});

it('ignore algorith validtion for national code if the config is false', function (): void {
    config(['app.ignore_national_code_validation' => true]);
    $rule      = new App\Rules\CivilIdRule();
    $validator = Validator::make(
        [
            'civil_id_type' => \App\Enums\User\CivilIdTypeEnum::NATIONAL_CODE->value,
            'civil_id'      => '1111111111',
        ],
        [
            'civil_id' => [$rule],
        ]
    );
    expect($validator->passes())->toBeTrue();
});

it('faill the validtion for invalid civil id', function ($type, $national_code): void {
    $rule      = new App\Rules\CivilIdRule();
    $validator = Validator::make(
        [
            'civil_id_type' => \App\Enums\User\CivilIdTypeEnum::NATIONAL_CODE->value,
            'civil_id'      => $national_code,
        ],
        [
            'civil_id' => [$rule],
        ]
    );
    expect($validator->fails())->toBeTrue();
})->with([
    [\App\Enums\User\CivilIdTypeEnum::NATIONAL_CODE->value, '123'],
    [\App\Enums\User\CivilIdTypeEnum::NATIONAL_CODE->value, '1234567890'],
    [\App\Enums\User\CivilIdTypeEnum::NATIONAL_CODE->value, '1111111111'],
    [\App\Enums\User\CivilIdTypeEnum::NATIONAL_CODE->value, '2222222222'],
    [\App\Enums\User\CivilIdTypeEnum::NATIONAL_CODE->value, '3333333333'],
    [\App\Enums\User\CivilIdTypeEnum::PASSPORT->value, 'Xc2'],
    [\App\Enums\User\CivilIdTypeEnum::PASSPORT->value, '333333333333333333333'],
    [\App\Enums\User\CivilIdTypeEnum::PASSPORT->value, '333-333-33333'],
    [\App\Enums\User\CivilIdTypeEnum::PASSPORT->value, '3333333333'],
    [\App\Enums\User\CivilIdTypeEnum::IMMIGRANT_CODE->value, '333'],
    [\App\Enums\User\CivilIdTypeEnum::IMMIGRANT_CODE->value, 'XXXXXXXX'],
    [\App\Enums\User\CivilIdTypeEnum::IMMIGRANT_CODE->value, '12345678910113'],
]);
it('passes the validtion for valid national_code', function (): void {
    $rule      = new App\Rules\CivilIdRule();
    $validator = Validator::make(
        [
            'civil_id_type' => \App\Enums\User\CivilIdTypeEnum::NATIONAL_CODE->value,
            'civil_id'      => '4380194108',
        ],
        [
            'civil_id' => [$rule],
        ]
    );
    expect($validator->passes())->toBeTrue();
});
