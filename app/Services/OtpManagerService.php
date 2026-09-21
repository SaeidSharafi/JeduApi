<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Cache\CacheStore;
use App\Contracts\OtpGeneratorInterface;
use App\Contracts\OtpTypeInterface;
use App\Data\OtpManager\OtpDto;
use App\Data\OtpManager\SentOtpDto;
use App\Enums\System\CacheKey;
use App\Events\OtpPrepared;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

final class OtpManagerService
{
    private string $trackingCode;

    private ?OtpTypeInterface $type = null;

    private int $waitingTime;

    private int $lockSeconds;

    private int $lockBlockSeconds;

    private OtpGeneratorInterface $otpGenerator;

    private CacheStore $cache;

    public function __construct(OtpGeneratorInterface $otpGenerator, CacheStore $cache)
    {
        $this->otpGenerator     = $otpGenerator;
        $this->cache            = $cache;
        $this->waitingTime      = config('otp.waiting_time');
        $this->lockSeconds      = config('otp.lock_seconds', 5);
        $this->lockBlockSeconds = config('otp.lock_block_seconds', 1);

    }

    /**
     * @template TK
     * @template TV
     *
     * @param  array<TK,TV>  $params
     */
    public function send(
        string $identifier,
        string $guard,
        ?OtpTypeInterface $type = null,
        array $params = []
    ): SentOtpDto {

        $this->type         = $type;
        $this->trackingCode = $this->otpGenerator->generateTrackingCode();

        $otp = new SentOtpDto($this->getNewCode($identifier, $guard), $type, $this->waitingTime, $this->trackingCode);

        event(new OtpPrepared(
            identifier: $identifier,
            guard: $guard,
            code: (string) $otp->code,
            type: $type,
            trackingCode: $otp->trackingCode,
            params: $params,
        ));

        return $otp;
    }

    /**
     * @template TK
     * @template TV
     *
     * @param  array<TK,TV>  $params
     */
    public function sendAndRetryCheck(
        string $identifier,
        string $guard,
        ?OtpTypeInterface $type = null,
        array $params = []
    ): SentOtpDto {

        $this->type = $type;

        return $this->runWithinOtpLock($identifier, $guard, $type, function () use ($identifier, $guard, $type, $params): SentOtpDto {
            $created = $this->getSentAt($identifier, $guard, $type);
            if (! $created) {
                return $this->send($identifier, $guard, $type, $params);
            }

            $retryAfter = $created->copy()->addSeconds($this->waitingTime);
            if (Carbon::now()->greaterThan($retryAfter)) {
                return $this->send($identifier, $guard, $type, $params);
            }

            $remainingTime = (int) Carbon::now()->diffInSeconds($retryAfter);

            throw ValidationException::withMessages([
                'otp' => [
                    trans('messages.auth.otp.throttle', ['seconds' => $remainingTime]),
                ],
            ]);
        });
    }

    public function verify(
        string $identifier,
        string $guard,
        int $otp,
        string $trackingCode,
        ?OtpTypeInterface $type = null
    ): bool {

        $this->type         = $type;
        $this->trackingCode = $trackingCode;

        return $this->runWithinOtpLock($identifier, $guard, $type, function () use ($identifier, $guard, $otp, $trackingCode, $type): bool {
            $otpDto = $this->getVerifyCode($identifier, $guard, $type);

            if (! $otpDto && $this->hasExpired($identifier, $guard, $type)) {
                $this->resetSendAttempts($identifier, $guard);

                throw ValidationException::withMessages([
                    'otp' => [__('messages.auth.otp.expired_code')],
                ]);
            }

            if ($this->hasExpired($identifier, $guard, $type)) {
                $this->deleteVerifyCode($identifier, $guard, $type);
                $this->resetSendAttempts($identifier, $guard);

                throw ValidationException::withMessages([
                    'otp' => [__('messages.auth.otp.expired_code')],
                ]);
            }

            if (! $otpDto || $otp !== $otpDto->code || $trackingCode !== $otpDto->trackingCode) {
                $this->handleVerificationAttempt($identifier, $guard);

                return false;
            }

            $this->resetSendAttempts($identifier, $guard);
            $this->deleteVerifyCode($identifier, $guard, $type);

            return true;
        });
    }

    public function getVerifyCode(string $identifier, string $guard, ?OtpTypeInterface $type = null): ?OtpDto
    {
        $this->type = $type;

        return $this->cache->get(CacheKey::OtpValue, $this->params($identifier, $guard));
    }

    public function deleteVerifyCode(string $identifier, string $guard, ?OtpTypeInterface $type = null): bool
    {
        $this->type = $type;

        $params  = $this->params($identifier, $guard);
        $existed = $this->cache->get(CacheKey::OtpValue, $params) !== null;

        $this->cache->forget(CacheKey::OtpValue, $params);

        return $existed;
    }

    public function getSentAt(string $identifier, string $guard, ?OtpTypeInterface $type = null): ?Carbon
    {
        $this->type = $type;

        if (empty($identifier)) {
            return null;
        }

        $created = $this->cache->get(CacheKey::OtpMarker, $this->params($identifier, $guard));
        if (! $created) {
            return null;
        }

        return Carbon::createFromTimestamp($created);
    }

    public function isVerifyCodeHasBeenSent(string $identifier, string $guard, ?OtpTypeInterface $type = null): bool
    {
        $this->type = $type;

        if (empty($identifier)) {
            return false;
        }

        return $this->cache->get(CacheKey::OtpValue, $this->params($identifier, $guard)) !== null;
    }

    private function handleVerificationAttempt(string $identifier, string $guard): void
    {
        $params = $this->params($identifier, $guard);

        $maxAttempts = config('otp.max_verify_attempts', 5);

        $attempts = (int) ($this->cache->get(CacheKey::OtpAttempts, $params) ?? 0) + 1;
        $this->cache->put(CacheKey::OtpAttempts, $params, $attempts);

        if ($attempts > $maxAttempts) {
            $this->deleteVerifyCode($identifier, $guard, $this->type);
            $this->cache->forget(CacheKey::OtpAttempts, $params);

            throw ValidationException::withMessages([
                'otp' => [__('messages.auth.otp.throttle', ['seconds' => CacheKey::OtpAttempts->ttl()])],
            ]);
        }
    }

    private function resetSendAttempts(string $identifier, string $guard): void
    {
        $this->cache->forget(CacheKey::OtpAttempts, $this->params($identifier, $guard));
    }

    private function getNewCode(string $identifier, string $guard): int
    {
        $otp = $this->otpGenerator->generateCode();

        $otpDto = new OtpDto($otp, $this->trackingCode);

        $params = $this->params($identifier, $guard);

        $this->cache->put(CacheKey::OtpValue, $params, $otpDto);
        $this->cache->put(CacheKey::OtpMarker, $params, time());

        return $otp;
    }

    private function hasExpired(string $identifier, string $guard, ?OtpTypeInterface $type = null): bool
    {
        $sentAt = $this->getSentAt($identifier, $guard, $type);

        if (! $sentAt) {
            return $this->cache->get(CacheKey::OtpValue, $this->params($identifier, $guard)) === null;
        }

        return Carbon::now()->greaterThan($sentAt->copy()->addSeconds((int) CacheKey::OtpValue->ttl()));
    }

    /**
     * @template T
     *
     * @param  callable():T  $callback
     * @return T
     */
    private function runWithinOtpLock(string $identifier, string $guard, ?OtpTypeInterface $type, callable $callback)
    {
        $lockKey = $this->getLockKey($identifier, $guard, $type);

        try {
            return Cache::lock($lockKey, $this->lockSeconds)->block($this->lockBlockSeconds, $callback);
        } catch (LockTimeoutException) {
            throw ValidationException::withMessages([
                'otp' => [trans('messages.auth.otp.throttle', ['seconds' => $this->lockBlockSeconds])],
            ]);
        }
    }

    private function getLockKey(string $identifier, string $guard, ?OtpTypeInterface $type = null): string
    {
        return sprintf('otp_lock_%s_%s_%s', $identifier, $guard, $type?->identifier() ?? 'none');
    }

    /**
     * Named parameters shared by every OTP registry key.
     *
     * @return array{identifier: string, guard: string, type: string|null}
     */
    private function params(string $identifier, string $guard): array
    {
        return [
            'identifier' => $identifier,
            'guard'      => $guard,
            'type'       => $this->type?->identifier(),
        ];
    }
}
