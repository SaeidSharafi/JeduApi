<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\OtpGeneratorInterface;
use App\Contracts\OtpTypeInterface;
use App\Data\OtpManager\OtpDto;
use App\Data\OtpManager\SentOtpDto;
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

    private int $ttlSeconds;

    private int $markerTtlSeconds;

    private int $verifyAttemptWindowSeconds;

    private int $lockSeconds;

    private int $lockBlockSeconds;

    private OtpGeneratorInterface $otpGenerator;

    public function __construct(OtpGeneratorInterface $otpGenerator)
    {
        $this->otpGenerator               = $otpGenerator;
        $this->waitingTime                = config('otp.waiting_time');
        $this->ttlSeconds                 = config('otp.ttl_seconds', 300);
        $this->markerTtlSeconds           = config('otp.marker_ttl_seconds', 900);
        $this->verifyAttemptWindowSeconds = config('otp.verify_attempt_window_seconds', 300);
        $this->lockSeconds                = config('otp.lock_seconds', 5);
        $this->lockBlockSeconds           = config('otp.lock_block_seconds', 1);

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

        return Cache::get($this->getCacheKey($identifier, $guard, 'value'));
    }

    public function deleteVerifyCode(string $identifier, string $guard, ?OtpTypeInterface $type = null): bool
    {
        $this->type = $type;

        $valueDeleted = Cache::delete($this->getCacheKey($identifier, $guard, 'value'));

        return $valueDeleted;
    }

    public function getSentAt(string $identifier, string $guard, ?OtpTypeInterface $type = null): ?Carbon
    {
        $this->type = $type;

        if (empty($identifier)) {
            return null;
        }

        $created = Cache::get($this->getCacheKey($identifier, $guard, 'created'));
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

        return Cache::get($this->getCacheKey($identifier, $guard, 'value')) !== null;
    }

    private function handleVerificationAttempt(string $identifier, string $guard): void
    {
        $attemptsKey = $this->getCacheKey($identifier, $guard, 'verify_attempts');

        $maxAttempts = config('otp.max_verify_attempts', 5);

        if (! Cache::has($attemptsKey)) {
            Cache::put($attemptsKey, 0, $this->verifyAttemptWindowSeconds);
        }

        $attempts = (int) Cache::increment($attemptsKey);

        Cache::put($attemptsKey, $attempts, $this->verifyAttemptWindowSeconds);

        if ($attempts > $maxAttempts) {
            $this->deleteVerifyCode($identifier, $guard, $this->type);
            Cache::forget($attemptsKey);

            throw ValidationException::withMessages([
                'otp' => [__('messages.auth.otp.throttle', ['seconds' => $this->verifyAttemptWindowSeconds])],
            ]);
        }
    }

    private function resetSendAttempts(string $identifier, string $guard): void
    {
        $attemptsKey = $this->getCacheKey($identifier, $guard, 'verify_attempts');

        Cache::forget($attemptsKey);
    }

    private function getNewCode(string $identifier, string $guard): int
    {
        $otp = $this->otpGenerator->generateCode();

        $otpDto = new OtpDto($otp, $this->trackingCode);

        Cache::put($this->getCacheKey($identifier, $guard, 'value'), $otpDto, $this->ttlSeconds);
        Cache::put($this->getCacheKey($identifier, $guard, 'created'), time(), $this->markerTtlSeconds);

        return $otp;
    }

    private function hasExpired(string $identifier, string $guard, ?OtpTypeInterface $type = null): bool
    {
        $sentAt = $this->getSentAt($identifier, $guard, $type);

        if (! $sentAt) {
            return Cache::get($this->getCacheKey($identifier, $guard, 'value')) === null;
        }

        return Carbon::now()->greaterThan($sentAt->copy()->addSeconds($this->ttlSeconds));
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

    private function getCacheKey(string $identifier, string $guard, string $for): string
    {
        return sprintf(
            'otp_%s_%s_%s_%s',
            $identifier,
            $guard,
            $for,
            $this->type?->identifier()
        );
    }
}
