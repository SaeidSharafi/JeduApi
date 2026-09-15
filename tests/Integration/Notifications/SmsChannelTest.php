<?php

declare(strict_types=1);

use App\Enums\Sms\SmsNotificationOptionEnum;
use App\Enums\System\OtpType;
use App\Enums\System\SettingKeyEnum;
use App\Events\OtpPrepared;
use App\Models\Setting;
use App\Models\SmsLog;
use App\Models\User;
use App\Notifications\Auth\OtpSmsNotification;
use App\Notifications\SmsChannel;
use App\Notifications\SmsMessage;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

covers(SmsChannel::class, SmsNotificationOptionEnum::class);

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
            'sms.gateways.ippanel.enabled'                    => true,
            'sms.gateways.ippanel.api_key'                    => 'test-api-key',
            'sms.gateways.ippanel.from'                       => '1000',
            'sms.gateways.ippanel.sandbox'                    => false,
            'sms.notifications.otp.enabled'                   => true,
            'sms.notifications.otp.pattern_code'              => 'otp-pattern',
            'sms.notifications.refund_completed.enabled'      => true,
            'sms.notifications.refund_completed.pattern_code' => '',
        ]);
    });

    it('sends the login code with the configured option pattern and creates a log', function (): void {
        Http::fake([
            'api2.ippanel.com/*' => Http::response([
                'data' => ['message_id' => 'fake-message-id'],
            ], 200),
        ]);

        $this->user->notify($this->notification);

        Http::assertSent(function (Request $request): bool {
            return $request->url()              === 'https://api2.ippanel.com/api/v1/sms/pattern/normal/send'
                && $request->data()['code']     === 'otp-pattern'
                && $request->data()['variable'] === ['code' => '123456'];
        });

        expect(SmsLog::count())->toBe(1);

        $smsLog = SmsLog::latest()->first();
        expect($smsLog)->not->toBeNull()
            ->and($smsLog->status)->toBe(200)
            ->and($smsLog->to)->toBe($this->user->phone)
            ->and($smsLog->from)->toBe('1000')
            ->and($smsLog->type)->toBe('OTP')
            ->and($smsLog->data['data']['message_id'])->toBe('fake-message-id');
    });

    it('uses the stored notification option over the configuration default', function (): void {
        Setting::factory()->create([
            'key'   => SettingKeyEnum::SMS_NOTIFICATIONS->value,
            'value' => ['otp' => ['enabled' => true, 'pattern_code' => 'stored-otp-pattern']],
            'type'  => 'json',
            'group' => 'sms',
        ]);
        Http::fake(['api2.ippanel.com/*' => Http::response([], 200)]);

        $this->user->notify($this->notification);

        Http::assertSent(fn (Request $request): bool => $request->data()['code'] === 'stored-otp-pattern');
    });

    it('does not send and records a skip when the notification option is disabled', function (): void {
        config(['sms.notifications.otp.enabled' => false]);
        Http::fake(['api2.ippanel.com/*' => Http::response([], 200)]);

        $this->user->notify($this->notification);

        Http::assertNothingSent();

        expect(SmsLog::count())->toBe(1);

        $smsLog = SmsLog::latest()->first();
        expect($smsLog)->not->toBeNull()
            ->and($smsLog->status)->toBe(SmsLog::STATUS_SKIPPED)
            ->and($smsLog->data)->toBe(['reason' => 'option_disabled'])
            ->and($smsLog->type)->toBe('OTP')
            ->and($smsLog->to)->toBe($this->user->phone);
    });

    it('does not send and records a skip when an option that needs a pattern has none', function (): void {
        config(['sms.notifications.otp.pattern_code' => '']);
        Http::fake(['api2.ippanel.com/*' => Http::response([], 200)]);

        $this->user->notify($this->notification);

        Http::assertNothingSent();

        expect(SmsLog::count())->toBe(1);

        $smsLog = SmsLog::latest()->first();
        expect($smsLog)->not->toBeNull()
            ->and($smsLog->status)->toBe(SmsLog::STATUS_SKIPPED)
            ->and($smsLog->data)->toBe(['reason' => 'pattern_missing'])
            ->and($smsLog->type)->toBe('OTP');
    });

    it('sends the refund as free text when no pattern is configured', function (): void {
        Http::fake(['api2.ippanel.com/*' => Http::response([], 200)]);

        $this->user->notify(smsNotificationOfType('REFUND'));

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api2.ippanel.com/api/v1/sms/send/webservice/single'
            && $request->data()['message']                              === 'Refund done');

        expect(SmsLog::count())->toBe(1);

        $smsLog = SmsLog::latest()->first();
        expect($smsLog->type)->toBe('REFUND')
            ->and($smsLog->content)->toBe('Refund done');
    });

    it('sends the refund through its configured pattern', function (): void {
        config(['sms.notifications.refund_completed.pattern_code' => 'refund-pattern']);
        Http::fake(['api2.ippanel.com/*' => Http::response([], 200)]);

        $this->user->notify(smsNotificationOfType('REFUND'));

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api2.ippanel.com/api/v1/sms/pattern/normal/send'
            && $request->data()['code']                                 === 'refund-pattern'
            && $request->data()['variable']                             === ['order_id' => 7, 'amount' => 250000]);

        expect(SmsLog::count())->toBe(1);
    });

    it('sends the configured pattern for a message that carries no free text', function (): void {
        config(['sms.notifications.refund_completed.pattern_code' => 'refund-pattern']);
        Http::fake(['api2.ippanel.com/*' => Http::response([], 200)]);

        $this->user->notify(smsNotificationOfType('REFUND', null));

        Http::assertSent(fn (Request $request): bool => $request->data()['code'] === 'refund-pattern');

        expect(SmsLog::count())->toBe(1);

        $smsLog = SmsLog::latest()->first();
        expect($smsLog->content)->toBe('')
            ->and($smsLog->type)->toBe('REFUND');
    });

    it('records a skip when an enabled option has neither a pattern nor free text', function (): void {
        Http::fake(['api2.ippanel.com/*' => Http::response([], 200)]);

        $this->user->notify(smsNotificationOfType('REFUND', null));

        Http::assertNothingSent();

        expect(SmsLog::count())->toBe(1);

        $smsLog = SmsLog::latest()->first();
        expect($smsLog->status)->toBe(SmsLog::STATUS_SKIPPED)
            ->and($smsLog->data)->toBe(['reason' => 'empty_message'])
            ->and($smsLog->type)->toBe('REFUND');
    });

    it('does not send and records a skip when the refund option is disabled', function (): void {
        config(['sms.notifications.refund_completed.enabled' => false]);
        Http::fake(['api2.ippanel.com/*' => Http::response([], 200)]);

        $this->user->notify(smsNotificationOfType('REFUND'));

        Http::assertNothingSent();

        expect(SmsLog::count())->toBe(1)
            ->and(SmsLog::latest()->first()->data)->toBe(['reason' => 'option_disabled']);
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
        config(['sms.gateways.ippanel.sandbox' => true]);
        Http::fake(['api2.ippanel.com/*' => Http::response([], 200)]);

        $this->user->notify($this->notification);

        Http::assertNothingSent();

        $smsLog = SmsLog::latest()->first();
        expect($smsLog)->not->toBeNull()
            ->and($smsLog->status)->toBe(200)
            ->and($smsLog->to)->toBe($this->user->phone)
            ->and($smsLog->data['message_id'])->toStartWith('Sandbox_');
    });

    it('does not attempt the login code and records it when the gateway has no credentials', function (): void {
        config([
            'sms.gateways.ippanel.api_key' => null,
            'sms.gateways.ippanel.from'    => '',
        ]);
        Http::fake(['api2.ippanel.com/*' => Http::response([], 200)]);

        $this->user->notify($this->notification);

        Http::assertNothingSent();

        $smsLog = SmsLog::latest()->first();
        expect($smsLog)->not->toBeNull()
            ->and($smsLog->status)->toBe(SmsLog::STATUS_SKIPPED)
            ->and($smsLog->data)->toBe(['reason' => 'not_configured']);
    });

    it('blocks the login code and records a skipped attempt when the gateway is switched off', function (): void {
        config(['sms.gateways.ippanel.enabled' => false]);
        Http::fake(['api2.ippanel.com/*' => Http::response([], 200)]);

        $this->user->notify($this->notification);

        Http::assertNothingSent();

        $smsLog = SmsLog::latest()->first();
        expect($smsLog)->not->toBeNull()
            ->and($smsLog->status)->toBe(SmsLog::STATUS_SKIPPED)
            ->and($smsLog->data)->toBe(['reason' => 'gateway_disabled'])
            ->and($smsLog->type)->toBe('OTP')
            ->and($smsLog->to)->toBe($this->user->phone);
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
            ['notification' => $badNotification::class]
        );
        Http::fake();

        $this->user->notify($badNotification);

        Http::assertNothingSent();
    });

    it('sends a standard content-based SMS correctly for a type with no option', function (): void {

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
            return $request->url()               === 'https://api2.ippanel.com/api/v1/sms/send/webservice/single'
                && $request->data()['message']   === 'Hello world'
                && $request->data()['recipient'] === [$this->user->phone];
        });

        $smsLog = SmsLog::latest()->first();
        expect(SmsLog::count())->toBe(1)
            ->and($smsLog->type)->toBe('GREETING')
            ->and($smsLog->to)->toBe([$this->user->phone]);
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
        expect(SmsLog::count())->toBe(1)
            ->and($smsLog)->not->toBeNull()
            ->and($smsLog->status)->toBe(200)
            ->and($smsLog->to)->toBe($staff->phone) // Check the Staff phone number
            ->and($smsLog->type)->toBe('OTP')
            ->and($smsLog->data['data']['message_id'])->toBe('staff-message-id');
    });
});

/**
 * A minimal notification carrying an arbitrary `SmsMessage`, used to exercise
 * the channel's option branches without depending on a domain notification.
 *
 * @param  array<string, mixed>  $parameters
 */
function smsNotificationOfType(string $type, ?string $content = 'Refund done', array $parameters = ['order_id' => 7, 'amount' => 250000]): Illuminate\Notifications\Notification
{
    return new class($type, $content, $parameters) extends Illuminate\Notifications\Notification
    {
        /**
         * @param  array<string, mixed>  $parameters
         */
        public function __construct(
            private readonly string $type,
            private readonly ?string $content,
            private readonly array $parameters,
        ) {}

        public function via($notifiable): string
        {
            return SmsChannel::class;
        }

        public function toSms($notifiable): SmsMessage
        {
            $message = (new SmsMessage)
                ->parameters($this->parameters)
                ->type($this->type);

            if ($this->content !== null) {
                $message->content($this->content);
            }

            return $message;
        }
    };
}
