<?php

declare(strict_types=1);

use App\Exceptions\Integrations\ExternalProvisioningException;
use App\Services\Integrations\BbbService;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Http::preventStrayRequests();

    config([
        'services.bbb.base_url' => 'https://bbb.test',
        'services.bbb.api_path' => '/bigbluebutton/api',
        'services.bbb.timeout'  => 15,
    ]);
});

it('creates meeting with expected query payload', function (): void {
    Http::fake([
        'https://bbb.test/*' => Http::response([], 200),
    ]);

    $service = app(BbbService::class);
    $service->createMeeting('MEET-100', 'Physics 101', 'ap', 'mp');

    Http::assertSent(function ($request) {
        $payload = $request->data();

        return $request->url()                   === 'https://bbb.test/create'
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

    $service = app(BbbService::class);

    expect(fn () => $service->createMeeting('MEET-100', 'Physics 101', 'ap', 'mp'))
        ->toThrow(ExternalProvisioningException::class, 'BBB create meeting request failed.');
});

it('builds join url from configuration', function (): void {
    config([
        'services.bbb.base_url' => 'https://bbb.example.org/',
        'services.bbb.api_path' => '/custom/api/',
    ]);

    $service = app(BbbService::class);

    $url = $service->buildJoinUrl('MID 1', 'Jane Doe', 'pw+1');

    expect($url)->toBe('https://bbb.example.org/custom/api/join?meetingID=MID+1&fullName=Jane+Doe&password=pw%2B1');
});
