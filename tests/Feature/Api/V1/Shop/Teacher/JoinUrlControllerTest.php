<?php

declare(strict_types=1);

use App\Actions\Shop\Teacher\GetTeacherJoinUrlAction;
use App\Contracts\Integrations\SkyroomClientContract;
use App\Enums\Product\DeliveryMethodEnum;
use App\Http\Controllers\Api\Shop\Teacher\TeacherJoinUrlController;
use App\Models\ProductDeliveryOption;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Support\Carbon;

uses(Tests\Support\Traits\AuthTestTrait::class);

covers(GetTeacherJoinUrlAction::class, TeacherJoinUrlController::class);

/*
|--------------------------------------------------------------------------
| Mutation notes (`pest --mutate --parallel`)
|--------------------------------------------------------------------------
| The four survivors on the action's class constants are coverage artifacts,
| not test gaps: PHP compiles `const` declarations instead of executing them,
| so no coverage driver can attribute a hit to those lines. Both constants are
| asserted through the mocked `createLoginUrl` call (access `2`, ttl `3600`).
|
| The two controller survivors are equivalent mutants: `$user?->teacherData`
| and `(bool) $teacher` only differ when the authenticated user is null, which
| the `auth.cookie:user` + `auth:user` middleware already rejects before the
| controller runs.
*/

beforeEach(function (): void {
    $this->customer();
});

it('returns 403 when the authenticated user has no teacher profile', function (): void {
    $deliveryOption = seminarOption(Teacher::factory()->create(), DeliveryMethodEnum::LIVE_SESSION_SKYROOM, [
        'room_id' => 456,
    ]);

    $this->mock(SkyroomClientContract::class, function ($mock): void {
        $mock->shouldNotReceive('createLoginUrl');
    });

    $this->getJson(route('api.v1.shop.teacher.courses.join', ['deliveryOption' => $deliveryOption->uuid]))
        ->assertForbidden();
});

it('returns 403 when the teacher is not assigned to the delivery option', function (): void {
    Teacher::factory()->create(['user_id' => $this->user->id]);

    $deliveryOption = seminarOption(Teacher::factory()->create(), DeliveryMethodEnum::LIVE_SESSION_SKYROOM, [
        'room_id' => 456,
    ]);

    $this->mock(SkyroomClientContract::class, function ($mock): void {
        $mock->shouldNotReceive('createLoginUrl');
    });

    $this->getJson(route('api.v1.shop.teacher.courses.join', ['deliveryOption' => $deliveryOption->uuid]))
        ->assertForbidden();
});

it('returns 422 for a non-seminar delivery option', function (): void {
    $teacher = Teacher::factory()->create(['user_id' => $this->user->id]);

    $deliveryOption = seminarOption($teacher, DeliveryMethodEnum::IN_PERSON);

    $this->getJson(route('api.v1.shop.teacher.courses.join', ['deliveryOption' => $deliveryOption->uuid]))
        ->assertUnprocessable()
        ->assertJsonFragment(['message' => __('messages.enrollments.not_seminar')]);
});

// Ticket #55 extends the endpoint to BBB/Niliroom seminars; until then BBB is not served.
it('returns 422 for a BBB seminar', function (): void {
    $teacher = Teacher::factory()->create(['user_id' => $this->user->id]);

    $deliveryOption = seminarOption($teacher, DeliveryMethodEnum::LIVE_SESSION_BBB);

    $this->getJson(route('api.v1.shop.teacher.courses.join', ['deliveryOption' => $deliveryOption->uuid]))
        ->assertUnprocessable()
        ->assertJsonFragment([
            'message' => __('messages.enrollment.delivery_no_join_url', [
                'method' => DeliveryMethodEnum::LIVE_SESSION_BBB->value,
            ]),
        ]);
});

it('returns 503 when the delivery option has no skyroom room id', function (): void {
    $teacher = Teacher::factory()->create(['user_id' => $this->user->id]);

    $deliveryOption = seminarOption($teacher, DeliveryMethodEnum::LIVE_SESSION_SKYROOM, ['room_id' => null]);

    $this->mock(SkyroomClientContract::class, function ($mock): void {
        $mock->shouldNotReceive('createLoginUrl');
    });

    $this->getJson(route('api.v1.shop.teacher.courses.join', ['deliveryOption' => $deliveryOption->uuid]))
        ->assertStatus(503)
        ->assertJsonFragment(['message' => __('messages.provisioning.skyroom_room_id_missing')]);
});

it('returns 503 when the skyroom room id is not a positive integer', function (string|int|null $roomId): void {
    $teacher = Teacher::factory()->create(['user_id' => $this->user->id]);

    $deliveryOption = seminarOption($teacher, DeliveryMethodEnum::LIVE_SESSION_SKYROOM, ['room_id' => $roomId]);

    $this->mock(SkyroomClientContract::class, function ($mock): void {
        $mock->shouldNotReceive('createLoginUrl');
    });

    $this->getJson(route('api.v1.shop.teacher.courses.join', ['deliveryOption' => $deliveryOption->uuid]))
        ->assertStatus(503)
        ->assertJsonFragment(['message' => __('messages.provisioning.skyroom_room_id_missing')]);
})->with([
    'non-numeric string' => 'not-a-room',
    'zero'               => 0,
    'negative'           => -5,
    'float string'       => '4.5',
]);

it('returns a presenter skyroom login url for the assigned teacher', function (): void {
    Carbon::setTestNow('2026-01-01 12:00:00');

    $this->customer(User::factory()->create([
        'first_name' => 'Sara',
        'last_name'  => 'Ahmadi',
    ]));

    $teacher = Teacher::factory()->create(['user_id' => $this->user->id]);

    // details_json is an untyped JSON column, so the stored room id may be a numeric string.
    $deliveryOption = seminarOption($teacher, DeliveryMethodEnum::LIVE_SESSION_SKYROOM, ['room_id' => '456']);

    $loginUrl = 'https://skyroom.example.com/login?room=456';

    $this->mock(SkyroomClientContract::class, function ($mock) use ($loginUrl): void {
        $mock->shouldReceive('createLoginUrl')
            ->once()
            ->with(Mockery::mustBe(456), 'user-'.$this->user->id, 'Sara Ahmadi', 2, 3600)
            ->andReturn($loginUrl);
    });

    $this->getJson(route('api.v1.shop.teacher.courses.join', ['deliveryOption' => $deliveryOption->uuid]))
        ->assertOk()
        ->assertJsonPath('data.url', $loginUrl)
        ->assertJsonPath('data.type', 'skyroom')
        ->assertJsonPath('data.expires_at', '1404-10-11 13:00:00');
});

it('falls back to a default nickname when the teacher account has no name', function (): void {
    $this->customer(User::factory()->create([
        'first_name' => null,
        'last_name'  => null,
    ]));

    $teacher = Teacher::factory()->create(['user_id' => $this->user->id]);

    $deliveryOption = seminarOption($teacher, DeliveryMethodEnum::LIVE_SESSION_SKYROOM, ['room_id' => 1]);

    $this->mock(SkyroomClientContract::class, function ($mock): void {
        $mock->shouldReceive('createLoginUrl')
            ->once()
            ->with(1, 'user-'.$this->user->id, 'استاد', 2, 3600)
            ->andReturn('https://skyroom.example.com/login?room=1');
    });

    $this->getJson(route('api.v1.shop.teacher.courses.join', ['deliveryOption' => $deliveryOption->uuid]))
        ->assertOk();
});

/**
 * @param  array<string, mixed>  $details
 */
function seminarOption(Teacher $teacher, DeliveryMethodEnum $deliveryMethod, array $details = []): ProductDeliveryOption
{
    $deliveryOption = ProductDeliveryOption::factory()->create([
        'delivery_method' => $deliveryMethod,
        'details_json'    => $details,
    ]);
    $deliveryOption->teachers()->attach($teacher);

    return $deliveryOption;
}
