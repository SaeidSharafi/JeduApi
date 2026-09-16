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
 * Fake the three Niliroom endpoints of the login-grant flow.
 *
 * @param  array<string, mixed>  $overrides
 */
function fakeNiliroomApi(array $overrides = []): void
{
    Http::fake($overrides + [
        'https://niliroom.test/api/v1/users/*'      => Http::response(['data' => ['id' => 'ident-1', 'name' => 'Ali Karimi', 'phone' => '09120000000', 'active' => true]]),
        'https://niliroom.test/api/v1/rooms/*'      => Http::response(['data' => ['id' => 'enroll-1', 'room_id' => 'room-public-1', 'user_id' => 'ident-1', 'role' => 'teacher', 'active' => true]]),
        'https://niliroom.test/api/v1/login-grants' => Http::response(['data' => [
            'id'         => 'grant-1',
            'url'        => 'https://niliroom.test/login/abc123',
            'expires_at' => '2026-09-16T12:05:00.000000Z',
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

/*
 * Mutation notes (`pest --mutate --parallel`, 96.61% — 57 tested, 2 uncovered):
 * The survivors are the `TIMEOUT_SECONDS` constant's IncrementInteger and
 * DecrementInteger mutants. `->timeout()` is a Guzzle client option, so it never
 * reaches the PSR-7 request that `Http::assertSent()` inspects: through this
 * seam the value is not observable, and asserting it would mean reaching into
 * the pending request. Left uncovered rather than coupling the test to the
 * client's internals.
 */
