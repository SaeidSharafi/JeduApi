<?php

declare(strict_types=1);

use App\Exceptions\Integrations\ExternalProvisioningException;
use App\Models\User;
use App\Services\Integrations\MoodleService;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Http::preventStrayRequests();

    config([
        'services.moodle.base_url'           => 'https://moodle.test',
        'services.moodle.token'              => 'moodle-token',
        'services.moodle.auth_userkey_token' => 'moodle-key-token',
        'services.moodle.default_role_id'    => 5,
        'services.moodle.timeout'            => 15,
    ]);
});

it('returns existing moodle user id when found by email', function (): void {
    $user = User::factory()->create([
        'email' => 'student@example.test',
    ]);

    Http::fake([
        'https://moodle.test/*' => Http::response([
            ['id' => 11],
        ], 200),
    ]);

    $service = app(MoodleService::class);

    $id = $service->findOrCreateUser($user);

    expect($id)->toBe(11);

    Http::assertSentCount(1);
});

it('creates moodle user when lookup is empty', function (): void {
    $user = User::factory()->create([
        'email' => 'student2@example.test',
    ]);

    Http::fake([
        'https://moodle.test/*' => Http::sequence()
            ->push([], 200)
            ->push([
                ['id' => 22],
            ], 200),
    ]);

    $service = app(MoodleService::class);

    $id = $service->findOrCreateUser($user);

    expect($id)->toBe(22);

    Http::assertSentCount(2);
});

it('throws when moodle user creation response missing id', function (): void {
    $user = User::factory()->create();

    Http::fake([
        'https://moodle.test/*' => Http::sequence()
            ->push([], 200)
            ->push([], 200),
    ]);

    $service = app(MoodleService::class);

    expect(fn () => $service->findOrCreateUser($user))
        ->toThrow(ExternalProvisioningException::class, 'Moodle user creation failed.');
});

it('enrolls user with optional start and end times', function (): void {
    Http::fake([
        'https://moodle.test/*' => Http::response([], 200),
    ]);

    $service = app(MoodleService::class);
    $service->enrollUser(55, 101, 1700000000, 1700003600);

    Http::assertSent(function ($request) {
        $payload = $request->data();

        return $request->url()                      === 'https://moodle.test/webservice/rest/server.php'
            && $payload['wsfunction']               === 'enrol_manual_enrol_users'
            && $payload['enrolments[0][userid]']    === 55
            && $payload['enrolments[0][courseid]']  === 101
            && $payload['enrolments[0][timestart]'] === 1700000000
            && $payload['enrolments[0][timeend]']   === 1700003600;
    });
});

it('creates moodle user key', function (): void {
    Http::fake([
        'https://moodle.test/*' => Http::response([
            'key' => 'auth-key-123',
        ], 200),
    ]);

    $service = app(MoodleService::class);

    $key = $service->createUserKey(77);

    expect($key)->toBe('auth-key-123');

    Http::assertSent(function ($request) {
        return $request['wstoken']    === 'moodle-key-token'
            && $request['wsfunction'] === 'auth_userkey_create_user_key'
            && $request['userid']     === 77;
    });
});

it('throws when user key missing from moodle response', function (): void {
    Http::fake([
        'https://moodle.test/*' => Http::response([], 200),
    ]);

    $service = app(MoodleService::class);

    expect(fn () => $service->createUserKey(77))
        ->toThrow(ExternalProvisioningException::class, 'Moodle auth_userkey creation failed.');
});

it('throws when moodle request returns failed status', function (): void {
    Http::fake([
        'https://moodle.test/*' => Http::response([], 500),
    ]);

    $service = app(MoodleService::class);

    expect(fn () => $service->enrollUser(1, 2))
        ->toThrow(ExternalProvisioningException::class, 'Moodle request failed for enrol_manual_enrol_users.');
});

it('throws moodle exception message when api returns exception payload', function (): void {
    Http::fake([
        'https://moodle.test/*' => Http::response([
            'exception' => 'required_param_missing_exception',
            'message'   => 'Missing required key',
        ], 200),
    ]);

    $service = app(MoodleService::class);

    expect(fn () => $service->enrollUser(1, 2))
        ->toThrow(ExternalProvisioningException::class, 'Missing required key');
});
