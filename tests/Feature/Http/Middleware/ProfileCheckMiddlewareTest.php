<?php

declare(strict_types=1);

namespace Tests\Feature\Middleware;

use App\Enums\Payment\PaymentMethodEnum;
use App\Http\Middleware\ProfileCheckMiddleware;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\Traits\AuthTestTrait;

uses(AuthTestTrait::class);

beforeEach(function (): void {
    $this->middleware = new ProfileCheckMiddleware();
    $this->next       = fn ($request): Response => new Response('Success', 200);
});

describe('ProfileCheckMiddleware', function (): void {

    it('prevent users with incompelte profile to checkout', function (): void {
        $user = User::create([
            'phone' => '09120000000',
        ]);
        $this->customer($user);
        $request = Request::create('/checkout', 'POST', ['payment_method' => PaymentMethodEnum::BANK_TRANSFER->value]);
        $route   = new Route('POST', '/checkout', ['as' => 'checkout']);
        $route->bind($request);
        $request->setRouteResolver(fn (): Route => $route);

        $response = $this->middleware->handle($request, $this->next);
        $json     = json_decode($response->getContent(), true);
        expect($response->getStatusCode())->toBe(403)
            ->and($json)->toHaveKey('message')
            ->and($json)->toHaveKey('error_code')
            ->and($json['error_code'])->toBe('PROFILE_INCOMPLETE')
            ->and($json['message'])->toBe(__('shop.profile_incomplete_message'));

    });

    test('user with complete profile can proceed to checkout', function (): void {
        $user = User::factory()->create([
            'phone' => '09120000000',
        ]);
        $this->customer($user);
        $request = Request::create('/checkout', 'POST', ['payment_method' => PaymentMethodEnum::BANK_TRANSFER->value]);
        $route   = new Route('POST', '/checkout', ['as' => 'checkout']);
        $route->bind($request);
        $request->setRouteResolver(fn (): Route => $route);

        $response = $this->middleware->handle($request, $this->next);

        expect($response->getStatusCode())->toBe(200)
            ->and($response->getContent())->toBe('Success');
    });
});
