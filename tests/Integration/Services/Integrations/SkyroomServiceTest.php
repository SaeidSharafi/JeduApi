<?php

declare(strict_types=1);

use App\Enums\System\SettingKeyEnum;
use App\Exceptions\Integrations\RecoverableProvisioningException;
use App\Exceptions\Integrations\UnrecoverableProvisioningException;
use App\Models\User;
use App\Services\Integrations\SkyroomService;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Http;

describe('findOrCreateUser', function (): void {
    it('returns skyroom_user_id when getUser finds an existing user', function (): void {
        Http::fake([
            skyroomEndpoint() => Http::response([
                'ok'     => true,
                'result' => ['id' => 42, 'username' => 'user-1'],
            ]),
        ]);

        $result = makeSkyroomService()->findOrCreateUser(makeUser(1));

        expect($result)->toBe(['skyroom_user_id' => 42]);
    });

    it('throws RecoverableProvisioningException when getUser returns error_code 15 (not found)', function (): void {
        Http::fake([
            skyroomEndpoint() => Http::sequence()
                ->push(['ok' => false, 'error_code' => 15, 'error_message' => 'User not found'])
                ->push(['ok' => true, 'result' => 99]),
        ]);

        expect(fn (): array => makeSkyroomService()->findOrCreateUser(makeUser(5, 'Ali Karimi')))
            ->toThrow(RecoverableProvisioningException::class);
    });

    it('throws RecoverableProvisioningException when getUser returns a non-15 error', function (): void {
        Http::fake([
            skyroomEndpoint() => Http::response([
                'ok'            => false,
                'error_code'    => 99,
                'error_message' => 'Server error',
            ]),
        ]);

        expect(fn (): array => makeSkyroomService()->findOrCreateUser(makeUser(3)))
            ->toThrow(RecoverableProvisioningException::class);
    });
});

describe('addUserToRoom', function (): void {
    it('calls addRoomUsers with correct payload on success', function (): void {
        Http::fake([
            skyroomEndpoint() => Http::response(['ok' => true, 'result' => null]),
        ]);

        $service = makeSkyroomService();

        expect(fn () => $service->addUserToRoom(10, 42))->not->toThrow(UnrecoverableProvisioningException::class);

        Http::assertSent(function ($request) {
            $body = $request->data();

            return $body['action']                        === 'addRoomUsers'
                && $body['params']['room_id']             === 10
                && $body['params']['users'][0]['user_id'] === 42;
        });
    });

    it('throws when API returns ok: false', function (): void {
        Http::fake([
            skyroomEndpoint() => Http::response([
                'ok'            => false,
                'error_code'    => 7,
                'error_message' => 'Permission denied',
            ]),
        ]);

        expect(fn () => makeSkyroomService()->addUserToRoom(10, 42))
            ->toThrow(UnrecoverableProvisioningException::class, 'Skyroom [addRoomUsers] error 7: Permission denied');
    });
});

describe('createLoginUrl', function (): void {
    it('returns the login URL string from the API result', function (): void {
        $url = 'https://www.skyroom.online/ch/test/room?token=abc123';

        Http::fake([
            skyroomEndpoint() => Http::response(['ok' => true, 'result' => $url]),
        ]);

        $result = makeSkyroomService()->createLoginUrl(roomId: 10, userId: 'u-7', nickname: 'Test User');

        expect($result)->toBe($url);
    });
});

describe('error handling', function (): void {
    it('throws RecoverableProvisioningException when Skyroom service configuration is missing.', function (): void {
        $settings = Mockery::mock(SettingsService::class);
        $settings->shouldReceive('get')
            ->with(SettingKeyEnum::SKYROOM, Mockery::any())
            ->andReturn(['enabled' => true, 'base_url' => 'https://www.skyroom.online/skyroom/api', 'api_key' => '']);

        $service = new SkyroomService($settings);

        expect(fn (): array => $service->findOrCreateUser(makeUser(1)))
            ->toThrow(RecoverableProvisioningException::class);
    });

    it('throws RecoverableProvisioningException on HTTP failure (5xx)', function (): void {
        Http::fake([
            skyroomEndpoint() => Http::response([], 500),
        ]);

        expect(fn (): array => makeSkyroomService()->findOrCreateUser(makeUser(1)))
            ->toThrow(RecoverableProvisioningException::class);
    });

    it('throws RecoverableProvisioningException on network error', function (): void {
        Http::fake([
            skyroomEndpoint() => fn () => throw new RuntimeException('Connection refused'),
        ]);

        expect(fn (): array => makeSkyroomService()->findOrCreateUser(makeUser(1)))
            ->toThrow(RecoverableProvisioningException::class);
    });
});

function makeSkyroomService(string $apiKey = 'test-api-key'): SkyroomService
{
    $settings = Mockery::mock(SettingsService::class);
    $settings->shouldReceive('get')
        ->with(SettingKeyEnum::SKYROOM, Mockery::any())
        ->andReturn($apiKey !== '' ? ['enabled' => true, 'base_url' => 'https://www.skyroom.online/skyroom/api', 'api_key' => $apiKey] : []);

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
