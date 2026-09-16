<?php

declare(strict_types=1);

use App\Actions\Shop\Student\GetJoinUrlAction;
use App\Contracts\Integrations\NiliroomClientContract;
use App\Contracts\Integrations\SkyroomClientContract;
use App\Enums\Product\DeliveryMethodEnum;
use App\Http\Controllers\Api\Shop\Student\JoinUrlController;
use App\Models\Enrollment;
use App\Models\ProductDeliveryOption;
use App\Models\User;
use Illuminate\Support\Facades\Exceptions;

uses(Tests\Support\Traits\AuthTestTrait::class);

covers(GetJoinUrlAction::class, JoinUrlController::class);

beforeEach(function (): void {
    $this->customer();
});

// ─── Ownership ──────────────────────────────────────────────────────────────

it('returns 404 when enrollment belongs to another user', function (): void {
    $otherUser  = User::factory()->create();
    $enrollment = createEnrollment($otherUser, DeliveryMethodEnum::LIVE_SESSION_NILIROOM);

    $this->getJson(route('api.v1.shop.student.courses.join', ['enrollment' => $enrollment->uuid]))
        ->assertNotFound();
});

// ─── Niliroom (live_session_niliroom) ────────────────────────────────────────────

it('returns the niliroom meeting join url for an enrolled student', function (): void {
    $enrollment = seminarEnrollment($this->user, ['nili_room_id' => 'room-public-1']);

    $joinUrl = 'https://niliroom.example.ir/meetings/join/abc123';

    $this->mock(NiliroomClientContract::class, function ($mock) use ($joinUrl): void {
        $mock->shouldReceive('isReady')->once()->andReturnTrue();
        $mock->shouldReceive('issueStudentMeetingJoinGrant')
            ->once()
            ->with(
                Mockery::on(fn (User $user): bool => $user->id === $this->user->id),
                'room-public-1',
            )
            ->andReturn($joinUrl);
    });

    $this->getJson(route('api.v1.shop.student.courses.join', ['enrollment' => $enrollment->uuid]))
        ->assertOk()
        ->assertJsonPath('data.url', $joinUrl)
        ->assertJsonPath('data.type', 'niliroom')
        // The meeting join grant is not time-limited by the panel, unlike the teacher login grant.
        ->assertJsonPath('data.expires_at', null);
});

it('returns 503 when the niliroom panel is not ready', function (): void {
    $enrollment = seminarEnrollment($this->user, ['nili_room_id' => 'room-public-1']);

    $this->mock(NiliroomClientContract::class, function ($mock): void {
        $mock->shouldReceive('isReady')->once()->andReturnFalse();
        $mock->shouldNotReceive('issueStudentMeetingJoinGrant');
    });

    $this->getJson(route('api.v1.shop.student.courses.join', ['enrollment' => $enrollment->uuid]))
        ->assertStatus(503)
        ->assertJsonFragment(['message' => __('messages.enrollments.niliroom_not_configured')]);
});

it('returns 503 when the niliroom room id is missing or malformed', function (mixed $roomId): void {
    $enrollment = seminarEnrollment($this->user, ['nili_room_id' => $roomId]);

    $this->mock(NiliroomClientContract::class, function ($mock): void {
        $mock->shouldNotReceive('issueStudentMeetingJoinGrant');
    });

    $this->getJson(route('api.v1.shop.student.courses.join', ['enrollment' => $enrollment->uuid]))
        ->assertStatus(503)
        ->assertJsonFragment(['message' => __('messages.provisioning.niliroom_room_id_missing')]);
})->with([
    'absent'          => null,
    'empty string'    => '',
    'whitespace only' => '   ',
    'integer'         => 456,
    'array'           => [['room']],
]);

// ─── Skyroom ─────────────────────────────────────────────────────────────────

it('returns join url for Skyroom live session', function (): void {
    $enrollment                    = createEnrollment($this->user, DeliveryMethodEnum::LIVE_SESSION_SKYROOM);
    $enrollment->provisioning_data = [
        'providers' => [
            'skyroom' => [
                // provisioning_data is an untyped JSON column, so the room id may be a numeric string.
                'status' => 'success', 'data' => ['room_id' => '456'],
            ],
        ],
    ];
    $enrollment->save();

    $joinUrl = 'https://skyroom.example.com/login?room=456';

    $this->mock(SkyroomClientContract::class, function ($mock) use ($joinUrl): void {
        $mock->shouldReceive('createLoginUrl')
            ->once()
            // The nickname is not asserted: the student path reads `customer->full_name`, which
            // `User` has no attribute for, so it is always the default.
            ->with(Mockery::mustBe(456), 'user-'.$this->user->id, Mockery::any())
            ->andReturn($joinUrl);
    });

    $this->getJson(route('api.v1.shop.student.courses.join', ['enrollment' => $enrollment->uuid]))
        ->assertOk()
        ->assertJsonPath('data.url', $joinUrl)
        ->assertJsonPath('data.type', 'skyroom');
});

it('returns 503 when Skyroom room is not provisioned yet', function (): void {
    $enrollment                    = createEnrollment($this->user, DeliveryMethodEnum::LIVE_SESSION_SKYROOM);
    $enrollment->provisioning_data = [
        'providers' => [
            'skyroom' => [
                'status' => 'provisioning',
                'data'   => [],
            ],
        ],
    ];
    $enrollment->save();

    $this->getJson(route('api.v1.shop.student.courses.join', ['enrollment' => $enrollment->uuid]))
        ->assertStatus(503)
        ->assertJsonFragment(['message' => 'Skyroom room not provisioned yet.']);
});

// ─── Invalid delivery method ────────────────────────────────────────────────

it('returns 422 when delivery method does not support join URLs', function (): void {
    $enrollment = createEnrollment($this->user, DeliveryMethodEnum::IN_PERSON);

    $this->getJson(route('api.v1.shop.student.courses.join', ['enrollment' => $enrollment->uuid]))
        ->assertUnprocessable()
        ->assertJsonFragment([
            'message' => __('messages.enrollment.delivery_no_join_url', [
                'method' => DeliveryMethodEnum::IN_PERSON->value,
            ]),
        ]);
});

// ─── Throwable catch ────────────────────────────────────────────────────────

it('returns 500 when an unexpected error occurs', function (): void {
    Exceptions::fake();

    $enrollment = seminarEnrollment($this->user, ['nili_room_id' => 'room-public-1']);

    $this->mock(NiliroomClientContract::class, function ($mock): void {
        $mock->shouldReceive('isReady')->andReturnTrue();
        $mock->shouldReceive('issueStudentMeetingJoinGrant')
            ->once()
            ->andThrow(new RuntimeException('NiliroomService crashed'));
    });

    $this->getJson(route('api.v1.shop.student.courses.join', ['enrollment' => $enrollment->uuid]))
        ->assertServerError();

    // An unexpected provider failure must reach the logs, not just a 500 to the client.
    Exceptions::assertReported(fn (RuntimeException $e): bool => $e->getMessage() === 'NiliroomService crashed');
});

/**
 * A `live_session_niliroom` enrollment whose delivery option carries the staff-entered Niliroom room.
 *
 * @param  array<string, mixed>  $details
 */
function seminarEnrollment(User $customer, array $details): Enrollment
{
    $deliveryOption = ProductDeliveryOption::factory()->create([
        'delivery_method'  => DeliveryMethodEnum::LIVE_SESSION_NILIROOM,
        'fulfillment_type' => DeliveryMethodEnum::LIVE_SESSION_NILIROOM->getFulfillmentType(),
        'details_json'     => $details,
    ]);

    return createEnrollment($customer, DeliveryMethodEnum::LIVE_SESSION_NILIROOM, deliveryOption: $deliveryOption);
}
