<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use App\Enums\System\SettingKeyEnum;
use App\Exceptions\Integrations\RecoverableProvisioningException;
use App\Exceptions\Integrations\UnrecoverableProvisioningException;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

final class SkyroomService extends AbstractIntegrationService
{
    public function findOrCreateUser(User $user): array
    {
        $username = 'user-'.$user->id;

        try {
            $existing = $this->request('getUser', ['username' => $username]);

            return ['skyroom_user_id' => (int) $existing['id']];
        } catch (UnrecoverableProvisioningException $e) {
            if (! str_contains($e->getMessage(), '15')) { // 15 = user not found
                throw $e;
            }
        }

        $skyroomUserId = $this->request('createUser', [
            'username' => $username,
            'password' => Str::random(16),
            'nickname' => $user->full_name ?? $username,
            'status'   => 1,
        ]);

        return ['skyroom_user_id' => (int) $skyroomUserId];
    }

    public function addUserToRoom(int $roomId, int $skyroomUserId): void
    {
        $this->request('addRoomUsers', [
            'room_id' => $roomId,
            'users'   => [['user_id' => $skyroomUserId]],
        ]);
    }

    public function createLoginUrl(
        int $roomId,
        string $userId,
        string $nickname,
        int $access = 1,
        int $ttl = 3600,
    ): string {
        return (string) $this->request('createLoginUrl', [
            'room_id'    => $roomId,
            'user_id'    => $userId,
            'nickname'   => $nickname,
            'access'     => $access,
            'concurrent' => 1,
            'language'   => 'fa',
            'ttl'        => $ttl,
        ]);
    }

    protected function getSettingKey(): SettingKeyEnum
    {
        return SettingKeyEnum::SKYROOM;
    }

    protected function getConfigFallbackPath(): string
    {
        return 'services.skyroom';
    }

    protected function validateConfig(): bool
    {
        // Deliberately does NOT throw — just returns false. assertConfigured() throws.
        return ! empty($this->config['api_key']);
    }

    private function request(string $action, array $params = []): mixed
    {
        $endpoint = ($this->config['base_url'] ?? '').'/'.$this->config['api_key'];
        $body     = array_filter(['action' => $action, 'params' => $params ?: null]);

        try {
            $response = Http::timeout(30)->acceptJson()->post($endpoint, $body);
        } catch (Throwable $e) {
            throw new RecoverableProvisioningException(
                "Skyroom network error on [{$action}]: {$e->getMessage()}",
                previous: $e,
            );
        }

        if ($response->failed()) {
            // HTTP-level failure from Skyroom's infrastructure — recoverable
            throw new RecoverableProvisioningException(
                "Skyroom HTTP error on [{$action}]: status {$response->status()}",
                $response->status(),
            );
        }

        $json = $response->json();

        if (! ($json['ok'] ?? false)) {
            $code    = (int) ($json['error_code'] ?? 0);
            $message = (string) ($json['error_message'] ?? 'Unknown Skyroom error');

            // Skyroom error codes 1–10 are authentication/parameter errors — unrecoverable.
            // Codes outside that range may be transient server-side issues — recoverable.
            // Consult https://skyroom.online/doc for the full list.
            if ($code >= 1 && $code <= 10) {
                throw new UnrecoverableProvisioningException("Skyroom [{$action}] error {$code}: {$message}");
            }

            throw new RecoverableProvisioningException("Skyroom [{$action}] error {$code}: {$message}");
        }

        return $json['result'] ?? null;
    }
}
