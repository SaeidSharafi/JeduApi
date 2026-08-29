<?php

declare(strict_types=1);

use App\Enums\EnrollmentStatusEnum;
use App\Enums\Product\DeliveryMethodEnum;
use App\Exceptions\Integrations\RecoverableProvisioningException;
use App\Exceptions\Integrations\UnrecoverableProvisioningException;
use App\Models\Enrollment;
use App\Models\ProductDeliveryOption;
use App\Services\Integrations\BbbService;
use App\Services\Integrations\MoodleService;
use App\Services\Integrations\SkyroomService;
use App\Services\Integrations\SpotPlayerService;
use App\Services\Provisioning\Providers\BbbProvisioningProvider;
use App\Services\Provisioning\Providers\MoodleProvisioningProvider;
use App\Services\Provisioning\Providers\MoodleQuizProvisioningProvider;
use App\Services\Provisioning\Providers\SkyroomProvisioningProvider;
use App\Services\Provisioning\Providers\SpotPlayerProvisioningProvider;

function adapterEnrollment(string $provider, array $details): Enrollment
{
    $option = ProductDeliveryOption::factory()->create([
        'delivery_method' => DeliveryMethodEnum::VIDEO_PLATFORM_SPOTPLAYER,
        'details_json'    => $details,
    ]);

    $enrollment = Enrollment::factory()->create([
        'product_delivery_option_id' => $option->id,
        'enrollment_status'          => EnrollmentStatusEnum::ACTIVE,
    ]);

    $enrollment->update([
        'provisioning_plan' => [
            'version'   => 1,
            'providers' => [['provider' => $provider, 'applicable' => true, 'readiness' => 'ready']],
        ],
    ]);

    return $enrollment->fresh();
}

it('provisions SpotPlayer and returns canonical references', function (): void {
    $enrollment = adapterEnrollment('spotplayer', ['spot_id' => 'SPOT-1']);
    $service    = $this->mock(SpotPlayerService::class);
    $service->shouldReceive('isEnabled')->andReturnTrue();
    $service->shouldReceive('assertConfigured');
    $service->shouldReceive('issueLicense')->with('SPOT-1', Mockery::type(App\Models\User::class))->andReturn([
        'license_key' => 'LIC-1', 'player_url' => 'https://player.test/1',
    ]);

    expect((new SpotPlayerProvisioningProvider($service))->provision($enrollment))->toBe([
        'spot_id' => 'SPOT-1', 'license_key' => 'LIC-1', 'player_url' => 'https://player.test/1',
    ]);
});

it('marks an uncertain SpotPlayer response as manual action', function (): void {
    $enrollment = adapterEnrollment('spotplayer', ['spot_id' => 'SPOT-1']);
    $service    = $this->mock(SpotPlayerService::class);
    $service->shouldReceive('isEnabled')->andReturnTrue();
    $service->shouldReceive('assertConfigured');
    $service->shouldReceive('issueLicense')->andThrow(new RecoverableProvisioningException('timeout', 0, null,
        ['http_status' => 504]));

    expect(fn () => (new SpotPlayerProvisioningProvider($service))->provision($enrollment))
        ->toThrow(UnrecoverableProvisioningException::class, 'ambiguous');
});

it('rejects a Moodle Quiz provider that is not in the canonical plan', function (): void {
    $enrollment = adapterEnrollment('spotplayer', ['moodle_quiz_course_id' => 99]);
    $service    = $this->mock(MoodleService::class);
    $service->shouldReceive('isEnabled')->andReturnTrue();
    $service->shouldReceive('assertConfigured');

    expect(fn () => (new MoodleQuizProvisioningProvider($service))->provision($enrollment))
        ->toThrow(UnrecoverableProvisioningException::class, 'not applicable');
});

it('rejects a Moodle Quiz provider when its course reference is missing', function (): void {
    $enrollment = adapterEnrollment('moodle_quiz', []);
    $service    = $this->mock(MoodleService::class);
    $service->shouldReceive('isEnabled')->andReturnTrue();
    $service->shouldReceive('assertConfigured');

    expect(fn () => (new MoodleQuizProvisioningProvider($service))->provision($enrollment))
        ->toThrow(UnrecoverableProvisioningException::class, 'course id');
});

it('provisions BBB from a staff-created room without creating it', function (): void {
    $enrollment = adapterEnrollment('bbb', ['meeting_id' => 'NILI-ROOM-1']);
    $service    = $this->mock(BbbService::class);
    $service->shouldReceive('isEnabled')->andReturnTrue();
    $service->shouldReceive('assertConfigured');
    $service->shouldNotReceive('createMeeting');

    expect((new BbbProvisioningProvider($service))->provision($enrollment))
        ->toBe(['meeting_id' => 'NILI-ROOM-1']);
});

it('provisions Skyroom into a staff-created room', function (): void {
    $enrollment = adapterEnrollment('skyroom', ['room_id' => 10]);
    $service    = $this->mock(SkyroomService::class);
    $service->shouldReceive('isEnabled')->andReturnTrue();
    $service->shouldReceive('assertConfigured');
    $service->shouldReceive('findOrCreateUser')->once()->andReturn(['skyroom_user_id' => 42]);
    $service->shouldReceive('addUserToRoom')->once()->with(10, 42);

    expect((new SkyroomProvisioningProvider($service))->provision($enrollment))
        ->toBe(['room_id' => 10, 'skyroom_user_id' => 42]);
});

it('rejects a missing staff-created room as manual action', function (): void {
    $enrollment = adapterEnrollment('skyroom', ['room_id' => null]);
    $service    = $this->mock(SkyroomService::class);
    $service->shouldReceive('isEnabled')->andReturnTrue();
    $service->shouldReceive('assertConfigured');

    expect(fn () => (new SkyroomProvisioningProvider($service))->provision($enrollment))
        ->toThrow(UnrecoverableProvisioningException::class);
});

it('revokes Moodle access for suspended lifecycle changes', function (): void {
    $enrollment = adapterEnrollment('moodle', []);
    $enrollment->update([
        'provisioning_data' => [
            'providers' => [
                'moodle' => [
                    'status' => 'success', 'data' => [
                        'moodle_user_id' => 42, 'moodle_course_id' => 99,
                    ],
                ],
            ],
        ],
    ]);
    $service = $this->mock(MoodleService::class);
    $service->shouldReceive('unenrollUser')->once()->with(42, 99);

    expect((new MoodleProvisioningProvider($service))->reconcileAccess($enrollment, [
        'requested_status' => EnrollmentStatusEnum::SUSPENDED->value,
    ]))->toBe(['moodle_user_id' => 42, 'moodle_course_id' => 99]);
});
