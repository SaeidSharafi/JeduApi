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
 * Rooms are staff-created in the panel and referenced by their public ID; this adapter only
 * syncs the user, enrolls them, and issues a login or meeting join grant — it never creates
 * rooms, and the panel owns the room's meeting lifecycle.
 */
final class NiliroomService extends AbstractIntegrationService implements NiliroomClientContract
{
    private const string PROVIDER = 'eshop';

    private const string TEACHER_ROLE = 'teacher';

    private const string STUDENT_ROLE = 'student';

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
        $this->enroll($identityId, $roomId, self::TEACHER_ROLE);

        return $this->issueLoginGrant($identityId, $roomId);
    }

    public function issueStudentMeetingJoinGrant(User $user, string $roomId): string
    {
        $this->assertConfigured();

        $identityId = $this->syncUser($user);
        $this->enroll($identityId, $roomId, self::STUDENT_ROLE);

        $meetingId = $this->startOrGetRoomMeeting($roomId);

        return $this->issueMeetingJoinGrant($identityId, $meetingId);
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

        $identityId = $this->requiredString($data, 'id', 'user_identity_missing');

        return $identityId;
    }

    /**
     * The panel answers every call inside a `data` envelope, and a response that omits the field
     * the next call needs is unrecoverable rather than a blank identifier travelling onward.
     *
     * @param  array<string, mixed>  $data
     */
    private function requiredString(array $data, string $key, string $messageKey): string
    {
        $value = data_get($data, $key);

        if (! is_string($value) || $value === '') {
            throw new UnrecoverableProvisioningException(__('messages.integration.niliroom.'.$messageKey));
        }

        return $value;
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
     * Upsert, so a participant who is not enrolled yet gets access on their first login.
     */
    private function enroll(string $identityId, string $roomId, string $role): void
    {
        $endpoint = sprintf('%s/rooms/%s/enrollments/%s', self::API_PREFIX, $roomId, $identityId);

        $this->send(
            fn (PendingRequest $request): Response => $request->put($endpoint, ['role' => $role]),
            $endpoint,
        );
    }

    /**
     * A student joins the meeting a room is running, and the platform stores no meeting of its
     * own: the panel owns the meeting lifecycle, so this call returns the running meeting — or
     * starts one when nobody has yet — and hands back the public ID the join grant is addressed
     * to. A room whose meeting is already running therefore costs nothing extra.
     */
    private function startOrGetRoomMeeting(string $roomId): string
    {
        $endpoint = sprintf('%s/rooms/%s/meetings/start', self::API_PREFIX, $roomId);

        $data = $this->send(fn (PendingRequest $request): Response => $request->post($endpoint), $endpoint);

        return $this->requiredString($data, 'id', 'meeting_identity_missing');
    }

    /**
     * The grant is addressed to the enrolled identity, never to an anonymous guest: a guest
     * grant would mint a throwaway participant outside the room's roster.
     */
    private function issueMeetingJoinGrant(string $identityId, string $meetingId): string
    {
        $endpoint = sprintf('%s/meetings/%s/join-grants', self::API_PREFIX, $meetingId);

        $data = $this->send(
            fn (PendingRequest $request): Response => $request->post($endpoint, ['user_id' => $identityId]),
            $endpoint,
        );

        return $this->requiredString($data, 'join_url', 'meeting_join_url_missing');
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

        $url       = $this->requiredString($data, 'url', 'login_grant_invalid');
        $expiresAt = $this->requiredString($data, 'expires_at', 'login_grant_invalid');

        // The panel answers with an offset-bearing ISO-8601 instant (UTC in practice). Express
        // it in the application timezone, or every consumer renders it in the panel's zone:
        // `Verta` keeps the incoming offset, so the Jalali date would be hours off.
        return [
            'url'        => $url,
            'expires_at' => CarbonImmutable::parse($expiresAt)->setTimezone(config()->string('app.timezone')),
        ];
    }

    /**
     * Send one Niliroom mutation with the shared base URL, bearer token and a fresh
     * per-call `Idempotency-Key`: a replayed key would hand back a grant that the
     * participant may already have redeemed, so each request gets its own.
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
