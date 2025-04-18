<?php

use App\Dto\OtpManager\OtpDto;
use App\Dto\OtpManager\SentOtpDto;
use App\Enums\OtpType;
use App\Events\OtpPrepared;
use App\Services\OtpManagerService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->otpService = app(OtpManagerService::class);
    $this->identifier = '09351234567';
    $this->guard = 'user';
    $this->otpType = OtpType::SIGNIN;
    Event::fake();
    Cache::flush();
});

test('it can generate and send otp', function () {
    $result = $this->otpService->send($this->identifier, $this->guard, $this->otpType);

    expect($result)
        ->toBeInstanceOf(SentOtpDto::class)
        ->and($result->code)->toBeInt()->toBeBetween(config('otp.code_min'), config('otp.code_max'))
        ->and($result->otpType)->toBe($this->otpType)
        ->and($result->trackingCode)->toBeString()->not->toBeEmpty();

    Event::assertDispatched(OtpPrepared::class);
});

test('it stores otp in cache with correct structure', function () {
    $result = $this->otpService->send($this->identifier, $this->guard, $this->otpType);

    $cachedOtp = $this->otpService->getVerifyCode($this->identifier, $this->guard, $this->otpType);

    expect($cachedOtp)
        ->toBeInstanceOf(OtpDto::class)
        ->and($cachedOtp->code)->toBe($result->code)
        ->and($cachedOtp->trackingCode)->toBe($result->trackingCode);
});

test('it enforces waiting time between otp requests', function () {
    $this->otpService->send($this->identifier, $this->guard, $this->otpType);

    expect(fn () => $this->otpService->sendAndRetryCheck($this->identifier, $this->guard, $this->otpType))
        ->toThrow(ValidationException::class);
});

test('it allows new otp request after waiting time', function () {
    $this->otpService->send($this->identifier, $this->guard, $this->otpType);

    // Move time forward past waiting period
    $waitingTime = config('otp.waiting_time');
    $this->travel($waitingTime + 1)->seconds();

    $result = $this->otpService->sendAndRetryCheck($this->identifier, $this->guard, $this->otpType);

    expect($result)->toBeInstanceOf(SentOtpDto::class);
});

test('it verifies correct otp successfully', function () {
    $sent = $this->otpService->send($this->identifier, $this->guard, $this->otpType);

    $result = $this->otpService->verify(
        $this->identifier,
        $this->guard,
        $sent->code,
        $sent->trackingCode,
        $this->otpType
    );

    expect($result)->toBeTrue();
});

test('it fails verification with incorrect otp', function () {
    $sent = $this->otpService->send($this->identifier, $this->guard, $this->otpType);

    $result = $this->otpService->verify(
        $this->identifier,
        $this->guard,
        99999, // incorrect code
        $sent->trackingCode,
        $this->otpType
    );

    expect($result)->toBeFalse();
});

test('it fails verification with incorrect tracking code', function () {
    $sent = $this->otpService->send($this->identifier, $this->guard, $this->otpType);

    $result = $this->otpService->verify(
        $this->identifier,
        $this->guard,
        $sent->code,
        'wrong-tracking-code',
        $this->otpType
    );

    expect($result)->toBeFalse();
});

test('it blocks verification after max attempts', function () {
    $sent = $this->otpService->send($this->identifier, $this->guard, $this->otpType);
    $maxAttempts = config('otp.max_verify_attempts', 3);

    // Exhaust all attempts
    for ($i = 0; $i < $maxAttempts; $i++) {
        $this->otpService->verify(
            $this->identifier,
            $this->guard,
            99999, // wrong code
            $sent->trackingCode,
            $this->otpType
        );
    }

    // Next attempt should throw exception
    expect(fn () => $this->otpService->verify(
        $this->identifier,
        $this->guard,
        99999,
        $sent->trackingCode,
        $this->otpType
    ))->toThrow(ValidationException::class);
});

test('it deletes otp after successful verification', function () {
    $sent = $this->otpService->send($this->identifier, $this->guard, $this->otpType);

    $this->otpService->verify(
        $this->identifier,
        $this->guard,
        $sent->code,
        $sent->trackingCode,
        $this->otpType
    );

    $cachedOtp = $this->otpService->getVerifyCode($this->identifier, $this->guard, $this->otpType);
    expect($cachedOtp)->toBeNull();
});

test('it handles empty identifier correctly', function () {
    $sentAt = $this->otpService->getSentAt('', $this->guard, $this->otpType);
    $hasBeenSent = $this->otpService->isVerifyCodeHasBeenSent('', $this->guard, $this->otpType);

    expect($sentAt)->toBeNull()
        ->and($hasBeenSent)->toBeFalse();
});

test('it returns correct sent at time', function () {
    $now = time();
    $this->otpService->send($this->identifier, $this->guard, $this->otpType);

    $sentAt = $this->otpService->getSentAt($this->identifier, $this->guard, $this->otpType);

    expect($sentAt)
        ->toBeInstanceOf(Carbon::class)
        ->and($sentAt->timestamp)->toBeBetween($now - 1, $now + 1);
});
