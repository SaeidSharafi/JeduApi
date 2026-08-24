<?php

declare(strict_types=1);

namespace Tests\Feature\Middleware;

use App\Http\Middleware\EnsureAdminNumericIdsMiddleware;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Stub controller used to give the test routes a real controller action whose
 * signature type-hints Eloquent models, so that Route::signatureParameters()
 * can resolve them (see EnsureAdminNumericIdsMiddleware::handle()).
 */
final class EnsureAdminNumericIdsTestController
{
    public function show(User $user): string
    {
        return 'ok';
    }

    public function showStringKeyed(StringKeyedTestModel $model): string
    {
        return 'ok';
    }
}

/**
 * Stub model with a string key type (e.g. a UUID primary key) used to verify
 * the middleware does not abort for non-numeric values on string-keyed models.
 */
final class StringKeyedTestModel extends Model
{
    protected $keyType = 'string';
}

describe('EnsureAdminNumericIdsMiddleware', function (): void {
    beforeEach(function (): void {
        $this->middleware = new EnsureAdminNumericIdsMiddleware();
        $this->next       = fn ($request): Response => new Response('ok', 200);
    });

    test('it passes requests outside the admin path through untouched', function (): void {
        $request = Request::create('/api/v1/users/abc', 'GET');
        $route   = new Route('GET', '/api/v1/users/{user}', [
            'uses' => EnsureAdminNumericIdsTestController::class.'@show',
        ]);
        $route->bind($request);
        $route->setParameter('user', 'abc');
        $request->setRouteResolver(fn (): Route => $route);

        $response = $this->middleware->handle($request, $this->next);

        expect($response->getStatusCode())->toBe(200);
        expect($response->getContent())->toBe('ok');
    });

    test('it passes numeric parameter values for integer-keyed models', function (mixed $value): void {
        $request = Request::create('/api/v1/admin/users/123', 'GET');
        $route   = new Route('GET', '/api/v1/admin/users/{user}', [
            'uses' => EnsureAdminNumericIdsTestController::class.'@show',
        ]);
        $route->bind($request);
        $route->setParameter('user', $value);
        $request->setRouteResolver(fn (): Route => $route);

        $response = $this->middleware->handle($request, $this->next);

        expect($response->getStatusCode())->toBe(200);
        expect($response->getContent())->toBe('ok');
    })->with([
        'numeric string' => '123',
        'integer'        => 5,
    ]);

    test('it aborts with 404 when an admin route receives a non-numeric value for an integer-keyed model',
        function (string $value): void {
            $request = Request::create("/api/v1/admin/users/{$value}", 'GET');
            $route   = new Route('GET', '/api/v1/admin/users/{user}', [
                'uses' => EnsureAdminNumericIdsTestController::class.'@show',
            ]);
            $route->bind($request);
            $route->setParameter('user', $value);
            $request->setRouteResolver(fn (): Route => $route);

            expect(fn () => $this->middleware->handle($request, $this->next))
                ->toThrow(NotFoundHttpException::class);
        })->with([
            'alphabetical' => 'abc',
            'mixed'        => 'not-a-number',
            'uuid-like'    => '550e8400-e29b-41d4-a716-446655440000',
        ]);

    test('it passes non-numeric values when the route binds to a custom field', function (): void {
        $request = Request::create('/api/v1/admin/users/abc', 'GET');
        $route   = new Route('GET', '/api/v1/admin/users/{user}', [
            'uses' => EnsureAdminNumericIdsTestController::class.'@show',
        ]);
        $route->bind($request);
        $route->setBindingFields(['user' => 'uuid']);
        $route->setParameter('user', 'abc');
        $request->setRouteResolver(fn (): Route => $route);

        $response = $this->middleware->handle($request, $this->next);

        expect($response->getStatusCode())->toBe(200);
        expect($response->getContent())->toBe('ok');
    });

    test('it passes non-numeric values for models with a string key type', function (): void {
        $request = Request::create('/api/v1/admin/string-keyed/550e8400-e29b-41d4-a716-446655440000', 'GET');
        $route   = new Route('GET', '/api/v1/admin/string-keyed/{model}', [
            'uses' => EnsureAdminNumericIdsTestController::class.'@showStringKeyed',
        ]);
        $route->bind($request);
        $route->setParameter('model', '550e8400-e29b-41d4-a716-446655440000');
        $request->setRouteResolver(fn (): Route => $route);

        $response = $this->middleware->handle($request, $this->next);

        expect($response->getStatusCode())->toBe(200);
        expect($response->getContent())->toBe('ok');
    });
});
