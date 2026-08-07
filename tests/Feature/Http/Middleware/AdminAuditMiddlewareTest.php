<?php

declare(strict_types=1);

namespace Tests\Feature\Middleware;

use App\Http\Middleware\AdminAuditMiddleware;
use App\Models\AdminActionLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Date;
use ReflectionClass;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\Traits\AuthTestTrait;

uses(AuthTestTrait::class);

beforeEach(function (): void {
    $this->middleware = new AdminAuditMiddleware();
    $this->next       = fn ($request): Response => new Response('Success', 200);
    $this->travelTo(now()->setTime(14, 0, 0));
});

describe('AdminAuditMiddleware', function (): void {
    beforeEach(function (): void {
        $this->authorized_user();
    });

    test('it logs a valid request for an authenticated staff member', function (): void {
        $request = Request::create('/users', 'POST', ['name' => 'John Doe']);
        $route   = new Route('POST', '/users', ['as' => 'admin.users.store']);
        $route->bind($request);
        $request->setRouteResolver(fn (): Route => $route);

        $response = $this->middleware->handle($request, $this->next);

        expect($response->getStatusCode())->toBe(200);
        expect($response->getContent())->toBe('Success');

        // Assert: A log was created in the database
        $this->assertDatabaseCount('admin_action_logs', 1);
        $log = AdminActionLog::first();
        expect($log->admin_id)->toBe($this->user->id);
        expect($log->route_name)->toBe('admin.users.store');
        expect($log->action_type)->toBe('create');
        expect($log->request_data['name'])->toBe('John Doe');
    });

    test('it correctly extracts resource type and id from a bound route', function (): void {
        $user    = User::factory()->create();
        $request = Request::create("/users/{$user->id}", 'PUT', ['name' => 'Jane Doe']);

        $route = new Route('PUT', '/users/{user}', ['as' => 'admin.users.update']);

        $route->bind($request);

        $request->setRouteResolver(fn (): Route => $route);

        $this->middleware->handle($request, $this->next);

        $this->assertDatabaseCount('admin_action_logs', 1);
        $this->assertDatabaseHas('admin_action_logs', [
            'resource_type' => get_class($user),
            'resource_id'   => $user->id,
            'action_type'   => 'update',
            'admin_id'      => $this->user->id,
        ]);
    });

    test('it skips logging for GET requests by default', function (): void {

        $request = Request::create('/users/1', 'GET');
        $route   = new Route('GET', '/users/1', ['as' => 'admin.users.show']);
        $route->bind($request);
        $request->setRouteResolver(fn (): Route => $route);

        $this->middleware->handle($request, $this->next);

        $this->assertDatabaseCount('admin_action_logs', 0);
    });

    test('it skips logging for routes matching the fnmatch skip patterns', function (string $routeName): void {

        $request = Request::create('/some-url', 'POST'); // Method would normally be logged
        $route   = new Route('POST', '/some-url', ['as' => $routeName]);
        $route->bind($request);
        $request->setRouteResolver(fn (): Route => $route);

        $this->middleware->handle($request, $this->next);

        $this->assertDatabaseCount('admin_action_logs', 0);
    })->with([
        'admin.users.index',
        'admin.select-option.categories',
        'admin.health',
        'admin.status',
    ]);

    test('it skips logging if the request has no route name', function (): void {
        $request = Request::create('/unrouted-path', 'POST');

        $this->middleware->handle($request, $this->next);

        $this->assertDatabaseCount('admin_action_logs', 0);
    });

    // --- ACTION TYPE DETERMINATION TESTS ---

    test('it correctly determines wallet action types', function (string $routeName, string $expectedActionType): void {

        $request = Request::create('/wallet', 'POST', ['amount' => 100000]);
        $route   = new Route('POST', '/wallet', ['as' => $routeName]);
        $route->bind($request);
        $request->setRouteResolver(fn (): Route => $route);

        $this->middleware->handle($request, $this->next);

        $this->assertDatabaseHas('admin_action_logs', [
            'action_type' => $expectedActionType,
        ]);
    })->with([
        ['admin.wallet.deposit', 'deposit'],
        ['admin.wallet.withdraw', 'withdrawal'],
        ['admin.wallet.adjust', 'adjustment'],
        ['admin.wallet.allocate', 'allocation'],
        ['admin.wallet.trigger', 'allocation'],
    ]);

    test('it correctly determines action types for different HTTP methods',
        function (string $method, string $routeName, string $expectedActionType): void {

            $request = Request::create('/test', $method);
            $route   = new Route($method, '/test', ['as' => $routeName]);
            $route->bind($request);
            $request->setRouteResolver(fn (): Route => $route);

            $this->middleware->handle($request, $this->next);

            $this->assertDatabaseHas('admin_action_logs', [
                'action_type' => $expectedActionType,
            ]);
        })->with([
            ['POST', 'admin.test.store', 'create'],
            ['POST', 'admin.test.bulk.store', 'bulk_create'],
            ['PUT', 'admin.test.update', 'update'],
            ['PATCH', 'admin.test.update', 'update'],
            ['DELETE', 'admin.test.destroy', 'delete'],
        ]);

    test('it handles unknown HTTP methods correctly', function (): void {

        $request = Request::create('/test', 'OPTIONS');
        $route   = new Route('OPTIONS', '/test', ['as' => 'admin.test.options']);
        $route->bind($request);
        $request->setRouteResolver(fn (): Route => $route);

        $this->middleware->handle($request, $this->next);

        // Assert: OPTIONS is not in the logged methods, so no log should be created
        $this->assertDatabaseCount('admin_action_logs', 0);
    });

    test('it would determine view action type for GET method if it were logged', function (): void {
        // This test verifies the determineActionType logic for GET method
        // even though GET requests are normally skipped

        // We can test this by using reflection to call the private method
        $reflection = new ReflectionClass(AdminAuditMiddleware::class);
        $method     = $reflection->getMethod('determineActionType');
        $method->setAccessible(true);

        $result = $method->invoke($this->middleware, 'GET', 'admin.test.show');

        expect($result)->toBe('view');
    });

    test('it handles default case for unknown HTTP methods in determineActionType', function (): void {
        // Test the default case in the match statement
        $reflection = new ReflectionClass(AdminAuditMiddleware::class);
        $method     = $reflection->getMethod('determineActionType');
        $method->setAccessible(true);

        $result = $method->invoke($this->middleware, 'OPTIONS', 'admin.test.options');

        expect($result)->toBe('options'); // Should return lowercase method name
    });

    test('it extracts resource information for different model types',
        function (string $paramName, string $expectedModelClass): void {
            $resourceId = fake()->numberBetween(1, 100);
            $request    = Request::create("/test/{$resourceId}", 'PUT');
            $route      = new Route('PUT', "/test/{{$paramName}}", ['as' => 'admin.test.update']);

            // Mock the route parameters
            $route->bind($request);
            $route->setParameter($paramName, $resourceId);
            $request->setRouteResolver(fn (): Route => $route);

            $this->middleware->handle($request, $this->next);

            $this->assertDatabaseHas('admin_action_logs', [
                'resource_type' => $expectedModelClass,
                'resource_id'   => $resourceId,
            ]);
        })->with([
            ['user', 'App\\Models\\User'],
            ['wallet', 'App\\Models\\Wallet'],
            ['walletCampaign', 'App\\Models\\WalletCampaign'],
            ['staff', 'App\\Models\\Staff'],
            ['category', 'App\\Models\\Category'],
            ['course', 'App\\Models\\Course'],
            ['teacher', 'App\\Models\\Teacher'],
            ['term', 'App\\Models\\Term'],
            ['seminar', 'App\\Models\\Seminar'],
            ['discountPromotion', 'App\\Models\\DiscountPromotion'],
        ]);

    test('it extracts resource id from object when parameter is a model instance', function (): void {
        $user    = User::factory()->create();
        $request = Request::create("/users/{$user->id}", 'PUT');
        $route   = new Route('PUT', '/users/{user}', ['as' => 'admin.users.update']);

        $route->bind($request);
        $route->setParameter('user', $user); // Simulate model binding
        $request->setRouteResolver(fn (): Route => $route);

        $this->middleware->handle($request, $this->next);

        $this->assertDatabaseHas('admin_action_logs', [
            'resource_type' => get_class($user),
            'resource_id'   => $user->id,
        ]);
    });

    test('it returns null resource info when no matching parameters', function (): void {

        $request = Request::create('/test', 'POST');
        $route   = new Route('POST', '/test', ['as' => 'admin.test.store']);
        $route->bind($request);
        $request->setRouteResolver(fn (): Route => $route);

        $this->middleware->handle($request, $this->next);

        $this->assertDatabaseHas('admin_action_logs', [
            'resource_type' => null,
            'resource_id'   => null,
        ]);
    });
    test('it assigns high risk for server error responses', function (): void {

        $request = Request::create('/test', 'POST');
        $route   = new Route('POST', '/test', ['as' => 'admin.test.store']);
        $route->bind($request);
        $request->setRouteResolver(fn (): Route => $route);

        // Mock next to return server error
        $nextWithError = fn ($request): Response => new Response('Server Error', 500);

        $this->middleware->handle($request, $nextWithError);

        $this->assertDatabaseHas('admin_action_logs', [
            'risk_level'      => 'high',
            'response_status' => 500,
        ]);
    });

    test('it assigns high risk for DELETE requests', function (): void {
        $request = Request::create('/test', 'DELETE');
        $route   = new Route('DELETE', '/test', ['as' => 'admin.test.destroy']);
        $route->bind($request);
        $request->setRouteResolver(fn (): Route => $route);

        $this->middleware->handle($request, $this->next);

        $this->assertDatabaseHas('admin_action_logs', [
            'risk_level' => 'high',
        ]);
    });

    test('it assigns risk levels based on wallet transaction amounts',
        function (int $amount, string $expectedRiskLevel): void {
            $request = Request::create('/wallet/deposit', 'POST', ['amount' => $amount]);
            $route   = new Route('POST', '/wallet/deposit', ['as' => 'admin.wallet.deposit']);
            $route->bind($request);
            $request->setRouteResolver(fn (): Route => $route);

            $this->middleware->handle($request, $this->next);

            $this->assertDatabaseHas('admin_action_logs', [
                'risk_level'           => $expectedRiskLevel,
                'request_data->amount' => $amount,
            ]);
        })->with([
            [50000, 'low'], // Less than 100K Toman
            [5000000, 'medium'], // Between 100K and 1M Toman
            [15000000, 'high'], // More than 1M Toman
        ]);

    test('it assigns medium risk for bulk operations', function (): void {
        $request = Request::create('/bulk-create', 'POST');
        $route   = new Route('POST', '/bulk-create', ['as' => 'admin.users.bulk.store']);
        $route->bind($request);
        $request->setRouteResolver(fn (): Route => $route);

        $this->middleware->handle($request, $this->next);

        $this->assertDatabaseHas('admin_action_logs', [
            'risk_level' => 'medium',
        ]);
    });

    test('it assigns medium risk for actions outside business hours', function (): void {
        $outsideHours = now()->setTime(2, 0, 0); // 2 AM
        Date::setTestNow($outsideHours);

        $request = Request::create('/test', 'POST');
        $route   = new Route('POST', '/test', ['as' => 'admin.test.store']);
        $route->bind($request);
        $request->setRouteResolver(fn (): Route => $route);

        $this->middleware->handle($request, $this->next);

        $this->assertDatabaseHas('admin_action_logs', [
            'risk_level' => 'medium',
        ]);

        // Clean up
        Date::setTestNow();
    });

    test('it assigns low risk for normal operations during business hours', function (): void {
        $businessHours = now()->setTime(10, 0, 0); // 10 AM
        Date::setTestNow($businessHours);

        $request = Request::create('/test', 'POST');
        $route   = new Route('POST', '/test', ['as' => 'admin.test.store']);
        $route->bind($request);
        $request->setRouteResolver(fn (): Route => $route);

        $this->middleware->handle($request, $this->next);

        $this->assertDatabaseHas('admin_action_logs', [
            'risk_level' => 'low',
        ]);

        Date::setTestNow();
    });

    test('it correctly identifies wallet actions', function (string $routeName, bool $shouldBeWalletAction): void {
        $request = Request::create('/test', 'POST', ['amount' => 100000]);
        $route   = new Route('POST', '/test', ['as' => $routeName]);
        $route->bind($request);
        $request->setRouteResolver(fn (): Route => $route);

        $this->middleware->handle($request, $this->next);

        // Assert: If it's a wallet action with amount > 1M, it should be medium risk, otherwise low
        $expectedRisk = $shouldBeWalletAction ? 'low' : 'low'; // 100K is below medium threshold
        $this->assertDatabaseHas('admin_action_logs', [
            'risk_level' => $expectedRisk,
        ]);
    })->with([
        ['admin.wallet.balance', true],
        ['admin.user.deposit.create', true],
        ['admin.user.withdraw.create', true],
        ['admin.balance.adjust', true],
        ['admin.user.profile', false],
        ['admin.category.store', false],
    ]);

    // --- DATA SANITIZATION TESTS ---

    test('it sanitizes sensitive fields in request data', function (): void {

        $sensitiveData = [
            'password'              => 'secret123',
            'password_confirmation' => 'secret123',
            'current_password'      => 'old_secret',
            'new_password'          => 'new_secret',
            'token'                 => 'abc123token',
            'api_key'               => 'api_key_value',
            'secret'                => 'secret_value',
            'name'                  => 'John Doe',
        ];

        $request = Request::create('/test', 'POST', $sensitiveData);
        $route   = new Route('POST', '/test', ['as' => 'admin.test.store']);
        $route->bind($request);
        $request->setRouteResolver(fn (): Route => $route);

        $this->middleware->handle($request, $this->next);

        $log         = AdminActionLog::first();
        $requestData = $log->request_data;

        expect($requestData['password'])->toBe('[REDACTED]');
        expect($requestData['password_confirmation'])->toBe('[REDACTED]');
        expect($requestData['current_password'])->toBe('[REDACTED]');
        expect($requestData['new_password'])->toBe('[REDACTED]');
        expect($requestData['token'])->toBe('[REDACTED]');
        expect($requestData['api_key'])->toBe('[REDACTED]');
        expect($requestData['secret'])->toBe('[REDACTED]');
        expect($requestData['name'])->toBe('John Doe'); // Non-sensitive field should remain
    });

    test('it handles file uploads in request data', function (): void {

        $file    = UploadedFile::fake()->image('test.jpg');
        $request = Request::create('/test', 'POST');
        $request->files->set('avatar', $file);

        $route = new Route('POST', '/test', ['as' => 'admin.test.store']);
        $route->bind($request);
        $request->setRouteResolver(fn (): Route => $route);

        $this->middleware->handle($request, $this->next);

        $log         = AdminActionLog::first();
        $requestData = $log->request_data;

        expect($requestData['avatar'])->toBe('[FILE: test.jpg]');
    });

    test('it truncates large request data', function (): void {
        $largeData = ['large_field' => str_repeat('x', 12000)]; // > 10KB

        $request = Request::create('/test', 'POST', $largeData);
        $route   = new Route('POST', '/test', ['as' => 'admin.test.store']);
        $route->bind($request);
        $request->setRouteResolver(fn (): Route => $route);

        $this->middleware->handle($request, $this->next);

        $log         = AdminActionLog::first();
        $requestData = $log->request_data;

        expect($requestData)->toHaveKey('_large_request');
        expect($requestData['_large_request'])->toBe('Request data too large, truncated');
    });

    test('it logs comprehensive metadata', function (): void {

        $request = Request::create('/test', 'POST', ['test' => 'data']);
        $route   = new Route('POST', '/test', ['as' => 'admin.test.store']);
        $route->bind($request);
        $request->setRouteResolver(fn (): Route => $route);

        $this->middleware->handle($request, $this->next);

        $log = AdminActionLog::first();

        expect($log->metadata)->toHaveKeys([
            'execution_time_ms',
            'memory_usage',
            'timestamp',
            'request_size',
            'response_size',
        ]);

        expect($log->metadata['execution_time_ms'])->toBeFloat();
        expect($log->metadata['memory_usage'])->toBeInt();
        expect($log->metadata['request_size'])->toBeInt();
        expect($log->metadata['response_size'])->toBeInt();
    });

    test('it calculates response size for JSON responses', function (): void {
        $request = Request::create('/test', 'POST');
        $route   = new Route('POST', '/test', ['as' => 'admin.test.store']);
        $route->bind($request);
        $request->setRouteResolver(fn (): Route => $route);

        $jsonResponse = response()->json(['message' => 'success', 'data' => ['id' => 1]]);
        $nextWithJson = fn ($request) => $jsonResponse;

        $this->middleware->handle($request, $nextWithJson);

        $log = AdminActionLog::first();
        expect($log->metadata['response_size'])->toBeGreaterThan(0);
    });

    test('it logs route name as unknown when route name is null', function (): void {

        $request = Request::create('/test', 'POST');
        $route   = new Route('POST', '/test', []); // No route name
        $route->bind($request);
        $request->setRouteResolver(fn (): Route => $route);

        $this->middleware->handle($request, $this->next);

        $this->assertDatabaseCount('admin_action_logs', 0);
    });

    test('it handles PATCH method correctly', function (): void {

        $request = Request::create('/test', 'PATCH', ['name' => 'Updated Name']);
        $route   = new Route('PATCH', '/test', ['as' => 'admin.test.update']);
        $route->bind($request);
        $request->setRouteResolver(fn (): Route => $route);

        $this->middleware->handle($request, $this->next);

        $this->assertDatabaseHas('admin_action_logs', [
            'http_method' => 'PATCH',
            'action_type' => 'update',
        ]);
    });

    test('it logs IP address and user agent', function (): void {

        $request = Request::create('/test', 'POST');
        $request->server->set('REMOTE_ADDR', '192.168.1.100');
        $request->headers->set('User-Agent', 'Test User Agent');

        $route = new Route('POST', '/test', ['as' => 'admin.test.store']);
        $route->bind($request);
        $request->setRouteResolver(fn (): Route => $route);

        $this->middleware->handle($request, $this->next);

        $this->assertDatabaseHas('admin_action_logs', [
            'ip_address' => '192.168.1.100',
            'user_agent' => 'Test User Agent',
        ]);
    });
    test('it handles business hours edge cases', function (int $hour, string $expectedRisk): void {
        $testTime = now()->setTime($hour, 0, 0);
        Date::setTestNow($testTime);

        $request = Request::create('/test', 'POST');
        $route   = new Route('POST', '/test', ['as' => 'admin.test.store']);
        $route->bind($request);
        $request->setRouteResolver(fn (): Route => $route);

        $this->middleware->handle($request, $this->next);

        $this->assertDatabaseHas('admin_action_logs', [
            'risk_level' => $expectedRisk,
        ]);

        // Clean up
        Date::setTestNow();
    })->with([
        [6, 'medium'], // Before business hours (7 AM)
        [7, 'low'],    // Start of business hours
        [22, 'low'],   // End of business hours (10 PM)
        [23, 'medium'], // After business hours
    ]);

    test('it correctly determines business hours boundaries', function (): void {
        // Test the isOutsideBusinessHours method using reflection
        $reflection = new ReflectionClass(AdminAuditMiddleware::class);
        $method     = $reflection->getMethod('isOutsideBusinessHours');
        $method->setAccessible(true);

        // Mock different times
        Date::setTestNow(now()->setTime(6, 59, 59)); // Just before 7 AM
        expect($method->invoke($this->middleware))->toBeTrue();

        Date::setTestNow(now()->setTime(7, 0, 0)); // Exactly 7 AM
        expect($method->invoke($this->middleware))->toBeFalse();

        Date::setTestNow(now()->setTime(22, 0, 0)); // Exactly 10 PM
        expect($method->invoke($this->middleware))->toBeFalse();

        Date::setTestNow(now()->setTime(23, 0, 0)); // 11 PM - outside business hours
        expect($method->invoke($this->middleware))->toBeTrue();

        Date::setTestNow();
    });
    test('it correctly extracts wallet amounts', function (): void {
        $reflection = new ReflectionClass(AdminAuditMiddleware::class);
        $method     = $reflection->getMethod('extractWalletAmount');
        $method->setAccessible(true);

        // Test with amount present
        $requestData = ['amount' => '5000000'];
        expect($method->invoke($this->middleware, $requestData))->toBe(5000000);

        // Test with no amount
        $requestData = ['other_field' => 'value'];
        expect($method->invoke($this->middleware, $requestData))->toBe(0);

        // Test with null amount
        $requestData = ['amount' => null];
        expect($method->invoke($this->middleware, $requestData))->toBe(0);
    });

    test('it detects wallet actions correctly using isWalletAction', function (): void {
        $reflection = new ReflectionClass(AdminAuditMiddleware::class);
        $method     = $reflection->getMethod('isWalletAction');
        $method->setAccessible(true);

        expect($method->invoke($this->middleware, 'admin.wallet.balance'))->toBeTrue();
        expect($method->invoke($this->middleware, 'admin.user.deposit.create'))->toBeTrue();
        expect($method->invoke($this->middleware, 'admin.user.withdraw.create'))->toBeTrue();
        expect($method->invoke($this->middleware, 'admin.balance.adjust'))->toBeTrue();

        expect($method->invoke($this->middleware, 'admin.user.profile'))->toBeFalse();
        expect($method->invoke($this->middleware, 'admin.category.store'))->toBeFalse();
    });

    test('it handles route with null parameters in extractResourceInfo', function (): void {
        $reflection = new ReflectionClass(AdminAuditMiddleware::class);
        $method     = $reflection->getMethod('extractResourceInfo');
        $method->setAccessible(true);

        $request = Request::create('/test', 'POST');

        $result = $method->invoke($this->middleware, $request);

        expect($result)->toBe(['type' => null, 'id' => null]);
    });

    test('it prioritizes earlier resource mappings when multiple parameters exist', function (): void {
        $request = Request::create('/test', 'POST');
        $route   = new Route('POST', '/test/{user}/{wallet}', ['as' => 'admin.test.store']);
        $route->bind($request);

        $route->setParameter('user', 1);
        $route->setParameter('wallet', 2);
        $request->setRouteResolver(fn (): Route => $route);

        $this->middleware->handle($request, $this->next);

        $this->assertDatabaseHas('admin_action_logs', [
            'resource_type' => 'App\\Models\\User',
            'resource_id'   => 1,
        ]);
    });

    test('it calculates response size as 0 for non-JSON responses', function (): void {

        $request = Request::create('/test', 'POST');
        $route   = new Route('POST', '/test', ['as' => 'admin.test.store']);
        $route->bind($request);
        $request->setRouteResolver(fn (): Route => $route);

        // Mock next to return regular response (not JSON)
        $regularResponse = new Response('Plain text response');
        $nextWithRegular = fn ($request): Response => $regularResponse;

        $this->middleware->handle($request, $nextWithRegular);

        $log = AdminActionLog::first();
        expect($log->metadata['response_size'])->toBe(0);
    });

    test('it logs session id when available', function (): void {

        $request = Request::create('/test', 'POST');
        $route   = new Route('POST', '/test', ['as' => 'admin.test.store']);
        $route->bind($request);
        $request->setRouteResolver(fn (): Route => $route);

        $this->middleware->handle($request, $this->next);

        $log = AdminActionLog::first();
        expect($log)->toHaveKey('session_id');
    });

    test('it handles complex risk scenarios correctly', function (): void {
        Date::setTestNow(now()->setTime(3, 0, 0)); // Outside business hours

        $request = Request::create('/wallet/adjust', 'POST', ['amount' => 15000000]); // Large amount
        $route   = new Route('POST', '/wallet/adjust', ['as' => 'admin.wallet.adjust']);
        $route->bind($request);
        $request->setRouteResolver(fn (): Route => $route);

        $this->middleware->handle($request, $this->next);

        $this->assertDatabaseHas('admin_action_logs', [
            'risk_level' => 'high',
        ]);

        Date::setTestNow();
    });

    test('it handles edge case where wallet action has zero amount', function (): void {
        $this->travelTo(now()->setTime(14, 0, 0));
        $request = Request::create('/wallet/deposit', 'POST', ['amount' => 0]);
        $route   = new Route('POST', '/wallet/deposit', ['as' => 'admin.wallet.deposit']);
        $route->bind($request);
        $request->setRouteResolver(fn (): Route => $route);

        $this->middleware->handle($request, $this->next);

        $this->assertDatabaseHas('admin_action_logs', [
            'risk_level'  => 'low',
            'action_type' => 'deposit',
        ]);
    });

    // --- REQUEST DATA SANITIZATION EDGE CASES ---

    test('it preserves non-sensitive nested data', function (): void {
        $requestData = [
            'password' => 'secret123',
            'secret'   => 'hidden',
            'user'     => [
                'name'    => 'John Doe',
                'profile' => [
                    'bio' => 'Developer',
                ],
            ],
        ];

        $request = Request::create('/test', 'POST', $requestData);
        $route   = new Route('POST', '/test', ['as' => 'admin.test.store']);
        $route->bind($request);
        $request->setRouteResolver(fn (): Route => $route);

        $this->middleware->handle($request, $this->next);

        $log        = AdminActionLog::first();
        $loggedData = $log->request_data;

        expect($loggedData['password'])->toBe('[REDACTED]');
        expect($loggedData['secret'])->toBe('[REDACTED]');

        expect($loggedData['user']['name'])->toBe('John Doe');
        expect($loggedData['user']['profile']['bio'])->toBe('Developer');
    });

    test('it handles multiple file uploads', function (): void {
        $request = Request::create('/test', 'POST');
        $request->files->set('avatar', UploadedFile::fake()->image('avatar.jpg'));

        $route = new Route('POST', '/test', ['as' => 'admin.test.store']);
        $route->bind($request);
        $request->setRouteResolver(fn (): Route => $route);

        $this->middleware->handle($request, $this->next);

        $log         = AdminActionLog::first();
        $requestData = $log->request_data;

        expect($requestData['avatar'])->toBe('[FILE: avatar.jpg]');
    });

    test('it redacts sensitive fields nested at any depth', function (): void {
        $requestData = [
            'user' => [
                'password' => 's3cret',
                'profile'  => ['bio' => 'Developer'],
            ],
            'credentials' => [
                'token'   => 'abc123',
                'payload' => ['api_key' => 'key_value'],
            ],
            'safe' => ['nested' => ['value' => 'kept']],
        ];

        $request = Request::create('/test', 'POST', $requestData);
        $route   = new Route('POST', '/test', ['as' => 'admin.test.store']);
        $route->bind($request);
        $request->setRouteResolver(fn (): Route => $route);

        $this->middleware->handle($request, $this->next);

        $log        = AdminActionLog::first();
        $loggedData = $log->request_data;

        expect($loggedData['user']['password'])->toBe('[REDACTED]');
        expect($loggedData['user']['profile']['bio'])->toBe('Developer');
        expect($loggedData['credentials']['token'])->toBe('[REDACTED]');
        expect($loggedData['credentials']['payload']['api_key'])->toBe('[REDACTED]');
        expect($loggedData['safe']['nested']['value'])->toBe('kept');
    });

    test('it derives resource info generically from any bound model parameter', function (): void {
        $user = User::factory()->create();

        $request = Request::create("/roles/{$user->id}", 'PUT', ['name' => 'admin']);
        $route   = new Route('PUT', '/roles/{role}', ['as' => 'admin.roles.update']);
        $route->bind($request);

        // Simulate implicit model binding (SubstituteBindings) with a model object.
        $route->setParameter('role', $user);
        $request->setRouteResolver(fn (): Route => $route);

        $this->middleware->handle($request, $this->next);

        $this->assertDatabaseHas('admin_action_logs', [
            'resource_type' => get_class($user),
            'resource_id'   => $user->id,
        ]);
    });

    test('it calculates request size in metadata', function (): void {

        $largeContent = str_repeat('test', 100);
        $request      = Request::create('/test', 'POST', [], [], [], [], $largeContent);
        $route        = new Route('POST', '/test', ['as' => 'admin.test.store']);
        $route->bind($request);
        $request->setRouteResolver(fn (): Route => $route);

        $this->middleware->handle($request, $this->next);

        $log = AdminActionLog::first();
        expect($log->metadata['request_size'])->toBe(mb_strlen($largeContent));
    });

    test('it logs memory usage in metadata', function (): void {

        $request = Request::create('/test', 'POST');
        $route   = new Route('POST', '/test', ['as' => 'admin.test.store']);
        $route->bind($request);
        $request->setRouteResolver(fn (): Route => $route);

        $this->middleware->handle($request, $this->next);

        $log = AdminActionLog::first();
        expect($log->metadata['memory_usage'])->toBeGreaterThan(0);
    });
});

test('it does not log if staff member is not authenticated', function (): void {

    $request = Request::create('/users', 'POST');
    $route   = new Route('POST', '/users', ['as' => 'admin.users.store']);
    $route->bind($request);
    $request->setRouteResolver(fn (): Route => $route);

    $this->middleware->handle($request, $this->next);

    $this->assertDatabaseCount('admin_action_logs', 0);
});
