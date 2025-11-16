<?php

declare(strict_types=1);

use App\Contracts\OtpGeneratorInterface;
use App\Data\OtpManager\OtpDto;
use App\Data\OtpManager\SentOtpDto;
use App\Enums\System\OtpType;
use App\Services\OtpManagerService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Tests\Support\Fakes\FakeOtpGenerator;

describe('OtpManagerService', function (): void {
    beforeEach(function (): void {
        Cache::flush();
        Event::fake();
        $this->expectedOtpCode      = 123456;
        $this->expectedTrackingCode = 'test-tracking';
        // Configure the FakeOtpGenerator
        /** @var FakeOtpGenerator $fakeGenerator */
        $fakeGenerator = app(OtpGeneratorInterface::class);
        expect($fakeGenerator)->toBeInstanceOf(FakeOtpGenerator::class); // Sanity check
        $fakeGenerator->setNextOtpCode($this->expectedOtpCode)
            ->setNextTrackingCode($this->expectedTrackingCode);

        // Instantiate OtpManagerService via the app container
        // This ensures it gets the FakeOtpGenerator injected.
        $this->service = app(OtpManagerService::class); // << USE APP CONTAINER

        // Common test data
        $this->identifier = '09123456789';
        $this->guard      = 'user';
        $this->otpType    = OtpType::SIGNIN;
        $this->params     = ['foo' => 'bar'];
    });

    it('generates and sends OTP, triggers event, and returns SentOtpDto', function (): void {

        $identifier = '09123456789';
        $guard      = 'user';
        $otpType    = OtpType::SIGNIN;
        $params     = ['foo' => 'bar'];

        $sentOtp = $this->service->send($identifier, $guard, $otpType, $params);
        expect($sentOtp)->toBeInstanceOf(SentOtpDto::class);
        expect($this->expectedTrackingCode)->not->toBeEmpty();
        expect($this->expectedOtpCode)->toBeInt();
        Event::assertDispatched(App\Events\OtpPrepared::class);
    });

    it('prevents resend within waiting time and throws ValidationException', function (): void {

        $identifier = '09123456789';
        $guard      = 'user';
        $otpType    = OtpType::SIGNIN;
        $this->service->send($identifier, $guard, $otpType);
        // Simulate just sent (no time passed)
        expect(fn () => $this->service->sendAndRetryCheck($identifier, $guard, $otpType))->toThrow(ValidationException::class);
    });

    it('allows resend after waiting time', function (): void {

        $identifier = '09123456789';
        $guard      = 'user';
        $otpType    = OtpType::SIGNIN;
        $this->service->send($identifier, $guard, $otpType);
        // Simulate time passed
        $createdKey = (new ReflectionClass($this->service))->getMethod('getCacheKey')->invoke($this->service, $identifier, $guard, 'created');
        Cache::put($createdKey, Carbon::now()->subSeconds(999999)->timestamp);
        $result = $this->service->sendAndRetryCheck($identifier, $guard, $otpType);
        expect($result)->toBeInstanceOf(SentOtpDto::class);
    });

    it('verifies correct OTP and tracking code', function (): void {

        $identifier = '09123456789';
        $guard      = 'user';
        $otpType    = OtpType::SIGNIN;
        $sentOtp    = $this->service->send($identifier, $guard, $otpType);
        $result     = $this->service->verify($identifier, $guard, $this->expectedOtpCode, $this->expectedTrackingCode, $otpType);
        expect($result)->toBeTrue();
    });

    it('fails verification with wrong code or tracking code', function (): void {

        $identifier = '09123456789';
        $guard      = 'user';
        $otpType    = OtpType::SIGNIN;
        $sentOtp    = $this->service->send($identifier, $guard, $otpType);
        expect($this->service->verify($identifier, $guard, 999999, $this->expectedTrackingCode, $otpType))->toBeFalse();
        expect($this->service->verify($identifier, $guard, $this->expectedOtpCode, 'wrong-track', $otpType))->toBeFalse();
    });

    it('resets attempts after successful verification', function (): void {

        $identifier = '09123456789';
        $guard      = 'user';
        $otpType    = OtpType::SIGNIN;
        $sentOtp    = $this->service->send($identifier, $guard, $otpType);
        // Simulate failed attempts
        $attemptsKey = (new ReflectionClass($this->service))->getMethod('getCacheKey')->invoke($this->service, $identifier, $guard, 'verify_attempts');
        Cache::put($attemptsKey, 2);
        $this->service->verify($identifier, $guard, $this->expectedOtpCode, $this->expectedTrackingCode, $otpType);
        expect(Cache::get($attemptsKey))->toBeNull();
    });

    it('deletes OTP after max failed attempts and throws ValidationException', function (): void {

        $identifier  = '09123456789';
        $guard       = 'user';
        $otpType     = OtpType::SIGNIN;
        $sentOtp     = $this->service->send($identifier, $guard, $otpType);
        $attemptsKey = (new ReflectionClass($this->service))->getMethod('getCacheKey')->invoke($this->service, $identifier, $guard, 'verify_attempts');
        Cache::put($attemptsKey, config('otp.max_verify_attempts', 3));
        expect(fn () => $this->service->verify($identifier, $guard, 999999, $this->expectedTrackingCode, $otpType))->toThrow(ValidationException::class);
        expect($this->service->getVerifyCode($identifier, $guard, $otpType))->toBeNull();
    });

    it('getVerifyCode and deleteVerifyCode work as expected', function (): void {

        $identifier = '09123456789';
        $guard      = 'user';
        $otpType    = OtpType::SIGNIN;
        $sentOtp    = $this->service->send($identifier, $guard, $otpType);
        $otpDto     = $this->service->getVerifyCode($identifier, $guard, $otpType);
        expect($otpDto)->toBeInstanceOf(OtpDto::class);
        $deleted = $this->service->deleteVerifyCode($identifier, $guard, $otpType);
        expect($deleted)->toBeTrue();
        expect($this->service->getVerifyCode($identifier, $guard, $otpType))->toBeNull();
    });
    it('getSentAt returns null if idnetifier is empty', function (): void {

        $identifier = '09123456789';
        $guard      = 'user';
        $otpType    = OtpType::SIGNIN;
        expect($this->service->getSentAt('', $guard, $otpType))->toBeNull();
        $this->service->send($identifier, $guard, $otpType);
        expect($this->service->getSentAt('', $guard, $otpType))->toBeNull;
    });

    it('getSentAt returns correct Carbon instance or null', function (): void {

        $identifier = '09123456789';
        $guard      = 'user';
        $otpType    = OtpType::SIGNIN;
        expect($this->service->getSentAt($identifier, $guard, $otpType))->toBeNull();
        $this->service->send($identifier, $guard, $otpType);
        expect($this->service->getSentAt($identifier, $guard, $otpType))->toBeInstanceOf(Carbon::class);
    });
    it('isVerifyCodeHasBeenSent returns false if identifier is mepty', function (): void {

        $identifier = '09123456789';
        $guard      = 'user';
        $otpType    = OtpType::SIGNIN;
        expect($this->service->isVerifyCodeHasBeenSent($identifier, $guard, $otpType))->toBeFalse();
        $this->service->send($identifier, $guard, $otpType);
        expect($this->service->isVerifyCodeHasBeenSent('', $guard, $otpType))->toBeFalse();
    });
    it('isVerifyCodeHasBeenSent returns true/false as expected', function (): void {

        $identifier = '09123456789';
        $guard      = 'user';
        $otpType    = OtpType::SIGNIN;
        expect($this->service->isVerifyCodeHasBeenSent($identifier, $guard, $otpType))->toBeFalse();
        $this->service->send($identifier, $guard, $otpType);
        expect($this->service->isVerifyCodeHasBeenSent($identifier, $guard, $otpType))->toBeTrue();
    });
});
