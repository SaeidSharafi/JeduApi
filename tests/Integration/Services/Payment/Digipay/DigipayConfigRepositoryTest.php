<?php

declare(strict_types=1);

use App\Enums\System\SettingKeyEnum;
use App\Services\Payment\Digipay\DigipayConfigRepository;
use App\Services\Payment\Digipay\DigipayException;
use App\Services\SettingsService;

beforeEach(function (): void {
    $this->settingsService = mock(SettingsService::class);
    $this->settingsService->shouldReceive('get')
        ->with(SettingKeyEnum::DIGIPAY, [])
        ->andReturn([
            'client_id'     => 'test-client-id',
            'client_secret' => 'test-client-secret',
            'username'      => 'test-user',
            'password'      => 'test-pass',
            'sandbox_mode'  => true,
        ]);
});

it('returns client_id from settings', function (): void {
    $repo = new DigipayConfigRepository($this->settingsService);

    expect($repo->getClientId())->toBe('test-client-id');
});

it('returns client_secret from settings', function (): void {
    $repo = new DigipayConfigRepository($this->settingsService);

    expect($repo->getClientSecret())->toBe('test-client-secret');
});

it('returns username from settings', function (): void {
    $repo = new DigipayConfigRepository($this->settingsService);

    expect($repo->getUsername())->toBe('test-user');
});

it('returns password from settings', function (): void {
    $repo = new DigipayConfigRepository($this->settingsService);

    expect($repo->getPassword())->toBe('test-pass');
});

it('detects sandbox mode', function (): void {
    $repo = new DigipayConfigRepository($this->settingsService);

    expect($repo->isSandbox())->toBeTrue();
});

it('detects production mode when sandbox is false', function (): void {
    $this->settingsService = mock(SettingsService::class);
    $this->settingsService->shouldReceive('get')
        ->with(SettingKeyEnum::DIGIPAY, [])
        ->andReturn([
            'client_id'     => 'prod-client',
            'client_secret' => 'prod-secret',
            'username'      => 'prod-user',
            'password'      => 'prod-pass',
            'sandbox_mode'  => false,
        ]);

    $repo = new DigipayConfigRepository($this->settingsService);

    expect($repo->isSandbox())->toBeFalse();
});

it('throws when required config key is missing', function (): void {
    $this->settingsService = mock(SettingsService::class);
    $this->settingsService->shouldReceive('get')
        ->with(SettingKeyEnum::DIGIPAY, [])
        ->andReturn([
            'client_id'     => 'test',
            'client_secret' => 'test',
            'username'      => '',
            'password'      => 'test',
        ]);

    $repo = new DigipayConfigRepository($this->settingsService);

    expect(fn (): string => $repo->getUsername())
        ->toThrow(DigipayException::class, 'Digipay configuration missing: username');
});

it('throws when required config key does not exist', function (): void {
    $this->settingsService = mock(SettingsService::class);
    $this->settingsService->shouldReceive('get')
        ->with(SettingKeyEnum::DIGIPAY, [])
        ->andReturn([
            'client_id'     => 'test',
            'client_secret' => 'test',
            'password'      => 'test',
        ]);

    $repo = new DigipayConfigRepository($this->settingsService);

    expect(fn (): string => $repo->getUsername())
        ->toThrow(DigipayException::class, 'Digipay configuration missing: username');
});

it('returns sandbox base_url when sandbox is enabled', function (): void {
    config(['digipay.endpoints.sandbox.base_url' => 'https://sandbox.digipay.test']);

    $repo = new DigipayConfigRepository($this->settingsService);

    expect($repo->getBaseUrl())->toBe('https://sandbox.digipay.test');
});

it('returns production base_url when sandbox is disabled', function (): void {
    $this->settingsService = mock(SettingsService::class);
    $this->settingsService->shouldReceive('get')
        ->with(SettingKeyEnum::DIGIPAY, [])
        ->andReturn([
            'client_id'     => 'prod-client',
            'client_secret' => 'prod-secret',
            'username'      => 'prod-user',
            'password'      => 'prod-pass',
            'sandbox_mode'  => false,
        ]);
    config(['digipay.endpoints.production.base_url' => 'https://api.digipay.prod']);

    $repo = new DigipayConfigRepository($this->settingsService);

    expect($repo->getBaseUrl())->toBe('https://api.digipay.prod');
});

it('returns configured timeout', function (): void {
    config(['digipay.timeout' => 45]);

    $repo = new DigipayConfigRepository($this->settingsService);

    expect($repo->getTimeout())->toBe(45);
});

it('returns default timeout of 30 when not configured', function (): void {
    $repo = new DigipayConfigRepository($this->settingsService);

    expect($repo->getTimeout())->toBe(30);
});
