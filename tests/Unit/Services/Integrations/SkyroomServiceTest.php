<?php

declare(strict_types=1);

use App\Enums\System\SettingKeyEnum;
use App\Exceptions\Integrations\ExternalProvisioningException;
use App\Models\User;
use App\Services\Integrations\SkyroomService;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Http;

function makeSkyroomService(string $apiKey = 'test-api-key'): SkyroomService
{
    $settings = Mockery::mock(SettingsService::class);
    $settings->shouldReceive('get')
        ->with(SettingKeyEnum::SKYROOM)
        ->andReturn($apiKey !== '' ? ['api_key' => $apiKey] : []);

    return new SkyroomService($settings);
}

function skyroomEndpoint(string $apiKey = 'test-api-key'): string
{
    return 'https://www.skyroom.online/skyroom/api/'.$apiKey.'*';
}

function makeUser(int $id = 1, string $name = 'Test User'): User
{
    return (new User())->forceFill(['id' => $id, 'full_name' => $name]);
}

describe('findOrCreateUser', function () {
    it('returns skyroom_user_id when getUser finds an existing user', function () {
        Http::fake([
            skyroomEndpoint() => Http::response([
                'ok'     => true,
                'result' => ['id' => 42, 'username' => 'user-1'],
            ]),
        ]);

        $result = makeSkyroomService()->findOrCreateUser(makeUser(1));

        expect($result)->toBe(['skyroom_user_id' => 42]);
    });

    it('creates a new user when getUser returns error_code 15 (not found)', function () {
        Http::fake([
            skyroomEndpoint() => Http::sequence()
                ->push(['ok' => false, 'error_code' => 15, 'error_message' => 'User not found'])
                ->push(['ok' => true, 'result' => 99]),
        ]);

        $result = makeSkyroomService()->findOrCreateUser(makeUser(5, 'Ali Karimi'));

        expect($result)->toBe(['skyroom_user_id' => 99]);
    });

    it('re-throws ExternalProvisioningException when getUser returns a non-15 error', function () {
        Http::fake([
            skyroomEndpoint() => Http::response([
                'ok'            => false,
                'error_code'    => 99,
                'error_message' => 'Server error',
            ]),
        ]);

        expect(fn () => makeSkyroomService()->findOrCreateUser(makeUser(3)))
            ->toThrow(ExternalProvisioningException::class);
    });
});

describe('addUserToRoom', function () {
    it('calls addRoomUsers with correct payload on success', function () {
        Http::fake([
            skyroomEndpoint() => Http::response(['ok' => true, 'result' => null]),
        ]);

        $service = makeSkyroomService();

        expect(fn () => $service->addUserToRoom(10, 42))->not->toThrow(ExternalProvisioningException::class);

        Http::assertSent(function ($request) {
            $body = $request->data();

            return $body['action']                        === 'addRoomUsers'
                && $body['params']['room_id']             === 10
                && $body['params']['users'][0]['user_id'] === 42;
        });
    });

    it('throws when API returns ok: false', function () {
        Http::fake([
            skyroomEndpoint() => Http::response([
                'ok'            => false,
                'error_code'    => 7,
                'error_message' => 'Permission denied',
            ]),
        ]);

        expect(fn () => makeSkyroomService()->addUserToRoom(10, 42))
            ->toThrow(ExternalProvisioningException::class, '7');
    });
});

describe('createLoginUrl', function () {
    it('returns the login URL string from the API result', function () {
        $url = 'https://www.skyroom.online/ch/test/room?token=abc123';

        Http::fake([
            skyroomEndpoint() => Http::response(['ok' => true, 'result' => $url]),
        ]);

        $result = makeSkyroomService()->createLoginUrl(roomId: 10, userId: 'u-7', nickname: 'Test User');

        expect($result)->toBe($url);
    });
});

describe('error handling', function () {
    it('throws ExternalProvisioningException when api_key is not configured', function () {
        $settings = Mockery::mock(SettingsService::class);
        $settings->shouldReceive('get')
            ->with(SettingKeyEnum::SKYROOM)
            ->andReturn([]);

        $service = new SkyroomService($settings);

        expect(fn () => $service->findOrCreateUser(makeUser(1)))
            ->toThrow(ExternalProvisioningException::class, 'api_key is not configured');
    });

    it('throws ExternalProvisioningException on HTTP failure (5xx)', function () {
        Http::fake([
            skyroomEndpoint() => Http::response([], 500),
        ]);

        expect(fn () => makeSkyroomService()->findOrCreateUser(makeUser(1)))
            ->toThrow(ExternalProvisioningException::class);
    });

    it('throws ExternalProvisioningException on network error', function () {
        Http::fake([
            skyroomEndpoint() => fn () => throw new RuntimeException('Connection refused'),
        ]);

        expect(fn () => makeSkyroomService()->findOrCreateUser(makeUser(1)))
            ->toThrow(ExternalProvisioningException::class, 'network error');
    });
});
