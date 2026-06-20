<?php

declare(strict_types=1);

use App\Enums\System\SettingKeyEnum;
use App\Exceptions\Integrations\RecoverableProvisioningException;
use App\Exceptions\Integrations\UnrecoverableProvisioningException;
use App\Models\User;
use App\Services\Integrations\MoodleService;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Http::preventStrayRequests();

    $settings = $this->mock(SettingsService::class);
    $settings->shouldReceive('get')
        ->with(SettingKeyEnum::MOODLE, Mockery::any())
        ->andReturn([
            'base_url'           => 'https://moodle.test',
            'token'              => 'moodle-token',
            'auth_userkey_token' => 'AUTH_USER_KEY',
        ]);

    $this->moodleService = app(MoodleService::class);
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

    [$id] = $this->moodleService->findOrCreateUser($user);

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
                ['id' => 22, 'username' => 'student2'],
            ], 200),
    ]);

    [$id,$username] = $this->moodleService->findOrCreateUser($user);

    expect($id)->toBe(22);

    Http::assertSentCount(2);
});
it('throws when username does not exist on user model', function (): void {
    $user = User::factory()->create([
        'civil_id' => null,
    ]);

    expect(fn () => $this->moodleService->findOrCreateUser($user))
        ->toThrow(UnrecoverableProvisioningException::class, 'Moodle username source missing.');

});
it('throws when moodle user creation response missing id', function (): void {
    $user = User::factory()->create();

    Http::fake([
        'https://moodle.test/*' => Http::sequence()
            ->push([], 200)
            ->push([], 200),
    ]);

    expect(fn () => $this->moodleService->findOrCreateUser($user))
        ->toThrow(UnrecoverableProvisioningException::class, 'Moodle user creation failed.');
});

it('enrolls user with optional start and end times', function (): void {
    Http::fake([
        'https://moodle.test/*' => Http::response([], 200),
    ]);

    $this->moodleService->enrollUser(55, 101, 1700000000, 1700003600);

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
            'loginurl' => 'https://moodle.test?key=testkey',
        ], 200),
    ]);

    $url = $this->moodleService->createUserKey('1122334', 'AUTH_USER_KEY');

    expect($url)->toBe('https://moodle.test?key=testkey');

    Http::assertSent(function ($request) {
        return $request['wstoken']          === 'AUTH_USER_KEY'
            && $request['wsfunction']       === 'auth_userkey_request_login_url'
            && $request['user']['username'] === '1122334';
    });
});

it('throws when user key missing from moodle response', function (): void {
    Http::fake([
        'https://moodle.test/*' => Http::response([], 200),
    ]);

    expect(fn () => $this->moodleService->createUserKey('1122334', 'AUTH_USER_KEY'))
        ->toThrow(UnrecoverableProvisioningException::class, 'Moodle auth_userkey creation failed.');
});

it('throws when moodle request returns failed status', function (): void {
    Http::fake([
        'https://moodle.test/*' => Http::response([], 500),
    ]);

    expect(fn () => $this->moodleService->enrollUser(1, 2))
        ->toThrow(RecoverableProvisioningException::class, 'Moodle server error for enrol_manual_enrol_users.');
});
it('throws when response contains exception', function (): void {
    Http::fake([
        'https://moodle.test/*' => Http::response([
            'exception' => 'Exception',
            'message'   => 'Something went wrong',
        ], 200),
    ]);

    expect(fn () => $this->moodleService->createUserKey('1122334', 'AUTH_USER_KEY'))
        ->toThrow(UnrecoverableProvisioningException::class, 'Something went wrong');
});
it('throws when service used before configuration', function (): void {
    $settings = $this->mock(SettingsService::class);
    $settings->shouldReceive('get')
        ->with(SettingKeyEnum::MOODLE, Mockery::any())
        ->andReturn(['base_url' => '', 'token' => '']);

    $service = app(MoodleService::class);

    expect(fn () => $service->enrollUser(1, 2))
        ->toThrow(UnrecoverableProvisioningException::class);
});
