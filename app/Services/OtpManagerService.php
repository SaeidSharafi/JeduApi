<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\OtpTypeInterface;
use App\Dto\OtpManager\OtpDto;
use App\Dto\OtpManager\SentOtpDto;
use App\Events\OtpPrepared;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OtpManagerService
{
    private string $trackingCode;

    private ?OtpTypeInterface $type = null;

    protected int $waitingTime;

    public function __construct()
    {
        $this->waitingTime = config('otp.waiting_time');

    }

    public function send(string $identifier, string $guard, ?OtpTypeInterface $type = null, array $params = []): SentOtpDto
    {

        $this->type = $type;
        $this->trackingCode = $this->generateTrackingCode();

        $otp = new SentOtpDto($this->getNewCode($identifier, $guard), $type, $this->waitingTime, $this->trackingCode);

        event(new OtpPrepared(
            identifier: $identifier,
            guard: $guard,
            code: (string) $otp->code,
            type: $type,
            trackingCode: $otp->trackingCode, params: $params,
        ));

        return $otp;
    }

    public function sendAndRetryCheck(string $identifier, string $guard, ?OtpTypeInterface $type = null, array $params = []): SentOtpDto
    {

        $this->type = $type;

        $created = $this->getSentAt($identifier, $guard, $type);
        if (! $created) {
            return $this->send($identifier, $guard, $type, $params);
        }

        $retryAfter = $created->addSeconds($this->waitingTime);
        if (Carbon::now()->greaterThan($retryAfter)) {
            return $this->send($identifier, $guard, $type, $params);
        }

        $remainingTime = (int) Carbon::now()->diffInSeconds($retryAfter);

        throw ValidationException::withMessages([
            'otp' => [
                trans('otp-manager::otp.throttle', ['seconds' => $remainingTime]),
            ],
        ]);
    }

    public function verify(string $identifier, string $guard, int $otp, string $trackingCode, ?OtpTypeInterface $type = null): bool
    {

        $this->type = $type;
        $this->trackingCode = $trackingCode;

        $otpDto = $this->getVerifyCode($identifier, $guard, $type);

        if (! $otpDto || $otp !== $otpDto->code || $trackingCode !== $otpDto->trackingCode) {
            $this->handleVerificationAttempt($identifier, $guard); // Handle failed verification attempt

            return false;
        }

        $this->resetSendAttempts($identifier, $guard); // Reset on successful verification

        // Auto-delete the OTP code after successful verification
        $this->deleteVerifyCode($identifier, $guard, $type);

        return true;
    }

    protected function handleVerificationAttempt(string $identifier, string $guard): void
    {
        $attemptsKey = $this->getCacheKey($identifier, $guard, 'verify_attempts');

        $maxAttempts = config('otp.max_verify_attempts', 3);

        $attempts = Cache::get($attemptsKey, 0) + 1;

        if ($attempts > $maxAttempts) {
            $this->deleteVerifyCode($identifier, $guard, $this->type);
            Cache::forget($attemptsKey);

            throw ValidationException::withMessages([
                'otp' => [__('otp-manager::otp.request_new')],
            ]);
        }

        Cache::put($attemptsKey, $attempts, $this->waitingTime);
    }

    protected function resetSendAttempts(string $identifier, string $guard): void
    {
        $attemptsKey = $this->getCacheKey($identifier, $guard, 'verify_attempts');

        Cache::forget($attemptsKey);
    }

    public function getVerifyCode(string $identifier, string $guard, ?OtpTypeInterface $type = null): ?OtpDto
    {
        $this->type = $type;

        return Cache::get($this->getCacheKey($identifier, $guard, 'value'));
    }

    public function deleteVerifyCode(string $identifier, string $guard, ?OtpTypeInterface $type = null): bool
    {
        $this->type = $type;

        return Cache::delete($this->getCacheKey($identifier, $guard, 'value'));
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

    protected function getNewCode(string $identifier, string $guard): int
    {
        $otp = $this->generateCode();

        $otpDto = new OtpDto($otp, $this->trackingCode);

        Cache::put($this->getCacheKey($identifier, $guard, 'value'), $otpDto);
        Cache::put($this->getCacheKey($identifier, $guard, 'created'), time());

        return $otp;
    }

    protected function generateCode(): int{
        $min = config('otp.code_min');
        $max = config('otp.code_max');

         return random_int($min, $max);
    }

    protected function generateTrackingCode(): string
    {
        return Str::uuid()->toString();
    }
    protected function getCacheKey(string $identifier, string $guard, string $for): string
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
