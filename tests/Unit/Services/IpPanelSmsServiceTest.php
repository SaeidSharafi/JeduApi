<?php

use Illuminate\Support\Facades\Http;
use \Illuminate\Support\Facades\Log;

describe("Normal SMS Sending", function () {
    beforeEach(function () {
        Http::preventStrayRequests();
        config()->set('services.ippanel.sand_box');

    });
    it('it send sms succesfully', function () {

        $service = app(\App\Services\IpPanelSmsService::class);
        $service->setFrom('1000');
        $service->setApiKey('test_api_key');
        $to = ['09123456789'];
        $messeage = 'Test message';
        Http::fake(
            [
                'https://api2.ippanel.com/api/v1/sms/send/webservice/single' => Http::response(
                    [
                        'status' => "OK",
                        'data'   => [
                            'message_id' => random_int(1000000000, 9999999999),
                        ],
                    ],
                )
            ]
        );

        $service->send($to, $messeage);
        $smsLog = \App\Models\SmsLog::latest()->first();
        expect($smsLog->status)->toBe(200)
            ->and($smsLog->content)->toBe($messeage)
            ->and($smsLog->to)->toBe($to)
            ->and($smsLog->from)->toBe('1000')
            ->and($smsLog->type)->toBe('custom');
    });
    it('handles 400 errors', function () {
        $service = app(\App\Services\IpPanelSmsService::class);
        $service->setFrom('1000');
        $service->setApiKey('test_api_key');
        $to = ['09123456789'];
        $messeage = 'Test message';
        Http::fake(
            [
                'https://api2.ippanel.com/api/v1/sms/send/webservice/single' => Http::response(
                    null,
                    400
                )
            ]
        );
        $service->send($to, $messeage);
        $smsLog = \App\Models\SmsLog::latest()->first();
        expect($smsLog->status)->toBe(400)
            ->and($smsLog->content)->toBe($messeage)
            ->and($smsLog->to)->toBe($to)
            ->and($smsLog->from)->toBe('1000')
            ->and($smsLog->type)->toBe('custom')
            ->and($smsLog->data)->toBeNull();
    });
    it('handles 401 errors', function () {
        $service = app(\App\Services\IpPanelSmsService::class);
        $service->setFrom('1000');
        $service->setApiKey('test_api_key');
        $to = ['09123456789'];
        $messeage = 'Test message';
        Http::fake(
            [
                'https://api2.ippanel.com/api/v1/sms/send/webservice/single' => Http::response(
                    null,
                    401
                )
            ]
        );
        $service->send($to, $messeage);
        $smsLog = \App\Models\SmsLog::latest()->first();
        expect($smsLog->status)->toBe(401)
            ->and($smsLog->content)->toBe($messeage)
            ->and($smsLog->to)->toBe($to)
            ->and($smsLog->from)->toBe('1000')
            ->and($smsLog->type)->toBe('custom')
            ->and($smsLog->data)->toBeNull();

    });
    it('handles 403 errors', function () {
        $service = app(\App\Services\IpPanelSmsService::class);
        $service->setFrom('1000');
        $service->setApiKey('test_api_key');
        $to = ['09123456789'];
        $messeage = 'Test message';
        Http::fake(
            [
                'https://api2.ippanel.com/api/v1/sms/send/webservice/single' => Http::response(
                    [
                        'status'       => "Forbidden",
                        'code'         => 403,
                        "errorMessage" => "Forbidden",
                        "data"         => null
                    ],
                    403
                )
            ]
        );

        $service->send($to, $messeage);
        $smsLog = \App\Models\SmsLog::latest()->first();
        expect($smsLog->status)->toBe(403)
            ->and($smsLog->content)->toBe($messeage)
            ->and($smsLog->to)->toBe($to)
            ->and($smsLog->from)->toBe('1000')
            ->and($smsLog->type)->toBe('custom')
            ->and($smsLog->data)->toBeArray();
    });
    it('handles 422 errors', function () {
        $service = app(\App\Services\IpPanelSmsService::class);
        $service->setFrom('1000');
        $service->setApiKey('test_api_key');
        $to = ['09123456789'];
        $messeage = 'Test message';
        Http::fake(
            [
                'https://api2.ippanel.com/api/v1/sms/send/webservice/single' => Http::response(
                    [
                        'status'       => "Internal Server Error",
                        'code'         => 422,
                        "errorMessage" => [
                            "description.count_recipient" => [
                                "validation.required"
                            ]
                        ],
                        "data"         => null
                    ],
                    422
                )
            ]
        );
        $service->send($to, $messeage);
        $smsLog = \App\Models\SmsLog::latest()->first();
        expect($smsLog->status)->toBe(422)
            ->and($smsLog->content)->toBe($messeage)
            ->and($smsLog->to)->toBe($to)
            ->and($smsLog->from)->toBe('1000')
            ->and($smsLog->type)->toBe('custom')
            ->and($smsLog->data)->toBeArray();


    });

    it('handles 500 error', function () {
        $service = app(\App\Services\IpPanelSmsService::class);
        $service->setFrom('1000');
        $service->setApiKey('test_api_key');
        $to = ['09123456789'];
        $messeage = 'Test message';
        Http::fake(
            [
                'https://api2.ippanel.com/api/v1/sms/send/webservice/single' => Http::response(
                    null,
                    500
                )
            ]
        );
        $service->send($to, $messeage);
        $smsLog = \App\Models\SmsLog::latest()->first();
        expect($smsLog->status)->toBe(500)
            ->and($smsLog->content)->toBe($messeage)
            ->and($smsLog->to)->toBe($to)
            ->and($smsLog->from)->toBe('1000')
            ->and($smsLog->type)->toBe('custom')
            ->and($smsLog->data)->toBeNull();
    });

    it('handles sandbox mode', function () {
        config(['services.ippanel.sand_box' => true]);
        $service = app(\App\Services\IpPanelSmsService::class);
        $service->setFrom('1000');
        $service->setApiKey('test_api_key');
        $to = ['09123456789'];
        $messeage = 'Test message';

        $service->send($to, $messeage);

        $smsLog = \App\Models\SmsLog::latest()->first();
        expect($smsLog->status)->toBe(200)
            ->and($smsLog->content)->toBe($messeage)
            ->and($smsLog->to)->toBe($to)
            ->and($smsLog->from)->toBe('1000')
            ->and($smsLog->type)->toBe('custom')
            ->and($smsLog->data['message_id'])->toBeString();
    });
    it('throws exception if api key or from is not set', function () {
        $service = app(\App\Services\IpPanelSmsService::class);
        config()->set('services.ippanel.api_key', null);
        config()->set('services.ippanel.from', null);
        $to = ['09123456789'];
        $messeage = 'Test message';

        expect(fn() => $service->send($to, $messeage))->toThrow(\Exception::class,
            'IPPanel API key or sender number is not configured.');
    });

});
describe("Pattern SMS Sending", function () {
    beforeEach(function () {
        Http::preventStrayRequests();
        config()->set('services.ippanel.sand_box');
    });
    it('it send sms succesfully', function () {

        $service = app(\App\Services\IpPanelSmsService::class);
        $service->setFrom('1000');
        $service->setApiKey('test_api_key');
        $to = '09123456789';
        $messeage = 'Test message';
        Http::fake(
            [
                'https://api2.ippanel.com/api/v1/sms/pattern/normal/send' => Http::response(
                    [
                        'status' => "OK",
                        'data'   => [
                            'message_id' => random_int(1000000000, 9999999999),
                        ],
                    ],
                )
            ]
        );

        $service->sendPattern("XYZ",["code" => "1234"], $to, $messeage);
        $smsLog = \App\Models\SmsLog::latest()->first();
        expect($smsLog->status)->toBe(200)
            ->and($smsLog->content)->toBe($messeage)
            ->and($smsLog->to)->toBe($to)
            ->and($smsLog->from)->toBe('1000')
            ->and($smsLog->type)->toBe('pattern');
    });
    it('handles 400 errors', function () {
        $service = app(\App\Services\IpPanelSmsService::class);
        $service->setFrom('1000');
        $service->setApiKey('test_api_key');
        $to = '09123456789';
        $messeage = 'Test message';
        Http::fake(
            [
                'https://api2.ippanel.com/api/v1/sms/pattern/normal/send' => Http::response(
                    null,
                    400
                )
            ]
        );
        $service->sendPattern("XYZ", ["code" => "1234"], $to, $messeage);
        $smsLog = \App\Models\SmsLog::latest()->first();
        expect($smsLog->status)->toBe(400)
            ->and($smsLog->content)->toBe($messeage)
            ->and($smsLog->to)->toBe($to)
            ->and($smsLog->from)->toBe('1000')
            ->and($smsLog->type)->toBe('pattern')
            ->and($smsLog->data)->toBeNull();
    });
    it('handles 401 errors', function () {
        $service = app(\App\Services\IpPanelSmsService::class);
        $service->setFrom('1000');
        $service->setApiKey('test_api_key');
        $to = '09123456789';
        $messeage = 'Test message';
        Http::fake(
            [
                'https://api2.ippanel.com/api/v1/sms/pattern/normal/send' => Http::response(
                    null,
                    401
                )
            ]
        );
        $service->sendPattern("XYZ", ["code" => "1234"], $to, $messeage);
        $smsLog = \App\Models\SmsLog::latest()->first();
        expect($smsLog->status)->toBe(401)
            ->and($smsLog->content)->toBe($messeage)
            ->and($smsLog->to)->toBe($to)
            ->and($smsLog->from)->toBe('1000')
            ->and($smsLog->type)->toBe('pattern')
            ->and($smsLog->data)->toBeNull();
    });
    it('handles 403 errors', function () {
        $service = app(\App\Services\IpPanelSmsService::class);
        $service->setFrom('1000');
        $service->setApiKey('test_api_key');
        $to = '09123456789';
        $messeage = 'Test message';
        Http::fake(
            [
                'https://api2.ippanel.com/api/v1/sms/pattern/normal/send' => Http::response(
                    [
                        'status'       => "Forbidden",
                        'code'         => 403,
                        "errorMessage" => "Forbidden",
                        "data"         => null
                    ],
                    403
                )
            ]
        );
        $service->sendPattern("XYZ", ["code" => "1234"], $to, $messeage);
        $smsLog = \App\Models\SmsLog::latest()->first();
        expect($smsLog->status)->toBe(403)
            ->and($smsLog->content)->toBe($messeage)
            ->and($smsLog->to)->toBe($to)
            ->and($smsLog->from)->toBe('1000')
            ->and($smsLog->type)->toBe('pattern')
            ->and($smsLog->data)->toBeArray();
    });
    it('handles 422 errors', function () {
        $service = app(\App\Services\IpPanelSmsService::class);
        $service->setFrom('1000');
        $service->setApiKey('test_api_key');
        $to = '09123456789';
        $messeage = 'Test message';
        Http::fake(
            [
                'https://api2.ippanel.com/api/v1/sms/pattern/normal/send' => Http::response(
                    [
                        'status'       => "Internal Server Error",
                        'code'         => 422,
                        "errorMessage" => [
                            "description.count_recipient" => [
                                "validation.required"
                            ]
                        ],
                        "data"         => null
                    ],
                    422
                )
            ]
        );
        $service->sendPattern("XYZ", ["code" => "1234"], $to, $messeage);
        $smsLog = \App\Models\SmsLog::latest()->first();
        expect($smsLog->status)->toBe(422)
            ->and($smsLog->content)->toBe($messeage)
            ->and($smsLog->to)->toBe($to)
            ->and($smsLog->from)->toBe('1000')
            ->and($smsLog->type)->toBe('pattern')
            ->and($smsLog->data)->toBeArray();
    });
    it('handles 500 error', function () {
        $service = app(\App\Services\IpPanelSmsService::class);
        $service->setFrom('1000');
        $service->setApiKey('test_api_key');
        $to = '09123456789';
        $messeage = 'Test message';
        Http::fake(
            [
                'https://api2.ippanel.com/api/v1/sms/pattern/normal/send' => Http::response(
                    null,
                    500
                )
            ]
        );
        $service->sendPattern("XYZ", ["code" => "1234"], $to, $messeage);
        $smsLog = \App\Models\SmsLog::latest()->first();
        expect($smsLog->status)->toBe(500)
            ->and($smsLog->content)->toBe($messeage)
            ->and($smsLog->to)->toBe($to)
            ->and($smsLog->from)->toBe('1000')
            ->and($smsLog->type)->toBe('pattern')
            ->and($smsLog->data)->toBeNull();
    });
    it('handles sandbox mode', function () {
        config(['services.ippanel.sand_box' => true]);
        $service = app(\App\Services\IpPanelSmsService::class);
        $service->setFrom('1000');
        $service->setApiKey('test_api_key');
        $to = '09123456789';
        $messeage = 'Test message';

        $service->sendPattern("XYZ", ["code" => "1234"], $to, $messeage);

        $smsLog = \App\Models\SmsLog::latest()->first();
        expect($smsLog->status)->toBe(200)
            ->and($smsLog->content)->toBe($messeage)
            ->and($smsLog->to)->toBe($to)
            ->and($smsLog->from)->toBe('1000')
            ->and($smsLog->type)->toBe('pattern')
            ->and($smsLog->data['message_id'])->toBeString();
    });
    it('throws exception if api key or from is not set', function () {
        $service = app(\App\Services\IpPanelSmsService::class);
        config()->set('services.ippanel.api_key', null);
        config()->set('services.ippanel.from', null);
        $to = '09123456789';
        $messeage = 'Test message';

        expect(fn() => $service->sendPattern("XYZ", ["code" => "1234"], $to, $messeage))->toThrow(\Exception::class,
            'IPPanel API key or sender number is not configured.');
    });
});
