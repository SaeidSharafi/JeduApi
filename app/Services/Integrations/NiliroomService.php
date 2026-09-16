<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use App\Contracts\Integrations\NiliroomClientContract;
use App\Enums\System\SettingKeyEnum;
use App\Exceptions\Integrations\RecoverableProvisioningException;
use App\Exceptions\Integrations\UnrecoverableProvisioningException;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * The Niliroom panel adapter (ADR 0007): one provider identity per shop user, keyed by
 * `provider=eshop` and `subject=user-{shopUserId}`, so a teacher is never duplicated.
 *
 * Rooms are staff-created in the panel and referenced by their public ID; this adapter
 * only syncs the user, enrolls them, and issues a login grant — it never creates rooms.
 */
final class NiliroomService extends AbstractIntegrationService implements NiliroomClientContract
{
    private const string PROVIDER = 'eshop';

    private const string TEACHER_ROLE = 'teacher';

    private const int TIMEOUT_SECONDS = 15;

    /**
     * The organization-scoped API version prefix, as declared by the provider's OpenAPI
     * server. The configured base URL is the host, matching the other REST adapters.
     */
    private const string API_PREFIX = '/api/v1';

    public function issueTeacherLoginGrant(User $user, string $roomId): array
    {
        $this->assertConfigured();

        $identityId = $this->syncUser($user);
        $this->enrollAsTeacher($identityId, $roomId);

        return $this->issueLoginGrant($identityId, $roomId);
    }

    protected function getSettingKey(): SettingKeyEnum
    {
        return SettingKeyEnum::NILIROOM;
    }

    protected function getConfigFallbackPath(): string
    {
        return 'provisioning.providers.niliroom';
    }

    protected function validateConfig(): bool
    {
        return ! empty($this->config['base_url']) && ! empty($this->config['api_token']);
    }

    /**
     * Returns the provider's opaque user identity public ID that the follow-up calls address.
     */
    private function syncUser(User $user): string
    {
        $endpoint = sprintf('%s/users/%s/user-%d', self::API_PREFIX, self::PROVIDER, $user->id);

        $data = $this->send(
            fn (PendingRequest $request): Response => $request->put($endpoint, [
                'name'  => $this->displayName($user),
                'phone' => (string) $user->phone,
            ]),
            $endpoint,
        );

        $identityId = data_get($data, 'id');

        if (! is_string($identityId) || $identityId === '') {
            throw new UnrecoverableProvisioningException(
                __('messages.integration.niliroom.user_identity_missing')
            );
        }

        return $identityId;
    }

    /**
     * The provider rejects an empty `name`, and shop users are not required to have
     * one, so a teacher without a profile name is identified by their subject —
     * the same fallback Skyroom uses for a nameless username.
     */
    private function displayName(User $user): string
    {
        return mb_trim(($user->first_name ?? '').' '.($user->last_name ?? '')) ?: 'user-'.$user->id;
    }

    /**
     * Upsert, so a teacher who is not enrolled yet gets access on their first login.
     */
    private function enrollAsTeacher(string $identityId, string $roomId): void
    {
        $endpoint = sprintf('%s/rooms/%s/enrollments/%s', self::API_PREFIX, $roomId, $identityId);

        $this->send(
            fn (PendingRequest $request): Response => $request->put($endpoint, ['role' => self::TEACHER_ROLE]),
            $endpoint,
        );
    }

    /**
     * @return array{url: string, expires_at: CarbonInterface}
     */
    private function issueLoginGrant(string $identityId, string $roomId): array
    {
        $endpoint = self::API_PREFIX.'/login-grants';

        $data = $this->send(
            fn (PendingRequest $request): Response => $request->post($endpoint, [
                'user_id' => $identityId,
                'room_id' => $roomId,
            ]),
            $endpoint,
        );

        $url       = data_get($data, 'url');
        $expiresAt = data_get($data, 'expires_at');

        if (! is_string($url) || $url === '' || ! is_string($expiresAt) || $expiresAt === '') {
            throw new UnrecoverableProvisioningException(
                __('messages.integration.niliroom.login_grant_invalid')
            );
        }

        return ['url' => $url, 'expires_at' => CarbonImmutable::parse($expiresAt)];
    }

    /**
     * Send one Niliroom mutation with the shared base URL, bearer token and a fresh
     * per-call `Idempotency-Key`: a replayed key would hand back a grant that the
     * teacher may already have redeemed, so each request gets its own.
     *
     * @param  Closure(PendingRequest): Response  $send
     * @return array<string, mixed>
     */
    private function send(Closure $send, string $endpoint): array
    {
        $request = Http::baseUrl((string) $this->config['base_url'])
            ->timeout(self::TIMEOUT_SECONDS)
            ->acceptJson()
            ->withToken((string) $this->config['api_token'])
            ->withHeaders(['Idempotency-Key' => (string) Str::uuid()]);

        try {
            $response = $send($request);
        } catch (ConnectionException $e) {
            throw new RecoverableProvisioningException(
                __('messages.integration.niliroom.network_error', ['endpoint' => $endpoint, 'message' => $e->getMessage()]),
                previous: $e,
            );
        }

        $this->handleHttpErrors($response, $endpoint);

        return (array) data_get($response->json(), 'data', []);
    }
}
