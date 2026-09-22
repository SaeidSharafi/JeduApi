<?php

declare(strict_types=1);

use App\Actions\Shop\Teacher\GetTeacherJoinUrlAction;
use App\Contracts\Integrations\NiliroomClientContract;
use App\Contracts\Integrations\SkyroomClientContract;
use App\Enums\Product\DeliveryMethodEnum;
use App\Http\Controllers\Api\Shop\Teacher\TeacherJoinUrlController;
use App\Models\ProductDeliveryOption;
use App\Models\Teacher;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

uses(Tests\Support\Traits\AuthTestTrait::class);

covers(GetTeacherJoinUrlAction::class, TeacherJoinUrlController::class);

beforeEach(function (): void {
    $this->customer();

    // The test environment runs with APP_DEBUG on, so "debug off" has to be explicit.
    config(['app.debug' => false]);
});

it('returns 403 when the authenticated user has no teacher profile', function (): void {
    $deliveryOption = seminarOption(Teacher::factory()->create(), DeliveryMethodEnum::LIVE_SESSION_SKYROOM, [
        'room_id' => 456,
    ]);

    $this->mock(SkyroomClientContract::class, function ($mock): void {
        $mock->shouldNotReceive('createLoginUrl');
    });

    $this->getJson(route('api.v1.shop.teacher.seminars.join', ['deliveryOption' => $deliveryOption->uuid]))
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

    $this->getJson(route('api.v1.shop.teacher.seminars.join', ['deliveryOption' => $deliveryOption->uuid]))
        ->assertForbidden();
});

it('returns 422 for a non-seminar delivery option', function (): void {
    $teacher = Teacher::factory()->create(['user_id' => $this->user->id]);

    $deliveryOption = seminarOption($teacher, DeliveryMethodEnum::IN_PERSON);

    $this->getJson(route('api.v1.shop.teacher.seminars.join', ['deliveryOption' => $deliveryOption->uuid]))
        ->assertUnprocessable()
        ->assertJsonFragment(['message' => __('messages.enrollments.not_seminar')]);
});

it('returns the niliroom login grant for the assigned teacher', function (): void {
    $teacher = Teacher::factory()->create(['user_id' => $this->user->id]);

    $deliveryOption = seminarOption($teacher, DeliveryMethodEnum::LIVE_SESSION_NILIROOM, [
        'nili_room_id' => 'room-public-456',
    ]);

    $grantUrl  = 'https://niliroom.example.ir/login/abc123';
    $expiresAt = CarbonImmutable::parse('2026-01-01 12:05:00');

    $this->mock(NiliroomClientContract::class, function ($mock) use ($grantUrl, $expiresAt): void {
        $mock->shouldReceive('isReady')->once()->andReturnTrue();
        $mock->shouldReceive('issueTeacherLoginGrant')
            ->once()
            ->with(Mockery::on(fn (User $user): bool => $user->id === $this->user->id), 'room-public-456')
            ->andReturn(['url' => $grantUrl, 'expires_at' => $expiresAt]);
    });

    $this->getJson(route('api.v1.shop.teacher.seminars.join', ['deliveryOption' => $deliveryOption->uuid]))
        ->assertOk()
        ->assertJsonPath('data.url', $grantUrl)
        ->assertJsonPath('data.type', 'niliroom')
        // The provider's own expiry, not a locally computed TTL.
        ->assertJsonPath('data.expires_at', '1404-10-11 12:05:00');
});

it('returns 503 when the niliroom panel is not ready', function (): void {
    $teacher = Teacher::factory()->create(['user_id' => $this->user->id]);

    $deliveryOption = seminarOption($teacher, DeliveryMethodEnum::LIVE_SESSION_NILIROOM, [
        'nili_room_id' => 'room-public-456',
    ]);

    $this->mock(NiliroomClientContract::class, function ($mock): void {
        $mock->shouldReceive('isReady')->once()->andReturnFalse();
        $mock->shouldNotReceive('issueTeacherLoginGrant');
    });

    $this->getJson(route('api.v1.shop.teacher.seminars.join', ['deliveryOption' => $deliveryOption->uuid]))
        ->assertStatus(503)
        ->assertJsonFragment(['message' => __('messages.enrollments.niliroom_not_configured')]);
});

it('returns 503 when the niliroom room id is missing or malformed', function (mixed $roomId): void {
    $teacher = Teacher::factory()->create(['user_id' => $this->user->id]);

    $deliveryOption = seminarOption($teacher, DeliveryMethodEnum::LIVE_SESSION_NILIROOM, ['nili_room_id' => $roomId]);

    $this->mock(NiliroomClientContract::class, function ($mock): void {
        $mock->shouldReceive('isReady')->andReturnTrue();
        $mock->shouldNotReceive('issueTeacherLoginGrant');
    });

    $this->getJson(route('api.v1.shop.teacher.seminars.join', ['deliveryOption' => $deliveryOption->uuid]))
        ->assertStatus(503)
        ->assertJsonFragment(['message' => __('messages.provisioning.niliroom_room_id_missing')])
        ->assertJsonPath('errors', []);
})->with([
    'absent'          => null,
    'empty string'    => '',
    'whitespace only' => '   ',
    'integer'         => 456,
    'array'           => [['room']],
]);

it('includes scrubbed upstream context on provisioning failures only when debug is on', function (): void {
    $teacher = Teacher::factory()->create(['user_id' => $this->user->id]);

    $deliveryOption = seminarOption($teacher, DeliveryMethodEnum::LIVE_SESSION_NILIROOM, ['nili_room_id' => 'room-public-456']);

    $this->mock(NiliroomClientContract::class, function ($mock): void {
        $mock->shouldReceive('isReady')->once()->andReturnFalse();
        $mock->shouldNotReceive('issueTeacherLoginGrant');
    });

    config(['app.debug' => true]);

    $this->getJson(route('api.v1.shop.teacher.seminars.join', ['deliveryOption' => $deliveryOption->uuid]))
        ->assertStatus(503)
        ->assertJsonPath('errors.debug', []);
});

it('returns 503 when the delivery option has no skyroom room id', function (): void {
    $teacher = Teacher::factory()->create(['user_id' => $this->user->id]);

    $deliveryOption = seminarOption($teacher, DeliveryMethodEnum::LIVE_SESSION_SKYROOM, ['room_id' => null]);

    $this->mock(SkyroomClientContract::class, function ($mock): void {
        $mock->shouldNotReceive('createLoginUrl');
    });

    $this->getJson(route('api.v1.shop.teacher.seminars.join', ['deliveryOption' => $deliveryOption->uuid]))
        ->assertStatus(503)
        ->assertJsonFragment(['message' => __('messages.provisioning.skyroom_room_id_missing')]);
});

it('returns 503 when the skyroom room id is not a positive integer', function (string|int|null $roomId): void {
    $teacher = Teacher::factory()->create(['user_id' => $this->user->id]);

    $deliveryOption = seminarOption($teacher, DeliveryMethodEnum::LIVE_SESSION_SKYROOM, ['room_id' => $roomId]);

    $this->mock(SkyroomClientContract::class, function ($mock): void {
        $mock->shouldNotReceive('createLoginUrl');
    });

    $this->getJson(route('api.v1.shop.teacher.seminars.join', ['deliveryOption' => $deliveryOption->uuid]))
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

    $this->getJson(route('api.v1.shop.teacher.seminars.join', ['deliveryOption' => $deliveryOption->uuid]))
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

    $this->getJson(route('api.v1.shop.teacher.seminars.join', ['deliveryOption' => $deliveryOption->uuid]))
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
