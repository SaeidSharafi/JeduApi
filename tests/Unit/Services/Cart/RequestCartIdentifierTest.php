<?php

declare(strict_types=1);

use App\Services\Cart\RequestCartIdentifier;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Mockery as m;

it('returns user id for authenticated users and no guest token', function (): void {
    $request = Request::create('/test', 'GET');

    $guard = m::mock(Guard::class);
    $guard->expects('check')->andReturn(true);
    $guard->expects('id')->andReturn(42);

    $identifier = new RequestCartIdentifier($request, $guard);

    expect($identifier->userId())->toBe(42)
        ->and($identifier->isGuest())->toBeFalse()
        ->and($identifier->guestToken())->toBeNull();
});

it('uses existing valid header token and does not mint a new one', function (): void {
    $existing = (string) Str::uuid();
    $request  = Request::create('/test', 'GET', server: ['HTTP_X_GUEST_TOKEN' => $existing]);

    $guard = m::mock(Guard::class);
    $guard->expects('check')->andReturn(false);

    $identifier = new RequestCartIdentifier($request, $guard);

    // Should read from header
    expect($identifier->guestToken())->toBe($existing)
        ->and($identifier->isGuest())->toBeTrue();

    // ensureGuestToken should return the same, not mint a new one
    $ensured = $identifier->ensureGuestToken();
    expect($ensured)->toBe($existing);
});

it('mints a guest token when absent or invalid', function (): void {
    $request = Request::create('/test', 'GET', server: ['HTTP_X_GUEST_TOKEN' => 'invalid-token']);

    $guard = m::mock(Guard::class);
    $guard->expects('check')->andReturn(false);

    $identifier = new RequestCartIdentifier($request, $guard);

    // Invalid header should not be accepted; ensureGuestToken should mint a UUID
    $minted = $identifier->ensureGuestToken();
    expect(Str::isUuid($minted))->toBeTrue();

    // Subsequent calls return the same minted token
    expect($identifier->ensureGuestToken())->toBe($minted);
});
