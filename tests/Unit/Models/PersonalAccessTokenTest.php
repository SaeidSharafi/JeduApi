<?php

declare(strict_types=1);

use App\Contracts\Cache\CacheStore;
use App\Enums\System\CacheKey;
use App\Models\PersonalAccessToken;
use App\Models\User;

covers(PersonalAccessToken::class);

it('forgets the cached lookup and user snapshot of every token a model owns', function (): void {
    $user  = User::factory()->create();
    $plain = $user->createToken('auth_token', ['*'], now()->addMinutes(60))->plainTextToken;
    $token = PersonalAccessToken::findToken($plain);
    $token->tokenable;

    $cache    = app(CacheStore::class);
    $lookup   = ['hash' => hash('sha256', explode('|', $plain, 2)[1])];
    $snapshot = ['id' => $token->id, 'env' => app()->environment()];

    expect($cache->get(CacheKey::AccessToken, $lookup))->not->toBeNull()
        ->and($cache->get(CacheKey::Tokenable, $snapshot))->not->toBeNull();

    PersonalAccessToken::forgetCacheFor($user);

    expect($cache->get(CacheKey::AccessToken, $lookup))->toBeNull()
        ->and($cache->get(CacheKey::Tokenable, $snapshot))->toBeNull();
});

it('forgets both cached entries when a token is deleted', function (): void {
    $user  = User::factory()->create();
    $plain = $user->createToken('auth_token', ['*'], now()->addMinutes(60))->plainTextToken;
    $token = PersonalAccessToken::findToken($plain);
    $token->tokenable;

    $cache    = app(CacheStore::class);
    $lookup   = ['hash' => hash('sha256', explode('|', $plain, 2)[1])];
    $snapshot = ['id' => $token->id, 'env' => app()->environment()];

    PersonalAccessToken::findToken($plain)->delete();

    expect($cache->get(CacheKey::AccessToken, $lookup))->toBeNull()
        ->and($cache->get(CacheKey::Tokenable, $snapshot))->toBeNull();
});
