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

    private int $waitingTime;

    public function __construct()
    {
        $this->waitingTime = config('otp.waiting_time');

    }

    public function send(string $indentifier, string $guard, ?OtpTypeInterface $type = null, array $params = []): SentOtpDto
    {

        $this->type = $type;
        $this->trackingCode = Str::uuid()->toString();

        $otp = new SentOtpDto($this->getNewCode($indentifier, $guard), $type, $this->waitingTime, $this->trackingCode);

        event(new OtpPrepared(
            indentifier: $indentifier,
            guard: $guard,
            code: (string) $otp->code,
            type: $type,
            trackingCode: $otp->trackingCode, params: $params,
        ));

        return $otp;
    }

    public function sendAndRetryCheck(string $indentifier, string $guard, ?OtpTypeInterface $type = null, array $params = []): SentOtpDto
    {

        $this->type = $type;

        $created = $this->getSentAt($indentifier, $guard, $type);
        if (! $created) {
            return $this->send($indentifier, $guard, $type, $params);
        }

        $retryAfter = $created->addSeconds($this->waitingTime);
        if (Carbon::now()->greaterThan($retryAfter)) {
            return $this->send($indentifier, $guard, $type, $params);
        }

        $remainingTime = (int) Carbon::now()->diffInSeconds($retryAfter);

        throw ValidationException::withMessages([
            'otp' => [
                trans('otp-manager::otp.throttle', ['seconds' => $remainingTime]),
            ],
        ]);
    }

    public function verify(string $indentifier, string $guard, int $otp, string $trackingCode, ?OtpTypeInterface $type = null): bool
    {

        $this->type = $type;
        $this->trackingCode = $trackingCode;

        $otpDto = $this->getVerifyCode($indentifier, $guard, $type);

        if (! $otpDto || $otp !== $otpDto->code || $trackingCode !== $otpDto->trackingCode) {
            $this->handleVerificationAttempt($indentifier, $guard); // Handle failed verification attempt

            return false;
        }

        $this->resetSendAttempts($indentifier, $guard); // Reset on successful verification

        // Auto-delete the OTP code after successful verification
        $this->deleteVerifyCode($indentifier, $guard, $type);

        return true;
    }

    protected function handleVerificationAttempt(string $indentifier, string $guard): void
    {
        $attemptsKey = $this->getCacheKey($indentifier, $guard, 'verify_attempts');

        $maxAttempts = config('otp.max_verify_attempts', 3);

        $attempts = Cache::get($attemptsKey, 0) + 1;

        if ($attempts > $maxAttempts) {
            $this->deleteVerifyCode($indentifier, $guard, $this->type);
            Cache::forget($attemptsKey);

            throw ValidationException::withMessages([
                'otp' => [__('otp-manager::otp.request_new')],
            ]);
        }

        Cache::put($attemptsKey, $attempts, $this->waitingTime);
    }

    protected function resetSendAttempts(string $indentifier, string $guard): void
    {
        $attemptsKey = $this->getCacheKey($indentifier, $guard, 'verify_attempts');

        Cache::forget($attemptsKey);
    }

    public function getVerifyCode(string $indentifier, string $guard, ?OtpTypeInterface $type = null): ?OtpDto
    {
        $this->type = $type;

        return Cache::get($this->getCacheKey($indentifier, $guard, 'value'));
    }

    public function deleteVerifyCode(string $indentifier, string $guard, ?OtpTypeInterface $type = null): bool
    {
        $this->type = $type;

        return Cache::delete($this->getCacheKey($indentifier, $guard, 'value'));
    }

    public function getSentAt(string $indentifier, string $guard, ?OtpTypeInterface $type = null): ?Carbon
    {
        $this->type = $type;

        if (empty($indentifier)) {
            return null;
        }

        $created = Cache::get($this->getCacheKey($indentifier, $guard, 'created'));
        if (! $created) {
            return null;
        }

        return Carbon::createFromTimestamp($created);
    }

    public function isVerifyCodeHasBeenSent(string $indentifier, string $guard, ?OtpTypeInterface $type = null): bool
    {
        $this->type = $type;

        if (empty($indentifier)) {
            return false;
        }

        return Cache::get($this->getCacheKey($indentifier, $guard, 'value')) !== null;
    }

    protected function getNewCode(string $indentifier, string $guard): int
    {
        $min = config('otp.code_min');
        $max = config('otp.code_max');

        $otp = random_int($min, $max);

        $otpDto = new OtpDto($otp, $this->trackingCode);

        Cache::put($this->getCacheKey($indentifier, $guard, 'value'), $otpDto);
        Cache::put($this->getCacheKey($indentifier, $guard, 'created'), time());

        return $otp;
    }

    protected function getCacheKey(string $indentifier, string $guard, string $for): string
    {
        return sprintf(
            'otp_%s_%s_%s_%s',
            $indentifier,
            $guard,
            $for,
            $this->type?->identifier()
        );
    }

    protected function validateMobile(string $indentifier): void
    {
        $this->mobileValidator->validate($indentifier);
    }
}
