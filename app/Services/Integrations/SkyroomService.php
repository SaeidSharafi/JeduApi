<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use App\Enums\System\SettingKeyEnum;
use App\Exceptions\Integrations\ExternalProvisioningException;
use App\Models\User;
use App\Services\SettingsService;

final class SkyroomService
{
    public function __construct(private readonly SettingsService $settings) {}

    /**
     * Find an existing Skyroom user by phone/email or create a new one.
     *
     * @return array{skyroom_user_id: int}
     *
     * @throws ExternalProvisioningException
     */
    public function findOrCreateUser(User $user): array
    {
        $config = $this->resolveConfig();

        // TODO: implement Skyroom API call
        // POST /api/1.1/addMember or /api/1.1/getMember
        // See Skyroom REST API docs for exact endpoint
        throw new ExternalProvisioningException('SkyroomService::findOrCreateUser is not yet implemented.');
    }

    /**
     * Add a user to a Skyroom room.
     *
     * @throws ExternalProvisioningException
     */
    public function addUserToRoom(int $roomId, int $skyroomUserId): void
    {
        $config = $this->resolveConfig();

        // TODO: implement Skyroom API call
        // POST /api/1.1/addRoomUsers
        throw new ExternalProvisioningException('SkyroomService::addUserToRoom is not yet implemented.');
    }

    /**
     * Generate a one-time login URL for a user to join a room.
     *
     * @throws ExternalProvisioningException
     */
    public function createLoginUrl(int $roomId, int $skyroomUserId): string
    {
        $config = $this->resolveConfig();

        // TODO: implement Skyroom API call
        // POST /api/1.1/getLoginUrl or similar
        throw new ExternalProvisioningException('SkyroomService::createLoginUrl is not yet implemented.');
    }

    /**
     * @return array{api_key: string, base_url: string}
     *
     * @throws ExternalProvisioningException
     */
    private function resolveConfig(): array
    {
        $config  = $this->settings->get(SettingKeyEnum::SKYROOM);
        $apiKey  = (string) data_get($config, 'api_key', '');
        $baseUrl = (string) data_get($config, 'base_url', '');

        if ($apiKey === '' || $baseUrl === '') {
            throw new ExternalProvisioningException('Skyroom service configuration is missing.');
        }

        return [
            'api_key'  => $apiKey,
            'base_url' => $baseUrl,
        ];
    }
}
