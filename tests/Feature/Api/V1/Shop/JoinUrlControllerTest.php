<?php

declare(strict_types=1);

use App\Contracts\Integrations\BbbClientContract;
use App\Contracts\Integrations\SkyroomClientContract;
use App\Enums\EnrollmentStatusEnum;
use App\Enums\Product\DeliveryMethodEnum;
use App\Models\Enrollment;

uses(Tests\Support\Traits\AuthTestTrait::class);

beforeEach(function (): void {
    $this->customer();
});

// ─── Ownership ──────────────────────────────────────────────────────────────

it('returns 404 when enrollment belongs to another user', function (): void {
    $otherUser  = App\Models\User::factory()->create();
    $enrollment = createEnrollment($otherUser, DeliveryMethodEnum::LIVE_SESSION_BBB);

    $this->getJson(route('api.v1.shop.student.courses.join', ['enrollment' => $enrollment->uuid]))
        ->assertNotFound();
});

// ─── BBB ─────────────────────────────────────────────────────────────────────

it('returns join url for BBB live session', function (): void {
    $enrollment = createEnrollment($this->user, DeliveryMethodEnum::LIVE_SESSION_BBB);
    markEnrollmentProvidersReady($enrollment);
    $enrollment->provisioning_data = [
        'providers' => [
            'bbb' => [
                'status' => 'success', 'data' => ['meeting_id' => 'meeting-abc-123'],
            ],
        ],
    ];
    $enrollment->save();
    $enrollment->update(['enrollment_status' => EnrollmentStatusEnum::ACTIVE]);
    $enrollment->provisioning_data = [
        'providers' => [
            'bbb' => [
                'status' => 'success',
                'data'   => ['meeting_id' => 'meeting-abc-123'],
            ],
        ],
    ];
    $enrollment->save();

    $joinUrl = 'https://bbb.example.com/join?meetingId=meeting-abc-123&fullName=Test+User';

    $this->mock(BbbClientContract::class, function ($mock) use ($joinUrl): void {
        $mock->shouldReceive('buildJoinUrl')
            ->once()
            ->with('meeting-abc-123', Mockery::any())
            ->andReturn($joinUrl);
    });

    $this->getJson(route('api.v1.shop.student.courses.join', ['enrollment' => $enrollment->uuid]))
        ->assertOk()
        ->assertJsonPath('data.url', $joinUrl)
        ->assertJsonPath('data.type', 'bbb');
});

it('returns 503 when BBB meeting is not provisioned yet', function (): void {
    $enrollment = createEnrollment($this->user, DeliveryMethodEnum::LIVE_SESSION_BBB);
    markEnrollmentProvidersReady($enrollment);
    $enrollment->provisioning_data = [
        'providers' => [
            'bbb' => [
                'status' => 'success', 'data' => ['meeting_id' => 'meeting-abc-123'],
            ],
        ],
    ];
    $enrollment->save();
    $enrollment->update(['enrollment_status' => EnrollmentStatusEnum::ACTIVE]);
    // provisioning_data has no bbb meeting_id
    $enrollment->provisioning_data = [
        'providers' => [
            'bbb' => [
                'status' => 'provisioning',
                'data'   => [],
            ],
        ],
    ];
    $enrollment->save();

    $this->getJson(route('api.v1.shop.student.courses.join', ['enrollment' => $enrollment->uuid]))
        ->assertStatus(503)
        ->assertJsonFragment(['message' => 'BBB meeting not provisioned yet.']);
});

// ─── Skyroom ─────────────────────────────────────────────────────────────────

it('returns join url for Skyroom live session', function (): void {
    $enrollment = createEnrollment($this->user, DeliveryMethodEnum::LIVE_SESSION_SKYROOM);
    markEnrollmentProvidersReady($enrollment);
    $enrollment->provisioning_data = [
        'providers' => [
            'skyroom' => [
                'status' => 'success', 'data' => ['room_id' => 456],
            ],
        ],
    ];
    $enrollment->save();
    $enrollment->update(['enrollment_status' => EnrollmentStatusEnum::ACTIVE]);
    $enrollment->provisioning_data = [
        'providers' => [
            'skyroom' => [
                'status' => 'success',
                'data'   => ['room_id' => 456],
            ],
        ],
    ];
    $enrollment->save();

    $joinUrl = 'https://skyroom.example.com/login?room=456';

    $this->mock(SkyroomClientContract::class, function ($mock) use ($joinUrl): void {
        $mock->shouldReceive('createLoginUrl')
            ->once()
            ->with(456, 'user-'.$this->user->id, Mockery::any())
            ->andReturn($joinUrl);
    });

    $this->getJson(route('api.v1.shop.student.courses.join', ['enrollment' => $enrollment->uuid]))
        ->assertOk()
        ->assertJsonPath('data.url', $joinUrl)
        ->assertJsonPath('data.type', 'skyroom');
});

it('returns 503 when Skyroom room is not provisioned yet', function (): void {
    $enrollment = createEnrollment($this->user, DeliveryMethodEnum::LIVE_SESSION_SKYROOM);
    markEnrollmentProvidersReady($enrollment);
    $enrollment->provisioning_data = [
        'providers' => [
            'skyroom' => [
                'status' => 'success', 'data' => ['room_id' => 456],
            ],
        ],
    ];
    $enrollment->save();
    $enrollment->update(['enrollment_status' => EnrollmentStatusEnum::ACTIVE]);
    // provisioning_data has no skyroom room_id
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
    $enrollment->update(['enrollment_status' => EnrollmentStatusEnum::ACTIVE]);

    $this->getJson(route('api.v1.shop.student.courses.join', ['enrollment' => $enrollment->uuid]))
        ->assertUnprocessable();
});

// ─── Throwable catch ────────────────────────────────────────────────────────

it('returns 500 when an unexpected error occurs', function (): void {
    $enrollment = createEnrollment($this->user, DeliveryMethodEnum::LIVE_SESSION_BBB);
    markEnrollmentProvidersReady($enrollment);
    $enrollment->provisioning_data = [
        'providers' => [
            'bbb' => [
                'status' => 'success', 'data' => ['meeting_id' => 'meeting-abc-123'],
            ],
        ],
    ];
    $enrollment->save();
    $enrollment->update(['enrollment_status' => EnrollmentStatusEnum::ACTIVE]);
    $enrollment->provisioning_data = [
        'providers' => [
            'bbb' => [
                'status' => 'success',
                'data'   => ['meeting_id' => 'meeting-abc-123'],
            ],
        ],
    ];
    $enrollment->save();

    $this->mock(BbbClientContract::class, function ($mock): void {
        $mock->shouldReceive('buildJoinUrl')
            ->once()
            ->andThrow(new RuntimeException('BbbService crashed'));
    });

    $this->getJson(route('api.v1.shop.student.courses.join', ['enrollment' => $enrollment->uuid]))
        ->assertServerError();
});

function markEnrollmentProvidersReady(Enrollment $enrollment): void
{
    $plan = $enrollment->provisioning_plan;

    foreach ($plan['providers'] ?? [] as $index => $provider) {
        $plan['providers'][$index]['readiness'] = 'ready';
    }

    $enrollment->updateQuietly(['provisioning_plan' => $plan]);
}
