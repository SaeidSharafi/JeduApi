<?php

declare(strict_types=1);

use App\Enums\System\SettingKeyEnum;
use App\Exceptions\Integrations\RecoverableProvisioningException;
use App\Exceptions\Integrations\UnrecoverableProvisioningException;
use App\Models\User;
use App\Services\Integrations\NiliroomService;
use App\Services\SettingsService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

covers(NiliroomService::class);

describe('issueTeacherLoginGrant', function (): void {
    it('syncs the teacher identity, enrolls them as a teacher, and returns the room login grant', function (): void {
        fakeNiliroomApi();

        $grant = makeNiliroomService()->issueTeacherLoginGrant(
            makeNiliroomUser(7, 'Ali', 'Karimi', '09120000000'),
            'room-public-1',
        );

        Http::assertSentInOrder([
            fn (Request $request): bool => $request->method() === 'PUT'
                && $request->url()                            === 'https://niliroom.test/api/v1/users/eshop/user-7'
                && $request->data()                           === ['name' => 'Ali Karimi', 'phone' => '09120000000']
                && $request->hasHeader('Idempotency-Key'),
            fn (Request $request): bool => $request->method() === 'PUT'
                && $request->url()                            === 'https://niliroom.test/api/v1/rooms/room-public-1/enrollments/ident-1'
                && $request->data()                           === ['role' => 'teacher']
                && $request->hasHeader('Idempotency-Key'),
            fn (Request $request): bool => $request->method() === 'POST'
                && $request->url()                            === 'https://niliroom.test/api/v1/login-grants'
                && $request->data()                           === ['user_id' => 'ident-1', 'room_id' => 'room-public-1']
                && $request->hasHeader('Idempotency-Key'),
        ]);

        expect($grant['url'])->toBe('https://niliroom.test/login/abc123')
            // The panel answers in UTC; the same instant reads as Tehran, so a consumer that
            // renders the value directly cannot land hours off.
            ->and($grant['expires_at']->toIso8601String())->toBe('2026-09-16T15:35:00+03:30');
    });

    it('identifies a teacher with no profile name by their subject instead of sending an empty name', function (): void {
        fakeNiliroomApi();

        makeNiliroomService()->issueTeacherLoginGrant(
            makeNiliroomUser(7, null, null, '09120000000'),
            'room-public-1',
        );

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://niliroom.test/api/v1/users/eshop/user-7'
            && $request->data()['name']                                 === 'user-7');
    });

    it('sends a fresh idempotency key on every call so a repeated request is not replayed a used grant', function (): void {
        fakeNiliroomApi();

        $service = makeNiliroomService();
        $user    = makeNiliroomUser(7);

        $service->issueTeacherLoginGrant($user, 'room-public-1');
        $service->issueTeacherLoginGrant($user, 'room-public-1');

        $grantKeys = collect(Http::recorded())
            ->map(fn (array $pair): ?string => $pair[0]->hasHeader('Idempotency-Key')
                ? $pair[0]->header('Idempotency-Key')[0]
                : null)
            ->filter()
            ->values();

        expect($grantKeys)->toHaveCount(6)
            ->and($grantKeys->unique())->toHaveCount(6);
    });
});

describe('issueStudentMeetingJoinGrant', function (): void {
    it('syncs the student identity, enrolls them as a student, and returns the room meeting join url', function (): void {
        fakeNiliroomApi();

        $joinUrl = makeNiliroomService()->issueStudentMeetingJoinGrant(
            makeNiliroomUser(7, 'Ali', 'Karimi', '09120000000'),
            'room-public-1',
        );

        Http::assertSentInOrder([
            fn (Request $request): bool => $request->method() === 'PUT'
                && $request->url()                            === 'https://niliroom.test/api/v1/users/eshop/user-7'
                && $request->data()                           === ['name' => 'Ali Karimi', 'phone' => '09120000000']
                && $request->hasHeader('Idempotency-Key'),
            fn (Request $request): bool => $request->method() === 'PUT'
                && $request->url()                            === 'https://niliroom.test/api/v1/rooms/room-public-1/enrollments/ident-1'
                && $request->data()                           === ['role' => 'student']
                && $request->hasHeader('Idempotency-Key'),
            // The panel owns the meeting lifecycle: the platform holds no meeting ID of its own.
            fn (Request $request): bool => $request->method() === 'POST'
                && $request->url()                            === 'https://niliroom.test/api/v1/rooms/room-public-1/meetings/start'
                && $request->hasHeader('Idempotency-Key'),
            fn (Request $request): bool => $request->method() === 'POST'
                && $request->url()                            === 'https://niliroom.test/api/v1/meetings/meeting-public-1/join-grants'
                // The enrolled identity joins; a guest grant would bypass the room enrollment.
                && $request->data() === ['user_id' => 'ident-1']
                && $request->hasHeader('Idempotency-Key'),
        ]);

        expect($joinUrl)->toBe('https://niliroom.test/meetings/join/xyz789');
    });

    it('throws UnrecoverableProvisioningException when the API token is missing', function (): void {
        $settings = Mockery::mock(SettingsService::class);
        $settings->shouldReceive('get')
            ->with(SettingKeyEnum::NILIROOM, Mockery::any())
            ->andReturn(['enabled' => true, 'base_url' => 'https://niliroom.test', 'api_token' => '']);

        $service = new NiliroomService($settings);

        expect(fn (): string => $service->issueStudentMeetingJoinGrant(makeNiliroomUser(7), 'room-public-1'))
            ->toThrow(UnrecoverableProvisioningException::class);
    });

    it('throws RecoverableProvisioningException when the room meeting cannot be started', function (): void {
        fakeNiliroomApi([
            'https://niliroom.test/api/v1/rooms/room-public-1/meetings/start' => Http::response([], 503),
        ]);

        expect(fn (): string => makeNiliroomService()->issueStudentMeetingJoinGrant(makeNiliroomUser(7), 'room-public-1'))
            ->toThrow(RecoverableProvisioningException::class);
    });

    it('throws UnrecoverableProvisioningException when the provider returns no meeting identity', function (): void {
        fakeNiliroomApi([
            'https://niliroom.test/api/v1/rooms/room-public-1/meetings/start' => Http::response(['data' => ['id' => null]]),
        ]);

        expect(fn (): string => makeNiliroomService()->issueStudentMeetingJoinGrant(makeNiliroomUser(7), 'room-public-1'))
            ->toThrow(UnrecoverableProvisioningException::class);
    });

    it('throws UnrecoverableProvisioningException when the meeting is unknown to the provider', function (): void {
        fakeNiliroomApi([
            'https://niliroom.test/api/v1/meetings/*' => Http::response(['error' => ['code' => 'not_found', 'message' => 'Meeting not found']], 404),
        ]);

        expect(fn (): string => makeNiliroomService()->issueStudentMeetingJoinGrant(makeNiliroomUser(7), 'room-public-1'))
            ->toThrow(UnrecoverableProvisioningException::class);
    });

    it('throws UnrecoverableProvisioningException when the provider returns no meeting join url', function (): void {
        fakeNiliroomApi([
            'https://niliroom.test/api/v1/meetings/*' => Http::response(['data' => ['meeting_id' => 'meeting-public-1', 'guest_id' => null]], 201),
        ]);

        expect(fn (): string => makeNiliroomService()->issueStudentMeetingJoinGrant(makeNiliroomUser(7), 'room-public-1'))
            ->toThrow(UnrecoverableProvisioningException::class);
    });

    it('throws RecoverableProvisioningException when the provider fails to issue the grant', function (): void {
        fakeNiliroomApi([
            'https://niliroom.test/api/v1/meetings/*' => Http::response([], 503),
        ]);

        expect(fn (): string => makeNiliroomService()->issueStudentMeetingJoinGrant(makeNiliroomUser(7), 'room-public-1'))
            ->toThrow(RecoverableProvisioningException::class);
    });
});

describe('error handling', function (): void {
    it('throws UnrecoverableProvisioningException when the API token is missing', function (): void {
        $settings = Mockery::mock(SettingsService::class);
        $settings->shouldReceive('get')
            ->with(SettingKeyEnum::NILIROOM, Mockery::any())
            ->andReturn(['enabled' => true, 'base_url' => 'https://niliroom.test', 'api_token' => '']);

        $service = new NiliroomService($settings);

        expect(fn (): array => $service->issueTeacherLoginGrant(makeNiliroomUser(7), 'room-public-1'))
            ->toThrow(UnrecoverableProvisioningException::class);
    });

    it('throws RecoverableProvisioningException when the network is unreachable', function (): void {
        fakeNiliroomApi([
            'https://niliroom.test/api/v1/users/*' => fn () => throw new ConnectionException('Connection refused'),
        ]);

        expect(fn (): array => makeNiliroomService()->issueTeacherLoginGrant(makeNiliroomUser(7), 'room-public-1'))
            ->toThrow(RecoverableProvisioningException::class);
    });

    it('throws RecoverableProvisioningException when the provider fails on enrollment', function (): void {
        fakeNiliroomApi([
            'https://niliroom.test/api/v1/rooms/*' => Http::response([], 503),
        ]);

        expect(fn (): array => makeNiliroomService()->issueTeacherLoginGrant(makeNiliroomUser(7), 'room-public-1'))
            ->toThrow(RecoverableProvisioningException::class);
    });

    it('throws UnrecoverableProvisioningException when the API client is not authorized', function (): void {
        fakeNiliroomApi([
            'https://niliroom.test/api/v1/users/*' => Http::response(['error' => ['code' => 'forbidden', 'message' => 'Missing scope']], 403),
        ]);

        expect(fn (): array => makeNiliroomService()->issueTeacherLoginGrant(makeNiliroomUser(7), 'room-public-1'))
            ->toThrow(UnrecoverableProvisioningException::class);
    });

    it('throws UnrecoverableProvisioningException when the room is unknown to the provider', function (): void {
        fakeNiliroomApi([
            'https://niliroom.test/api/v1/rooms/*' => Http::response(['error' => ['code' => 'not_found', 'message' => 'Room not found']], 404),
        ]);

        expect(fn (): array => makeNiliroomService()->issueTeacherLoginGrant(makeNiliroomUser(7), 'room-public-1'))
            ->toThrow(UnrecoverableProvisioningException::class);
    });

    it('throws UnrecoverableProvisioningException when the grant payload is rejected', function (): void {
        fakeNiliroomApi([
            'https://niliroom.test/api/v1/login-grants' => Http::response(['message' => 'The user id field is required.', 'errors' => ['user_id' => ['The user id field is required.']]], 422),
        ]);

        expect(fn (): array => makeNiliroomService()->issueTeacherLoginGrant(makeNiliroomUser(7), 'room-public-1'))
            ->toThrow(UnrecoverableProvisioningException::class);
    });

    it('throws UnrecoverableProvisioningException when the provider returns no user identity', function (): void {
        fakeNiliroomApi([
            'https://niliroom.test/api/v1/users/*' => Http::response(['data' => ['id' => null, 'name' => 'Ali Karimi', 'phone' => '09120000000', 'active' => true]]),
        ]);

        expect(fn (): array => makeNiliroomService()->issueTeacherLoginGrant(makeNiliroomUser(7), 'room-public-1'))
            ->toThrow(UnrecoverableProvisioningException::class);
    });

    it('throws UnrecoverableProvisioningException when the provider returns no redemption url', function (): void {
        fakeNiliroomApi([
            'https://niliroom.test/api/v1/login-grants' => Http::response(['data' => ['id' => 'grant-1', 'expires_at' => '2026-09-16T12:05:00.000000Z']], 201),
        ]);

        expect(fn (): array => makeNiliroomService()->issueTeacherLoginGrant(makeNiliroomUser(7), 'room-public-1'))
            ->toThrow(UnrecoverableProvisioningException::class);
    });
});

/**
 * Fake the Niliroom endpoints of the teacher login-grant and student meeting-join flows.
 *
 * @param  array<string, mixed>  $overrides
 */
function fakeNiliroomApi(array $overrides = []): void
{
    Http::fake($overrides + [
        'https://niliroom.test/api/v1/users/*'                => Http::response(['data' => ['id' => 'ident-1', 'name' => 'Ali Karimi', 'phone' => '09120000000', 'active' => true]]),
        'https://niliroom.test/api/v1/rooms/*/enrollments/*'  => Http::response(['data' => ['id' => 'enroll-1', 'room_id' => 'room-public-1', 'user_id' => 'ident-1', 'role' => 'teacher', 'active' => true]]),
        'https://niliroom.test/api/v1/rooms/*/meetings/start' => Http::response(['data' => [
            'id'         => 'meeting-public-1',
            'room_id'    => 'room-public-1',
            'status'     => 'started',
            'started_at' => '2026-09-16T12:00:00.000000Z',
            'ended_at'   => null,
        ]]),
        'https://niliroom.test/api/v1/login-grants' => Http::response(['data' => [
            'id'         => 'grant-1',
            'url'        => 'https://niliroom.test/login/abc123',
            'expires_at' => '2026-09-16T12:05:00.000000Z',
        ]], 201),
        'https://niliroom.test/api/v1/meetings/*/join-grants' => Http::response(['data' => [
            'meeting_id' => 'meeting-public-1',
            'join_url'   => 'https://niliroom.test/meetings/join/xyz789',
            'guest_id'   => null,
        ]], 201),
    ]);
}

function makeNiliroomService(): NiliroomService
{
    $settings = Mockery::mock(SettingsService::class);
    $settings->shouldReceive('get')
        ->with(SettingKeyEnum::NILIROOM, Mockery::any())
        ->andReturn(['enabled' => true, 'base_url' => 'https://niliroom.test', 'api_token' => 'niliroom-token']);

    return new NiliroomService($settings);
}

function makeNiliroomUser(
    int $id = 1,
    ?string $firstName = 'Ali',
    ?string $lastName = 'Karimi',
    ?string $phone = '09120000000',
): User {
    return (new User())->forceFill([
        'id'         => $id,
        'first_name' => $firstName,
        'last_name'  => $lastName,
        'phone'      => $phone,
    ]);
}
