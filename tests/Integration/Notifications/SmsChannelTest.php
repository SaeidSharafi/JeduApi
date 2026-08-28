<?php

declare(strict_types=1);

use App\Enums\System\OtpType;
use App\Events\OtpPrepared;
use App\Models\SmsLog;
use App\Models\User;
use App\Notifications\Auth\OtpSmsNotification;
use App\Notifications\SmsChannel;
use App\Notifications\SmsMessage;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

describe('SmsChannel Sending Logic', function (): void {
    beforeEach(function (): void {
        $this->user = User::factory()->create(['phone' => '09123456789']);

        $this->otpEvent = new OtpPrepared(
            identifier: '09321456987',
            guard: 'user',
            code: '123456',
            type: OtpType::SIGNIN,
            trackingCode: 'test-tracking',
            params: []
        );
        $this->notification = new OtpSmsNotification($this->otpEvent);

        config([
            'services.ippanel.api_key'  => 'test-api-key',
            'services.ippanel.from'     => '1000',
            'services.ippanel.sand_box' => false,
        ]);
    });
    it('sends a pattern SMS successfully and creates a log', function (): void {
        Http::fake([
            'api2.ippanel.com/*' => Http::response([
                'data' => ['message_id' => 'fake-message-id'],
            ], 200),
        ]);

        $this->user->notify($this->notification);

        $smsLog = SmsLog::latest()->first();
        expect($smsLog)->not->toBeNull()
            ->and($smsLog->status)->toBe(200)
            ->and($smsLog->to)->toBe($this->user->phone)
            ->and($smsLog->from)->toBe('1000')
            ->and($smsLog->type)->toBe('OTP')
            ->and($smsLog->data['data']['message_id'])->toBe('fake-message-id');
    });

    it('throws exception and logs on a 401 client error', function (): void {

        Http::fake([
            'api2.ippanel.com/*' => Http::response(['message' => 'Unauthorized'], 401),
        ]);
        Log::shouldReceive('error')->once();

        expect(fn () => $this->user->notify($this->notification))
            ->toThrow(RequestException::class);

        $smsLog = SmsLog::latest()->first();
        expect($smsLog)->not->toBeNull()
            ->and($smsLog->status)->toBe(401);
    });

    it('throws exception and logs on a 422 validation error', function (): void {
        $errorResponse = ['errors' => ['recipient' => 'is invalid']];
        Http::fake([
            'api2.ippanel.com/*' => Http::response($errorResponse, 422),
        ]);
        Log::shouldReceive('error')->once()->with(
            'SMS sending failed',
            Mockery::on(function (array $data) use ($errorResponse): bool {
                return $data['status'] === 422 && $data['message'] === $errorResponse;
            })
        );

        expect(fn () => $this->user->notify($this->notification))
            ->toThrow(RequestException::class);

        $smsLog = SmsLog::latest()->first();
        expect($smsLog)->not->toBeNull()
            ->and($smsLog->status)->toBe(422)
            ->and($smsLog->data)->toBe($errorResponse);
    });

    it('throws exception and logs on a 500 server error', function (): void {
        Http::fake([
            'api2.ippanel.com/*' => Http::response(null, 500),
        ]);
        Log::shouldReceive('error')->once();

        expect(fn () => $this->user->notify($this->notification))
            ->toThrow(RequestException::class);

        $smsLog = SmsLog::latest()->first();
        expect($smsLog)->not->toBeNull()
            ->and($smsLog->status)->toBe(500);
    });

    it('does not send http request and logs in sandbox mode', function (): void {
        config(['services.ippanel.sand_box' => true]);
        Http::fake();

        $this->user->notify($this->notification);

        Http::assertNothingSent();

        $smsLog = SmsLog::latest()->first();
        expect($smsLog)->not->toBeNull()
            ->and($smsLog->status)->toBe(200)
            ->and($smsLog->to)->toBe($this->user->phone)
            ->and($smsLog->data['message_id'])->toStartWith('Sandbox_');
    });

    it('throws exception if api key or from is not configured', function (): void {
        config([
            'services.ippanel.api_key' => null,
            'services.ippanel.from'    => null,
        ]);

        expect(fn () => $this->user->notify($this->notification))
            ->toThrow(Exception::class, 'IPPanel API key or sender number is not configured.');
    });
    it('does not send if notifiable does not have a route for sms', function (): void {
        $userWithoutPhone = User::factory()->create(['phone' => '']);

        $notification = new OtpSmsNotification(new OtpPrepared(
            identifier: '09321456987',
            guard: 'user',
            code: '123456',
            type: OtpType::SIGNIN,
            trackingCode: 'test-tracking',
            params: []
        ));

        Http::fake();
        $initialLogCount = SmsLog::count();

        $userWithoutPhone->notify($notification);

        Http::assertNothingSent();

        expect(SmsLog::count())->toBe($initialLogCount);
    });

    it('logs an error if notification returns an invalid message type', function (): void {

        $badNotification = new class extends Illuminate\Notifications\Notification
        {
            public function via($notifiable): string
            {
                return SmsChannel::class;
            }

            public function toSms($notifiable): string
            {
                return 'this is not a valid message object';
            }
        };

        Log::shouldReceive('error')->once()->with(
            'Notification did not return an SmsMessage object.',
            Mockery::any()
        );
        Http::fake();

        $this->user->notify($badNotification);

        Http::assertNothingSent();
    });

    it('sends a standard content-based SMS correctly', function (): void {

        $standardSmsNotification = new class extends Illuminate\Notifications\Notification
        {
            public function via($notifiable): string
            {
                return SmsChannel::class;
            }

            public function toSms($notifiable): SmsMessage
            {
                return (new SmsMessage)
                    ->content('Hello world')
                    ->type('GREETING');
            }
        };

        Http::fake([
            'api2.ippanel.com/api/v1/sms/send/webservice/single' => Http::response([], 200),
        ]);

        $this->user->notify($standardSmsNotification);

        Http::assertSent(function (Request $request): bool {
            return $request->url()             === 'https://api2.ippanel.com/api/v1/sms/send/webservice/single'
                && $request->data()['message'] === 'Hello world';
        });

        $smsLog = SmsLog::latest()->first();
        expect($smsLog->type)->toBe('GREETING');
    });

    it('sends a pattern SMS successfully to a Staff member and creates a log', function (): void {
        $staff = App\Models\Staff::factory()->create(['phone' => '09876543210']);

        Http::fake([
            'api2.ippanel.com/*' => Http::response(['data' => ['message_id' => 'staff-message-id']], 200),
        ]);

        $staff->notify($this->notification);

        Http::assertSent(function (Request $request) use ($staff): bool {
            return $request['recipient'] === $staff->phone;
        });

        $smsLog = SmsLog::latest()->first();
        expect($smsLog)->not->toBeNull()
            ->and($smsLog->status)->toBe(200)
            ->and($smsLog->to)->toBe($staff->phone) // Check the Staff phone number
            ->and($smsLog->type)->toBe('OTP')
            ->and($smsLog->data['data']['message_id'])->toBe('staff-message-id');
    });
});
