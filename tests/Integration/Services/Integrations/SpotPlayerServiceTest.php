<?php

declare(strict_types=1);

use App\Exceptions\Integrations\ExternalProvisioningException;
use App\Models\User;
use App\Services\Integrations\SpotPlayerService;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Http::preventStrayRequests();
    $this->service = new SpotPlayerService();
    $this->service->setConfig([
        'endpoint' => 'https://spotplayer.test/license',
        'api_key'  => 'spot-key',
        'sandbox'  => false,
    ]);
});

it('issues license from root level response fields', function (): void {
    $user = User::factory()->create();

    Http::fake([
        'https://spotplayer.test/*' => Http::response([
            'status'      => true,
            'license_key' => 'LIC-100',
            'player_url'  => 'https://player.example/100',
        ], 200),
    ]);

    $result = $this->service->issueLicense('SPOT-1', $user);

    expect($result['license_key'])->toBe('LIC-100')
        ->and($result['player_url'])->toBe('https://player.example/100')
        ->and($result['raw']['status'])->toBeTrue();
});

it('issues license from nested data response fields', function (): void {
    $user = User::factory()->create();

    Http::fake([
        'https://spotplayer.test/*' => Http::response([
            'status' => true,
            'data'   => [
                'license_key' => 'LIC-200',
                'player_url'  => 'https://player.example/200',
            ],
        ], 200),
    ]);

    $result = $this->service->issueLicense('SPOT-2', $user);

    expect($result['license_key'])->toBe('LIC-200')
        ->and($result['player_url'])->toBe('https://player.example/200');
});

it('throws when spotplayer request fails', function (): void {
    $user = User::factory()->create();

    Http::fake([
        'https://spotplayer.test/*' => Http::response([], 500),
    ]);

    expect(fn () => $this->service->issueLicense('SPOT-3', $user))
        ->toThrow(ExternalProvisioningException::class, 'SpotPlayer provisioning request failed.');
});

it('throws when spotplayer returns non array response', function (): void {
    $user = User::factory()->create();

    Http::fake([
        'https://spotplayer.test/*' => Http::response('ok', 200),
    ]);

    expect(fn () => $this->service->issueLicense('SPOT-4', $user))
        ->toThrow(ExternalProvisioningException::class, 'SpotPlayer invalid response format.');
});

it('throws when spotplayer returns status false', function (): void {
    $user = User::factory()->create();

    Http::fake([
        'https://spotplayer.test/*' => Http::response([
            'status'  => false,
            'message' => 'license limit reached',
        ], 200),
    ]);

    expect(fn () => $this->service->issueLicense('SPOT-5', $user))
        ->toThrow(ExternalProvisioningException::class, 'license limit reached');
});

it('throws when spotplayer returns explicit error field', function (): void {
    $user = User::factory()->create();

    Http::fake([
        'https://spotplayer.test/*' => Http::response([
            'error' => 'invalid api key',
        ], 200),
    ]);

    expect(fn () => $this->service->issueLicense('SPOT-6', $user))
        ->toThrow(ExternalProvisioningException::class, 'invalid api key');
});

it('throws when service used before configuration', function (): void {
    $service = new SpotPlayerService();
    $user    = User::factory()->create();

    expect(fn () => $service->issueLicense('SPOT-7', $user))
        ->toThrow(ExternalProvisioningException::class, 'SpotPlayer service configuration is missing.');
});
