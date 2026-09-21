<?php

declare(strict_types=1);

use App\Contracts\OtpGeneratorInterface;
use App\Data\OtpManager\OtpDto;
use App\Enums\System\OtpType;
use App\Models\Staff;
use App\Models\User;
use App\Services\OtpManagerService;
use Database\Factories\StaffFactory;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\Notification;
use Tests\Support\Fakes\FakeOtpGenerator;

covers(UserFactory::class, StaffFactory::class);

beforeEach(function (): void {
    // The OtpPrepared listener would deliver the code; only the stored value is under test.
    Notification::fake();

    /** @var FakeOtpGenerator $generator */
    $generator = app(OtpGeneratorInterface::class);
    $generator->setNextTrackingCode('factory-tracking');
});

it('seeds a user otp the otp service can read', function (): void {
    $user = User::factory()->withOtp(1234)->create();

    $otp = app(OtpManagerService::class)->getVerifyCode($user->phone, 'user', OtpType::SIGNIN);

    expect($otp)->toBeInstanceOf(OtpDto::class)
        ->and($otp->code)->toBe(1234)
        ->and($otp->trackingCode)->toBe('factory-tracking');
});

it('seeds a staff otp the otp service can read', function (): void {
    $staff = Staff::factory()->withOtp(1234)->create();

    $otp = app(OtpManagerService::class)->getVerifyCode($staff->phone, 'staff', OtpType::SIGNIN);

    expect($otp)->toBeInstanceOf(OtpDto::class)
        ->and($otp->code)->toBe(1234)
        ->and($otp->trackingCode)->toBe('factory-tracking');
});
