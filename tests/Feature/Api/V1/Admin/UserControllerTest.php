<?php

use App\Enums\CivilIdTypeEnum;
use App\Enums\PermissionEnum;
use App\Models\User;
use Illuminate\Testing\Fluent\AssertableJson;

uses(\Tests\AuthTestTrait::class);
describe('list filters', function () {
    it('should return by name', function () {
        $this->authorized_user([PermissionEnum::USER_VIEW_ANY]);

        User::factory(10)->create();
        $filteringUser = User::factory()
            ->create([
                'first_name' => 'John',
                'last_name'  => 'Doe',
            ])->fresh();
        $response = $this->getJson(route('api.v1.admin.user.index', ['filter' => ['name' => 'John Doe']]));

        $response->assertSuccessful();
        $response->assertJsonCount(1, 'data.data');
        $response->assertJsonFragment(['first_name' => $filteringUser->first_name]);
        $response->assertJsonFragment(['last_name' => $filteringUser->last_name]);
    });

    it('should return by email', function () {
        $this->authorized_user([PermissionEnum::USER_VIEW_ANY]);

        User::factory(10)->create();
        $filteringUser = User::factory()
            ->create([
                'email' => 'john@example.com',
            ])->fresh();
        $response = $this->getJson(route('api.v1.admin.user.index', ['filter' => ['email' => 'john@example.com']]));

        $response->assertSuccessful();
        $response->assertJsonCount(1, 'data.data');
        $response->assertJsonFragment(['email' => $filteringUser->email]);
    });

    it('should return by phone', function () {
        $this->authorized_user([PermissionEnum::USER_VIEW_ANY]);

        User::factory(10)->create();
        $filteringUser = User::factory()
            ->create([
                'phone' => '09999999999',
            ])->fresh();
        $response = $this->getJson(route('api.v1.admin.user.index', ['filter' => ['phone' => '09999999999']]));

        $response->assertSuccessful();
        $response->assertJsonCount(1, 'data.data');
        $response->assertJsonFragment(['phone' => $filteringUser->phone]);
    });

    it('should return by civil_id', function () {
        $this->authorized_user([PermissionEnum::USER_VIEW_ANY]);

        User::factory(10)->create();
        //using invalid civil id to prevent collision with factory geenrated models
        $filteringUser = User::factory()
            ->create([
                'civil_id' => 'XYZ',
            ])->fresh();
        $response = $this->getJson(route('api.v1.admin.user.index', ['filter' => ['civil_id' => 'XYZ']]));

        $response->assertSuccessful();
        $response->assertJsonCount(1, 'data.data');
        $response->assertJsonFragment(['civil_id' => $filteringUser->civil_id]);
    });

    it('should return by civil_id_type', function () {
        $this->authorized_user([PermissionEnum::USER_VIEW_ANY]);

        User::factory(12)
            ->withPassport()
            ->create();
        User::factory(8)
            ->withImmigrantCode()
            ->create();
        User::factory(6)
            ->withNationalCode()
            ->create();
        $response = $this->getJson(
            route('api.v1.admin.user.index',
                [
                    'filter' => ['civil_id_type' => CivilIdTypeEnum::NATIONAL_CODE->value]
                ]
            )
        );

        $response->assertSuccessful();
        $response->assertJsonCount(6, 'data.data');
        $response->assertJsonFragment([
            'civil_id_type' => [
                'label' => CivilIdTypeEnum::NATIONAL_CODE->translate(),
                'value' => CivilIdTypeEnum::NATIONAL_CODE->value
            ]
        ]);
        $response->assertJsonMissing([
            'civil_id_type' => [
                'label' => CivilIdTypeEnum::PASSPORT->translate(),
                'value' => CivilIdTypeEnum::PASSPORT->value
            ]
        ]);
        $response->assertJsonMissing([
            'civil_id_type' => [
                'label' => CivilIdTypeEnum::IMMIGRANT_CODE->translate(),
                'value' => CivilIdTypeEnum::IMMIGRANT_CODE->value
            ]
        ]);
    });

    it('should return by date of birth', function () {
        $this->authorized_user([PermissionEnum::USER_VIEW_ANY]);

        User::factory(10)->create();
        //using invalid civil id to prevent collision with factory geenrated models
        $filteringUser1 = User::factory()
            ->create([
                'date_of_birth' => '1991-01-01',
            ])->fresh();
        $filteringUser2 = User::factory()
            ->create([
                'date_of_birth' => '1992-01-01',
            ])->fresh();
        $response = $this->getJson(route('api.v1.admin.user.index',
            [
                'filter' =>
                    [
                        'date_of_birth_from' => '1369-10-11',
                        'date_of_birth_to' => '1370-10-11',
                    ]

            ]));

        $response->assertSuccessful();
        $response->assertJsonCount(2, 'data.data');
        $response->assertJsonFragment(['id' => $filteringUser1->id]);
        $response->assertJsonFragment(['id' => $filteringUser2->id]);
    });
});

describe('CRUD Autherized', function () {
    it('should return list of user', function () {
        $this->authorized_user([PermissionEnum::USER_VIEW_ANY]);
        $users = User::factory(10)->create()->fresh();
        $response = $this->getJson(route('api.v1.admin.user.index'));
        $actualDataItems = collect($response->json('data.data'));

        foreach ($users as $expectedUser) {
            $match = $actualDataItems->first(function ($actualItem) use ($expectedUser) {
                return $actualItem['civil_id'] === $expectedUser->civil_id;
            });

            expect($match)->not->toBeNull("Expected user with civil_id '{$expectedUser->civil_id}' not found or properties mismatch.");

            if ($match) {
                AssertableJson::fromArray($match)
                    ->where('id', $expectedUser->id)
                    ->where('phone', $expectedUser->phone)
                    ->where('first_name', $expectedUser->first_name)
                    ->where('last_name', $expectedUser->last_name)
                    ->where('email', $expectedUser->email)
                    ->where('phone2', $expectedUser->phone2)
                    ->where('civil_id', $expectedUser->civil_id)
                    ->where('civil_id_type', [
                        'label' => $expectedUser->civil_id_type->translate(),
                        'value' => $expectedUser->civil_id_type->value
                    ])
                    ->where('date_of_birth', $this->toJalalitString($expectedUser->date_of_birth, 'Y-m-d'))
                    ->where('father_name', $expectedUser->father_name)
                    ->where('gender', [
                        'label' => $expectedUser->gender->translate(),
                        'value' => $expectedUser->gender->value
                    ])
                    ->where('education_level', [
                        'label' => $expectedUser->education_level->translate(),
                        'value' => $expectedUser->education_level->value
                    ])
                    ->where('field_of_study', $expectedUser->field_of_study)
                    ->where('education_status', [
                        'label' => $expectedUser->education_status->translate(),
                        'value' => $expectedUser->education_status->value
                    ])
                    ->etc();
            }
        }
    });

    it('should create user', function () {
        $this->authorized_user([PermissionEnum::USER_CREATE]);
        $user = User::factory()->withPassport()->make();
        $userData = [
            ...$user->toArray(),
            'date_of_birth' => verta($user->date_of_birth)->format('Y-m-d')
        ];
        $response = $this->postJson(route('api.v1.admin.user.store'), $userData);
        $response->assertCreated();
        $response->assertJson(function (AssertableJson $json) use ($user) {
            $json->where('data.phone', $user->phone)
                ->where('data.first_name', $user->first_name)
                ->where('data.last_name', $user->last_name)
                ->where('data.email', $user->email)
                ->where('data.phone2', $user->phone2)
                ->where('data.civil_id', $user->civil_id)
                ->where('data.civil_id_type', [
                    'label' => $user->civil_id_type->translate(),
                    'value' => $user->civil_id_type->value
                ])
                ->where('data.date_of_birth', $this->toJalalitString($user->date_of_birth, 'Y-m-d'))
                ->where('data.father_name', $user->father_name)
                ->where('data.gender', [
                    'label' => $user->gender->translate(),
                    'value' => $user->gender->value
                ])
                ->where('data.education_level', [
                    'label' => $user->education_level->translate(),
                    'value' => $user->education_level->value
                ])
                ->where('data.field_of_study', $user->field_of_study)
                ->where('data.education_status', [
                    'label' => $user->education_status->translate(),
                    'value' => $user->education_status->value
                ])
                ->etc();
        });

        $this->assertDatabaseHas('users', [
            'first_name'       => $user->first_name,
            'last_name'        => $user->last_name,
            'email'            => $user->email,
            'phone'            => $user->phone,
            'date_of_birth'    => $user->date_of_birth,
            'civil_id'         => $user->civil_id,
            'civil_id_type'    => $user->civil_id_type->value,
            'phone2'           => $user->phone2,
            'father_name'      => $user->father_name,
            'gender'           => $user->gender->value,
            'education_level'  => $user->education_level->value,
            'field_of_study'   => $user->field_of_study,
            'education_status' => $user->education_status->value,
        ]);
    });

    it('should return user data', function () {
        $this->authorized_user([PermissionEnum::USER_VIEW]);
        $user = User::factory()->create()->fresh();

        $response = $this->getJson(route('api.v1.admin.user.show', $user->id));
        $response->assertSuccessful();
        $response->assertJson(function (AssertableJson $json) use ($user) {
            $json
                ->where('data.id', $user->id)
                ->where('data.phone', $user->phone)
                ->where('data.first_name', $user->first_name)
                ->where('data.last_name', $user->last_name)
                ->where('data.email', $user->email)
                ->where('data.phone2', $user->phone2)
                ->where('data.civil_id', $user->civil_id)
                ->where('data.civil_id_type', [
                    'label' => $user->civil_id_type->translate(),
                    'value' => $user->civil_id_type->value
                ])
                ->where('data.date_of_birth', $this->toJalalitString($user->date_of_birth, 'Y-m-d'))
                ->where('data.father_name', $user->father_name)
                ->where('data.gender', [
                    'label' => $user->gender->translate(),
                    'value' => $user->gender->value
                ])
                ->where('data.education_level', [
                    'label' => $user->education_level->translate(),
                    'value' => $user->education_level->value
                ])
                ->where('data.field_of_study', $user->field_of_study)
                ->where('data.education_status', [
                    'label' => $user->education_status->translate(),
                    'value' => $user->education_status->value
                ])
                ->etc();
        });
    });

    it('should update user', function () {
        $this->authorized_user([PermissionEnum::USER_UPDATE]);
        $user = User::factory()->withPassport()->create();
        $updateUserData = [
            ...$user->toArray(),
            'date_of_birth' => verta($user->date_of_birth)->format('Y-m-d'),
            'first_name'    => 'Updated name',
            'last_name'     => 'Updated last name',
        ];
        $user->refresh();
        $response = $this->putJson(route('api.v1.admin.user.update', $user->id), $updateUserData);

        $response->assertSuccessful();
        $response->assertJson(function (AssertableJson $json) use ($user, $updateUserData) {
            $json->where('data.id', $user->id)
                ->where('data.phone', $user->phone)
                ->where('data.first_name', $updateUserData['first_name'])
                ->where('data.last_name', $updateUserData['last_name'])
                ->where('data.email', $user->email)
                ->where('data.phone2', $user->phone2)
                ->where('data.civil_id', $user->civil_id)
                ->where('data.civil_id_type', [
                    'label' => $user->civil_id_type->translate(),
                    'value' => $user->civil_id_type->value
                ])
                ->where('data.date_of_birth', $this->toJalalitString($user->date_of_birth, 'Y-m-d'))
                ->where('data.father_name', $user->father_name)
                ->where('data.gender', [
                    'label' => $user->gender->translate(),
                    'value' => $user->gender->value
                ])
                ->where('data.education_level', [
                    'label' => $user->education_level->translate(),
                    'value' => $user->education_level->value
                ])
                ->where('data.field_of_study', $user->field_of_study)
                ->where('data.education_status', [
                    'label' => $user->education_status->translate(),
                    'value' => $user->education_status->value
                ])
                ->etc();
        });
        $this->assertDatabaseHas('users', [
            'id'         => $user->id,
            'first_name' => $updateUserData['first_name'],
            'last_name'  => $updateUserData['last_name'],
        ]);
        $this->assertDatabaseMissing('users', [
            'id'         => $user->id,
            'first_name' => $user->first_name,
            'last_name'  => $user->last_name,
        ]);
    });

    it('should delete user', function () {
        $this->authorized_user([PermissionEnum::USER_DELETE]);
        $user = User::factory()->create()->fresh();
        $response = $this->deleteJson(route('api.v1.admin.user.destroy', $user->id));
        $response->assertNoContent();

        $this->assertDatabaseMissing('users', [
            'id' => $user->id,
        ]);
    });

    it('should delete user if has related data (teacher', function () {
        $this->authorized_user([PermissionEnum::USER_DELETE]);
        $user = User::factory()->create()->fresh();
        $teacher = \App\Models\Teacher::factory()->create([
            'user_id' => $user->id,
        ]);
        $response = $this->deleteJson(route('api.v1.admin.user.destroy', $user->id));
        $response->assertUnprocessable();
        $response->assertJson(function (AssertableJson $json) use ($user, $teacher) {
            $json->where('message', __(
                'messages.errors.model_has_relationship_data',
                [
                    'model'         => __('messages.models.user'),
                    'related_model' => getModelLabel(\App\Models\Teacher::class),
                ]
            ))->etc();
        });
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
        ]);
    });
});
describe('CRUD Unautherized', function () {
    beforeEach(function () {
        $this->unauthorized_user();
    });
    it('should not return list of user', function () {
        $users = User::factory(10)->create()->fresh();
        $response = $this->getJson(route('api.v1.admin.user.index'));
        $response->assertForbidden();
    });

    it('should not create user', function () {
        $user = User::factory()->withPassport()->make([
            'date_of_birth' => '1360-01-01',
        ]);
        $response = $this->postJson(route('api.v1.admin.user.store'), $user->toArray());
        $response->assertForbidden();

        $this->assertDatabaseMissing('users', [
            'first_name'       => $user->first_name,
            'last_name'        => $user->last_name,
            'email'            => $user->email,
            'phone'            => $user->phone,
            'date_of_birth'    => $user->date_of_birth,
            'civil_id'         => $user->civil_id,
            'civil_id_type'    => $user->civil_id_type->value,
            'phone2'           => $user->phone2,
            'father_name'      => $user->father_name,
            'gender'           => $user->gender->value,
            'education_level'  => $user->education_level->value,
            'field_of_study'   => $user->field_of_study,
            'education_status' => $user->education_status->value,
        ]);
    });

    it('should not return user data', function () {
        $user = User::factory()->create()->fresh();

        $response = $this->getJson(route('api.v1.admin.user.show', $user->id));
        $response->assertForbidden();
    });

    it('should update user', function () {
        $user = User::factory()->withPassport()->create();
        $updateUserData = [
            ...$user->toArray(),
            'date_of_birth' => '1360-01-01',
            'first_name'    => 'Updated name',
            'last_name'     => 'Updated last name',
        ];
        $user->refresh();
        $response = $this->putJson(route('api.v1.admin.user.update', $user->id), $updateUserData);

        $response->assertForbidden();

        $this->assertDatabaseMissing('users', [
            'id'         => $user->id,
            'first_name' => $updateUserData['first_name'],
            'last_name'  => $updateUserData['last_name'],
        ]);
        $this->assertDatabaseHas('users', [
            'id'         => $user->id,
            'first_name' => $user->first_name,
            'last_name'  => $user->last_name,
        ]);
    });

    it('should delete user', function () {
        $user = User::factory()->create()->fresh();
        $response = $this->deleteJson(route('api.v1.admin.user.destroy', $user->id));
        $response->assertForbidden();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
        ]);
    });
});

describe('Validations', function () {
    it('fail civil id validations', function ($validation_type, $data) {
        $this->authorized_user([PermissionEnum::USER_CREATE]);
        if ($validation_type === 'duplicated') {
            $user = User::factory()->create(
                [
                    "civil_id"      => "42532564865",
                    "civil_id_type" => "passport",
                ]
            )->fresh();
        }
        $response = $this->postJson(route('api.v1.admin.user.store'), $data);
        $response->assertJsonValidationErrors(
            ['civil_id']
        );
    })->with([
        [
            'duplicated',
            [
                "civil_id"      => "42532564865",
                "civil_id_type" => "passport",
            ],
        ],
        [
            'national_code',
            [
                "civil_id"      => "5958136284",
                "civil_id_type" => "national_code",
            ]
        ],
        [
            'passport',
            [
                "civil_id"      => "425",
                "civil_id_type" => "passport",
            ],
        ],
        [
            'immigrant_code',
            [
                "civil_id"      => "161",
                "civil_id_type" => "immigrant_code",
            ]
        ],
    ]);
});
