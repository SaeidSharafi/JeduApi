<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Routing\Route;

it('ignore checks if civil_id_type is empty', function (): void {
    $rule      = new App\Rules\UniqueCivilIdRule();
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
    $rule      = new App\Rules\UniqueCivilIdRule();
    $validator = Validator::make(
        [
            'civil_id_type' => 'invalid_type',
            'civil_id'      => 1,
        ],
        [
            'civil_id' => [$rule],
        ]
    );
    expect($validator->passes())->toBeTrue();
});
it('ignore checks if civil_id is empty', function (): void {
    $rule      = new App\Rules\UniqueCivilIdRule();
    $validator = Validator::make(
        [
            'civil_id_type' => App\Enums\User\CivilIdTypeEnum::PASSPORT->value,
            'civil_id'      => null,
        ],
        [
            'civil_id' => [$rule],
        ]
    );
    expect($validator->passes())->toBeTrue();
});

it('use the given UserId in contructor', function (): void {
    $user      = App\Models\User::factory()->create()->fresh();
    $rule      = new App\Rules\UniqueCivilIdRule($user->id);
    $validator = Validator::make(
        [
            'civil_id_type' => App\Enums\User\CivilIdTypeEnum::PASSPORT->value,
            'civil_id'      => 'X123456789',
        ],
        [
            'civil_id' => [$rule],
        ]
    );
    expect($validator->passes())->toBeTrue();
});

it('faill the validtion if the civil id exist', function (): void {
    $user = App\Models\User::factory()->create([
        'civil_id_type' => App\Enums\User\CivilIdTypeEnum::PASSPORT->value,
        'civil_id'      => 'X123456789',
    ])->fresh();
    $rule      = new App\Rules\UniqueCivilIdRule();
    $validator = Validator::make(
        [
            'civil_id_type' => App\Enums\User\CivilIdTypeEnum::PASSPORT->value,
            'civil_id'      => 'X123456789',
        ],
        [
            'civil_id' => [$rule],
        ]
    );
    expect($validator->fails())->toBeTrue();
});

it('ignores the route user when binding has not resolved the numeric id yet', function (): void {
    $user = App\Models\User::factory()->create([
        'civil_id_type' => App\Enums\User\CivilIdTypeEnum::PASSPORT->value,
        'civil_id'      => 'X123456789',
    ]);

    $request = Request::create("/users/{$user->id}", 'PUT');
    $route   = new Route(['PUT'], '/users/{user}', fn (): null => null);
    $route->bind($request);
    $request->setRouteResolver(fn (): Route => $route);
    app()->instance('request', $request);

    $validator = Validator::make(
        [
            'civil_id_type' => App\Enums\User\CivilIdTypeEnum::PASSPORT->value,
            'civil_id'      => 'X123456789',
        ],
        [
            'civil_id' => [new App\Rules\UniqueCivilIdRule()],
        ],
    );

    expect($validator->passes())->toBeTrue();
});
