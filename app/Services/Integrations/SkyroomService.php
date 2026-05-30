<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use App\Enums\System\SettingKeyEnum;
use App\Exceptions\Integrations\ExternalProvisioningException;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

final class SkyroomService
{
    private const BASE_URL = 'https://www.skyroom.online/skyroom/api';

    public function __construct(private readonly SettingsService $settings) {}

    /**
     * Find an existing Skyroom user by username or create a new one.
     *
     * Username is derived from the app user ID: "user-{id}".
     * The Skyroom user ID is returned for storing in provisioning_data.
     *
     * @return array{skyroom_user_id: int}
     *
     * @throws ExternalProvisioningException
     */
    public function findOrCreateUser(User $user): array
    {
        $username = 'user-'.$user->id;

        // Try to find by username first
        try {
            $existing = $this->request('getUser', ['username' => $username]);

            return ['skyroom_user_id' => (int) $existing['id']];
        } catch (ExternalProvisioningException $e) {
            // Error code 15 = not found; re-throw anything else
            if (! str_contains($e->getMessage(), '15')) {
                throw $e;
            }
        }

        // Create a new user
        $skyroomUserId = $this->request('createUser', [
            'username' => $username,
            'password' => Str::random(16),
            'nickname' => $user->full_name ?? $username,
            'status'   => 1,
        ]);

        return ['skyroom_user_id' => (int) $skyroomUserId];
    }

    /**
     * Add a Skyroom user to a room with viewer access (access level 1).
     *
     * @throws ExternalProvisioningException
     */
    public function addUserToRoom(int $roomId, int $skyroomUserId): void
    {
        $this->request('addRoomUsers', [
            'room_id' => $roomId,
            'users'   => [['user_id' => $skyroomUserId]],
        ]);
    }

    /**
     * Generate a direct login URL for a user to join a room.
     *
     * Does not require a pre-registered Skyroom user.
     * The userId is used only for concurrent login control.
     *
     * @throws ExternalProvisioningException
     */
    public function createLoginUrl(
        int $roomId,
        string $userId,
        string $nickname,
        int $access = 1,
        int $ttl = 3600,
    ): string {
        $url = $this->request('createLoginUrl', [
            'room_id'    => $roomId,
            'user_id'    => $userId,
            'nickname'   => $nickname,
            'access'     => $access,
            'concurrent' => 1,
            'language'   => 'fa',
            'ttl'        => $ttl,
        ]);

        return (string) $url;
    }

    /**
     * Perform a Skyroom API call.
     *
     * @throws ExternalProvisioningException
     */
    private function request(string $action, array $params = []): mixed
    {
        $apiKey   = $this->resolveApiKey();
        $endpoint = self::BASE_URL.'/'.$apiKey;

        $body = ['action' => $action];
        if (! empty($params)) {
            $body['params'] = $params;
        }

        try {
            $response = Http::timeout(30)
                ->acceptJson()
                ->post($endpoint, $body);
        } catch (Throwable $e) {
            throw new ExternalProvisioningException(
                "Skyroom network error on [{$action}]: {$e->getMessage()}",
                previous: $e,
            );
        }

        if ($response->failed()) {
            throw new ExternalProvisioningException(
                "Skyroom HTTP error on [{$action}]: status {$response->status()}"
            );
        }

        $json = $response->json();

        if (! ($json['ok'] ?? false)) {
            $code    = $json['error_code']    ?? 0;
            $message = $json['error_message'] ?? 'Unknown error';

            throw new ExternalProvisioningException(
                "Skyroom API error {$code} on [{$action}]: {$message}"
            );
        }

        return $json['result'] ?? null;
    }

    /**
     * @throws ExternalProvisioningException
     */
    private function resolveApiKey(): string
    {
        $config = $this->settings->get(SettingKeyEnum::SKYROOM);
        $apiKey = (string) data_get($config, 'api_key', '');

        if ($apiKey === '') {
            throw new ExternalProvisioningException('Skyroom api_key is not configured.');
        }

        return $apiKey;
    }
}
