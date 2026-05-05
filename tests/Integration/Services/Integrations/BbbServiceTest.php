<?php

declare(strict_types=1);

use App\Exceptions\Integrations\ExternalProvisioningException;
use App\Services\Integrations\BbbService;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Http::preventStrayRequests();
    $this->service = new BbbService();
    $this->service->setConfig([
        'base_url'             => 'https://bbb.test',
        'secret'               => 'secret',
        'api_path'             => '/bigbluebutton/api',
        'default_attendee_pw'  => 'ap-default',
        'default_moderator_pw' => 'mp-default',
    ]);
});

it('creates meeting with expected query payload', function (): void {
    Http::fake([
        'https://bbb.test/*' => Http::response([], 200),
    ]);

    $this->service->createMeeting('MEET-100', 'Physics 101', 'ap', 'mp');

    Http::assertSent(function ($request) {
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
        ->toThrow(ExternalProvisioningException::class, 'BBB create meeting request failed.');
});

it('throws when service used before configuration', function (): void {
    $service = new BbbService();

    expect(fn () => $service->createMeeting('MEET-100', 'Physics 101'))
        ->toThrow(ExternalProvisioningException::class, 'BBB service configuration is missing.');

    expect(fn () => $service->buildJoinUrl('MEET-100', 'Physics 101', "pass"))
        ->toThrow(ExternalProvisioningException::class, 'BBB service configuration is missing.');
});
