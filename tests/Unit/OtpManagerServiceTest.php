<?php

declare(strict_types=1);

use App\Services\OtpManagerService;
use App\Dto\OtpManager\OtpDto;
use App\Dto\OtpManager\SentOtpDto;
use App\Enums\OtpType;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Carbon;

describe('OtpManagerService', function () {
    beforeEach(function () {
        Cache::flush();
        Event::fake();
    });

    it('generates and sends OTP, triggers event, and returns SentOtpDto', function () {
        $service = new OtpManagerService();
        $identifier = '09123456789';
        $guard = 'user';
        $otpType = OtpType::SIGNIN;
        $params = ['foo' => 'bar'];

        $sentOtp = $service->send($identifier, $guard, $otpType, $params);
        expect($sentOtp)->toBeInstanceOf(SentOtpDto::class);
        expect($sentOtp->trackingCode)->not->toBeEmpty();
        expect($sentOtp->code)->toBeInt();
        Event::assertDispatched(App\Events\OtpPrepared::class);
    });

    it('prevents resend within waiting time and throws ValidationException', function () {
        $service = new OtpManagerService();
        $identifier = '09123456789';
        $guard = 'user';
        $otpType = OtpType::SIGNIN;
        $service->send($identifier, $guard, $otpType);
        // Simulate just sent (no time passed)
        expect(fn() => $service->sendAndRetryCheck($identifier, $guard, $otpType))->toThrow(ValidationException::class);
    });

    it('allows resend after waiting time', function () {
        $service = new OtpManagerService();
        $identifier = '09123456789';
        $guard = 'user';
        $otpType = OtpType::SIGNIN;
        $service->send($identifier, $guard, $otpType);
        // Simulate time passed
        $createdKey = (new ReflectionClass($service))->getMethod('getCacheKey')->invoke($service, $identifier, $guard, 'created');
        Cache::put($createdKey, Carbon::now()->subSeconds(999999)->timestamp);
        $result = $service->sendAndRetryCheck($identifier, $guard, $otpType);
        expect($result)->toBeInstanceOf(SentOtpDto::class);
    });

    it('verifies correct OTP and tracking code', function () {
        $service = new OtpManagerService();
        $identifier = '09123456789';
        $guard = 'user';
        $otpType = OtpType::SIGNIN;
        $sentOtp = $service->send($identifier, $guard, $otpType);
        $result = $service->verify($identifier, $guard, $sentOtp->code, $sentOtp->trackingCode, $otpType);
        expect($result)->toBeTrue();
    });

    it('fails verification with wrong code or tracking code', function () {
        $service = new OtpManagerService();
        $identifier = '09123456789';
        $guard = 'user';
        $otpType = OtpType::SIGNIN;
        $sentOtp = $service->send($identifier, $guard, $otpType);
        expect($service->verify($identifier, $guard, 999999, $sentOtp->trackingCode, $otpType))->toBeFalse();
        expect($service->verify($identifier, $guard, $sentOtp->code, 'wrong-track', $otpType))->toBeFalse();
    });

    it('resets attempts after successful verification', function () {
        $service = new OtpManagerService();
        $identifier = '09123456789';
        $guard = 'user';
        $otpType = OtpType::SIGNIN;
        $sentOtp = $service->send($identifier, $guard, $otpType);
        // Simulate failed attempts
        $attemptsKey = (new ReflectionClass($service))->getMethod('getCacheKey')->invoke($service, $identifier, $guard, 'verify_attempts');
        Cache::put($attemptsKey, 2);
        $service->verify($identifier, $guard, $sentOtp->code, $sentOtp->trackingCode, $otpType);
        expect(Cache::get($attemptsKey))->toBeNull();
    });

    it('deletes OTP after max failed attempts and throws ValidationException', function () {
        $service = new OtpManagerService();
        $identifier = '09123456789';
        $guard = 'user';
        $otpType = OtpType::SIGNIN;
        $sentOtp = $service->send($identifier, $guard, $otpType);
        $attemptsKey = (new ReflectionClass($service))->getMethod('getCacheKey')->invoke($service, $identifier, $guard, 'verify_attempts');
        Cache::put($attemptsKey, config('otp.max_verify_attempts', 3));
        expect(fn() => $service->verify($identifier, $guard, 999999, $sentOtp->trackingCode, $otpType))->toThrow(ValidationException::class);
        expect($service->getVerifyCode($identifier, $guard, $otpType))->toBeNull();
    });

    it('getVerifyCode and deleteVerifyCode work as expected', function () {
        $service = new OtpManagerService();
        $identifier = '09123456789';
        $guard = 'user';
        $otpType = OtpType::SIGNIN;
        $sentOtp = $service->send($identifier, $guard, $otpType);
        $otpDto = $service->getVerifyCode($identifier, $guard, $otpType);
        expect($otpDto)->toBeInstanceOf(OtpDto::class);
        $deleted = $service->deleteVerifyCode($identifier, $guard, $otpType);
        expect($deleted)->toBeTrue();
        expect($service->getVerifyCode($identifier, $guard, $otpType))->toBeNull();
    });

    it('getSentAt returns correct Carbon instance or null', function () {
        $service = new OtpManagerService();
        $identifier = '09123456789';
        $guard = 'user';
        $otpType = OtpType::SIGNIN;
        expect($service->getSentAt($identifier, $guard, $otpType))->toBeNull();
        $service->send($identifier, $guard, $otpType);
        expect($service->getSentAt($identifier, $guard, $otpType))->toBeInstanceOf(Carbon::class);
    });

    it('isVerifyCodeHasBeenSent returns true/false as expected', function () {
        $service = new OtpManagerService();
        $identifier = '09123456789';
        $guard = 'user';
        $otpType = OtpType::SIGNIN;
        expect($service->isVerifyCodeHasBeenSent($identifier, $guard, $otpType))->toBeFalse();
        $service->send($identifier, $guard, $otpType);
        expect($service->isVerifyCodeHasBeenSent($identifier, $guard, $otpType))->toBeTrue();
    });
});
