<?php

declare(strict_types=1);

it('denies Horizon access when credentials are not configured', function (): void {
    config()->set('horizon.auth.username');
    config()->set('horizon.auth.password');

    $this->get('/horizon')
        ->assertUnauthorized()
        ->assertHeader('WWW-Authenticate', 'Basic realm="Horizon"');
});

it('denies Horizon access with invalid credentials', function (): void {
    config()->set('horizon.auth.username', 'operator');
    config()->set('horizon.auth.password', 'correct-password');

    $this->withBasicAuth('operator', 'wrong-password')
        ->get('/horizon')
        ->assertUnauthorized();
});

it('allows Horizon access with valid credentials', function (): void {
    config()->set('horizon.auth.username', 'operator');
    config()->set('horizon.auth.password', 'correct-password');

    $this->withBasicAuth('operator', 'correct-password')
        ->get('/horizon')
        ->assertSuccessful();
});
