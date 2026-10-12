<?php

declare(strict_types=1);

use App\Enums\System\SettingKeyEnum;
use App\Exceptions\Integrations\RecoverableProvisioningException;
use App\Exceptions\Integrations\UnrecoverableProvisioningException;
use App\Models\User;
use App\Services\Integrations\SpotPlayerService;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Http::preventStrayRequests();

    $settings = $this->mock(SettingsService::class);
    $settings->shouldReceive('get')
        ->with(SettingKeyEnum::SPOT_PLAYER, Mockery::any())
        ->andReturn([
            'endpoint' => 'https://spotplayer.test/license/edit/',
            'api_key'  => 'spot-key',
            'sandbox'  => false,
        ]);

    $this->service = app(SpotPlayerService::class);
});

mutates(SpotPlayerService::class);

it('sends the documented $API/$LEVEL headers and course/watermark body', function (): void {
    $user = User::factory()->create([
        'first_name' => 'Ali',
        'last_name'  => 'Rezaei',
        'phone'      => '09121112266',
    ]);

    Http::fake([
        'https://spotplayer.test/*' => Http::response([
            '_id' => 'lic-1',
            'key' => 'KEY-100',
            'url' => '/lic-1/hash/',
        ], 200),
    ]);

    $result = $this->service->issueLicense('COURSE-1', $user);

    expect($result['license_key'])->toBe('KEY-100')
        ->and($result['player_url'])->toBe('https://dl.spotplayer.ir/lic-1/hash/')
        ->and($result['raw']['_id'])->toBe('lic-1');

    Http::assertSent(function ($request): bool {
        return $request->url()    === 'https://spotplayer.test/license/edit/'
            && $request->method() === 'POST'
            && $request->hasHeader('$API', 'spot-key')
            && $request->hasHeader('$LEVEL', '-1')
            && $request->data() === [
                'test'      => false,
                'course'    => ['COURSE-1'],
                'name'      => 'Ali Rezaei',
                'watermark' => ['texts' => [['text' => '09121112266']]],
            ];
    });
});

it('issues a test licence when sandbox is enabled', function (): void {
    $settings = $this->mock(SettingsService::class);
    $settings->shouldReceive('get')
        ->with(SettingKeyEnum::SPOT_PLAYER, Mockery::any())
        ->andReturn([
            'endpoint' => 'https://spotplayer.test/license/edit/',
            'api_key'  => 'spot-key',
            'sandbox'  => true,
        ]);

    Http::fake([
        'https://spotplayer.test/*' => Http::response(['key' => 'KEY-1', 'url' => '/a/b/'], 200),
    ]);

    app(SpotPlayerService::class)->issueLicense('COURSE-1', User::factory()->create());

    Http::assertSent(fn ($request): bool => $request->data()['test'] === true);
});

it('surfaces the SpotPlayer ex message and treats it as final', function (): void {
    Http::fake([
        'https://spotplayer.test/*' => Http::response([
            'ex' => ['msg' => 'مقدار آرایه course الزامی است.', 'status' => 471],
        ], 471),
    ]);

    try {
        $this->service->issueLicense('COURSE-1', User::factory()->create());
        $this->fail('Expected UnrecoverableProvisioningException');
    } catch (UnrecoverableProvisioningException $e) {
        expect($e->getMessage())->toBe('مقدار آرایه course الزامی است.')
            ->and($e->metaData['http_status'])->toBe(471);
    }
});

it('throws invalid_response when the API answers with html instead of json', function (): void {
    Http::fake([
        'https://spotplayer.test/*' => Http::response('<!DOCTYPE html><html lang="fa"></html>', 200),
    ]);

    expect(fn () => $this->service->issueLicense('COURSE-1', User::factory()->create()))
        ->toThrow(UnrecoverableProvisioningException::class, 'SpotPlayer returned an invalid response format.');
});

it('throws invalid_response when the licence key or url is missing', function (): void {
    Http::fake([
        'https://spotplayer.test/*' => Http::response(['_id' => 'lic-1'], 200),
    ]);

    expect(fn () => $this->service->issueLicense('COURSE-1', User::factory()->create()))
        ->toThrow(UnrecoverableProvisioningException::class, 'SpotPlayer returned an invalid response format.');
});

it('treats a 5xx without an ex envelope as recoverable', function (): void {
    Http::fake([
        'https://spotplayer.test/*' => Http::response([], 500),
    ]);

    expect(fn () => $this->service->issueLicense('COURSE-1', User::factory()->create()))
        ->toThrow(RecoverableProvisioningException::class, 'HTTP 500 on /.');
});

it('throws when service used before configuration', function (): void {
    $settings = $this->mock(SettingsService::class);
    $settings->shouldReceive('get')
        ->with(SettingKeyEnum::SPOT_PLAYER, Mockery::any())
        ->andReturn(['endpoint' => '', 'api_key' => '']);

    $service = app(SpotPlayerService::class);

    expect(fn () => $service->issueLicense('COURSE-1', User::factory()->create()))
        ->toThrow(UnrecoverableProvisioningException::class);
});
