<?php

declare(strict_types=1);

use App\Enums\System\SettingKeyEnum;
use App\Exceptions\Integrations\RecoverableProvisioningException;
use App\Exceptions\Integrations\UnrecoverableProvisioningException;
use App\Services\Integrations\BbbService;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Http::preventStrayRequests();

    $settings = $this->mock(SettingsService::class);
    $settings->shouldReceive('get')
        ->with(SettingKeyEnum::BIG_BLUE_BUTTON, Mockery::any())
        ->andReturn([
            'base_url'                   => 'https://bbb.test',
            'secret'                     => 'secret',
            'api_path'                   => '/bigbluebutton/api',
            'default_attendee_password'  => 'ap-default',
            'default_moderator_password' => 'mp-default',
        ]);

    $this->service = app(BbbService::class);
});

it('creates meeting with expected query payload', function (): void {
    Http::fake([
        'https://bbb.test/*' => Http::response([], 200),
    ]);

    $this->service->createMeeting('MEET-100', 'Physics 101', 'ap', 'mp');

    Http::assertSent(function ($request): bool {
        $payload = $request->data();

        return str_contains($request->url(), 'https://bbb.test/bigbluebutton/api/create')
            && ($payload['meetingID'] ?? null)   === 'MEET-100'
            && ($payload['name'] ?? null)        === 'Physics 101'
            && ($payload['attendeePW'] ?? null)  === 'ap'
            && ($payload['moderatorPW'] ?? null) === 'mp';
    });
});

it('throws when create meeting request fails', function (): void {
    Http::fake([
        'https://bbb.test/*' => Http::response([], 500),
    ]);

    expect(fn () => $this->service->createMeeting('MEET-100', 'Physics 101', 'ap', 'mp'))
        ->toThrow(RecoverableProvisioningException::class, 'BBB create meeting request failed.');
});

it('builds a join url carrying the configured attendee password when none is given', function (): void {
    $url = $this->service->buildJoinUrl('MEET-100', 'Student One');

    parse_str((string) parse_url($url, PHP_URL_QUERY), $params);

    expect($params['meetingID'])->toBe('MEET-100')
        ->and($params['fullName'])->toBe('Student One')
        ->and($params['password'])->toBe('ap-default')
        ->and($params['checksum'])->not->toBeEmpty();
});

it('uses the configured attendee and moderator passwords when none are given to createMeeting', function (): void {
    Http::fake([
        'https://bbb.test/*' => Http::response([], 200),
    ]);

    $this->service->createMeeting('MEET-100', 'Physics 101');

    Http::assertSent(function ($request): bool {
        $payload = $request->data();

        return str_contains($request->url(), 'https://bbb.test/bigbluebutton/api/create')
            && ($payload['attendeePW'] ?? null)  === 'ap-default'
            && ($payload['moderatorPW'] ?? null) === 'mp-default';
    });
});

it('throws when service used before configuration', function (): void {
    $settings = $this->mock(SettingsService::class);
    $settings->shouldReceive('get')
        ->with(SettingKeyEnum::BIG_BLUE_BUTTON, Mockery::any())
        ->andReturn(['base_url' => '', 'secret' => '']);

    $service = app(BbbService::class);

    expect(fn () => $service->assertConfigured())
        ->toThrow(UnrecoverableProvisioningException::class);

    expect(fn () => $service->assertConfigured())
        ->toThrow(UnrecoverableProvisioningException::class);
});
