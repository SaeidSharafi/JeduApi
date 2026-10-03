<?php

declare(strict_types=1);

use App\Contracts\Cache\CacheStore;
use App\Contracts\OtpGeneratorInterface;
use App\Data\OtpManager\OtpDto;
use App\Data\OtpManager\SentOtpDto;
use App\Enums\System\CacheKey;
use App\Enums\System\OtpType;
use App\Events\OtpPrepared;
use App\Services\OtpManagerService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Tests\Support\Fakes\FakeOtpGenerator;

covers(OtpManagerService::class);

describe('OtpManagerService', function (): void {
    beforeEach(function (): void {
        Cache::flush();
        Event::fake();
        config()->set('otp.lock_seconds', 5);
        config()->set('otp.lock_block_seconds', 1);
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
        $this->cache   = app(CacheStore::class);

        // Common test data
        $this->identifier = '09123456789';
        $this->guard      = 'user';
        $this->otpType    = OtpType::SIGNIN;
        $this->params     = ['foo' => 'bar'];
        $this->otpParams  = [
            'identifier' => $this->identifier,
            'guard'      => $this->guard,
            'type'       => $this->otpType->identifier(),
        ];
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
        Event::assertDispatched(OtpPrepared::class);
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
        $this->travel((int) config('otp.waiting_time') + 1)->seconds();
        $result = $this->service->sendAndRetryCheck($identifier, $guard, $otpType);
        expect($result)->toBeInstanceOf(SentOtpDto::class);
    });

    it('sends when no code has been issued yet', function (): void {
        $result = $this->service->sendAndRetryCheck($this->identifier, $this->guard, $this->otpType);

        expect($result)->toBeInstanceOf(SentOtpDto::class);
    });

    it('verifies correct OTP and tracking code', function (): void {

        $identifier = '09123456789';
        $guard      = 'user';
        $otpType    = OtpType::SIGNIN;
        $sentOtp    = $this->service->send($identifier, $guard, $otpType);
        $result     = $this->service->verify($identifier, $guard, $this->expectedOtpCode, $this->expectedTrackingCode, $otpType);
        expect($result)->toBeTrue()
            ->and($this->service->getVerifyCode($identifier, $guard, $otpType))->toBeNull();
    });

    it('supports keys without an otp type', function (): void {
        $this->cache->put(
            CacheKey::OtpValue,
            ['identifier' => $this->identifier, 'guard' => $this->guard, 'type' => null],
            new OtpDto($this->expectedOtpCode, $this->expectedTrackingCode),
        );

        expect($this->service->verify($this->identifier, $this->guard, $this->expectedOtpCode, $this->expectedTrackingCode, null))->toBeTrue();
    });

    it('fails verification with wrong code or tracking code', function (): void {

        $identifier = '09123456789';
        $guard      = 'user';
        $otpType    = OtpType::SIGNIN;
        $sentOtp    = $this->service->send($identifier, $guard, $otpType);
        expect($this->service->verify($identifier, $guard, 999999, $this->expectedTrackingCode, $otpType))->toBeFalse();
        expect($this->service->verify($identifier, $guard, $this->expectedOtpCode, 'wrong-track', $otpType))->toBeFalse();
    });

    it('fails verification when no code was ever issued', function (): void {
        $this->cache->put(CacheKey::OtpAttempts, $this->otpParams, 2);

        try {
            $this->service->verify($this->identifier, $this->guard, $this->expectedOtpCode, $this->expectedTrackingCode, $this->otpType);
            $this->fail('Verification should have thrown for a missing code.');
        } catch (ValidationException $exception) {
            expect($exception->errors())->toBe(['otp' => [__('messages.auth.otp.expired_code')]]);
        }

        expect($this->cache->get(CacheKey::OtpAttempts, $this->otpParams))->toBeNull();
    });

    it('does not lock out at exactly the allowed number of failed attempts', function (): void {
        $this->service->send($this->identifier, $this->guard, $this->otpType);
        $this->cache->put(CacheKey::OtpAttempts, $this->otpParams, (int) config('otp.max_verify_attempts', 5) - 1);

        $result = $this->service->verify($this->identifier, $this->guard, 999999, $this->expectedTrackingCode, $this->otpType);

        expect($result)->toBeFalse()
            ->and($this->cache->get(CacheKey::OtpAttempts, $this->otpParams))->toBe((int) config('otp.max_verify_attempts', 5));
    });

    it('resets attempts after successful verification', function (): void {

        $identifier = '09123456789';
        $guard      = 'user';
        $otpType    = OtpType::SIGNIN;
        $sentOtp    = $this->service->send($identifier, $guard, $otpType);
        // Simulate failed attempts
        $this->cache->put(CacheKey::OtpAttempts, $this->otpParams, 2);
        $this->service->verify($identifier, $guard, $this->expectedOtpCode, $this->expectedTrackingCode, $otpType);
        expect($this->cache->get(CacheKey::OtpAttempts, $this->otpParams))->toBeNull();
    });

    it('deletes OTP after max failed attempts and throws ValidationException', function (): void {

        $identifier = '09123456789';
        $guard      = 'user';
        $otpType    = OtpType::SIGNIN;
        $sentOtp    = $this->service->send($identifier, $guard, $otpType);
        $this->cache->put(CacheKey::OtpAttempts, $this->otpParams, config('otp.max_verify_attempts', 3));

        try {
            $this->service->verify($identifier, $guard, 999999, $this->expectedTrackingCode, $otpType);
            $this->fail('Verification should have thrown for exceeding the attempt limit.');
        } catch (ValidationException $exception) {
            expect($exception->errors())->toBe([
                'otp' => [__('messages.auth.otp.throttle', ['seconds' => CacheKey::OtpAttempts->ttl()])],
            ]);
        }

        expect($this->service->getVerifyCode($identifier, $guard, $otpType))->toBeNull()
            ->and($this->cache->get(CacheKey::OtpAttempts, $this->otpParams))->toBeNull();
    });

    it('fails verification with expired otp', function (): void {
        config()->set('otp.ttl_seconds', 60);

        $identifier = '09123456789';
        $guard      = 'user';
        $otpType    = OtpType::SIGNIN;

        $this->service->send($identifier, $guard, $otpType);

        $this->travel(61)->seconds();

        expect(fn () => $this->service->verify($identifier, $guard, $this->expectedOtpCode, $this->expectedTrackingCode, $otpType))
            ->toThrow(ValidationException::class);

        expect($this->service->getVerifyCode($identifier, $guard, $otpType))->toBeNull();
    });

    it('fails verification when the stored code outlives its send marker', function (): void {
        // The value is still readable; only the marker says the code is too old,
        // which is the second expiry branch rather than the missing-code one.
        putCachedOtp(
            $this->identifier,
            $this->guard,
            $this->otpType,
            $this->expectedOtpCode,
            $this->expectedTrackingCode,
            now()->subDay()->timestamp,
        );
        $this->cache->put(CacheKey::OtpAttempts, $this->otpParams, 2);

        try {
            $this->service->verify($this->identifier, $this->guard, $this->expectedOtpCode, $this->expectedTrackingCode, $this->otpType);
            $this->fail('Verification should have thrown for an expired code.');
        } catch (ValidationException $exception) {
            expect($exception->errors())->toBe(['otp' => [__('messages.auth.otp.expired_code')]]);
        }

        expect($this->service->getVerifyCode($this->identifier, $this->guard, $this->otpType))->toBeNull()
            ->and($this->cache->get(CacheKey::OtpAttempts, $this->otpParams))->toBeNull();
    });

    it('keeps created marker after successful verification code deletion for cooldown semantics', function (): void {
        $identifier = '09123456789';
        $guard      = 'user';
        $otpType    = OtpType::SIGNIN;

        $this->service->send($identifier, $guard, $otpType);
        $this->service->deleteVerifyCode($identifier, $guard, $otpType);

        expect($this->service->getSentAt($identifier, $guard, $otpType))->toBeInstanceOf(Carbon::class);
    });

    it('counts every failed verification attempt', function (): void {
        $identifier = '09123456789';
        $guard      = 'user';
        $otpType    = OtpType::SIGNIN;

        $this->service->send($identifier, $guard, $otpType);

        $this->service->verify($identifier, $guard, 999999, $this->expectedTrackingCode, $otpType);

        expect($this->cache->get(CacheKey::OtpAttempts, $this->otpParams))->toBe(1);
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
