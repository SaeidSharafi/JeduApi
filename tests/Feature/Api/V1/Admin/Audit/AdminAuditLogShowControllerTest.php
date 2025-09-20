<?php

declare(strict_types=1);

use App\Enums\PermissionEnum;
use App\Models\AdminActionLog;
use App\Models\Staff;
use App\Models\User;
use Tests\AuthTestTrait;

uses(AuthTestTrait::class);

describe('AdminAuditLogShowController', function (): void {

    beforeEach(function (): void {
        $this->admin   = Staff::factory()->create();
        $this->baseUrl = '/api/v1/admin/audit/admin-actions';
    });

    it('can show specific admin audit log with proper permissions', function (): void {
        $log = AdminActionLog::factory()->create([
            'action_type'     => 'create',
            'resource_type'   => 'App\\Models\\User',
            'resource_id'     => 123,
            'route_name'      => 'admin.users.store',
            'http_method'     => 'POST',
            'request_data'    => ['name' => 'Test User'],
            'response_status' => 201,
            'ip_address'      => '127.0.0.1',
            'user_agent'      => 'Test Agent',
            'session_id'      => 'test-session',
            'risk_level'      => 'low',
            'metadata'        => ['test' => 'data'],
        ]);

        $response = $this->authorized_user([PermissionEnum::AUDIT_ADMIN_ACTIONS_VIEW])
            ->getJson($this->baseUrl.'/'.$log->id);

        $response->assertJsonStructure([
            'data' => [
                'id',
                'action_type',
                'resource_type',
                'resource_id',
                'route_name',
                'http_method',
                'request_data',
                'response_status',
                'ip_address',
                'user_agent',
                'session_id',
                'risk_level',
                'metadata',
                'created_at',
                'admin' => [
                    'id',
                    'name',
                ],
            ],
        ]);

        $data = $response->json('data');
        expect($data['id'])->toBe($log->id);
        expect($data['action_type'])->toBe('create');
        expect($data['resource_type'])->toBe('App\\Models\\User');
        expect($data['resource_id'])->toBe(123);
        expect($data['route_name'])->toBe('admin.users.store');
        expect($data['http_method'])->toBe('POST');
        expect($data['request_data'])->toBe(['name' => 'Test User']);
        expect($data['response_status'])->toBe(201);
        expect($data['ip_address'])->toBe('127.0.0.1');
        expect($data['user_agent'])->toBe('Test Agent');
        expect($data['session_id'])->toBe('test-session');
        expect($data['risk_level'])->toBe('low');
        expect($data['metadata'])->toBe(['test' => 'data']);
    });

    it('requires permission to view audit log details', function (): void {
        $log = AdminActionLog::factory()->create();

        $response = $this->authorized_user([])
            ->getJson($this->baseUrl.'/'.$log->id);

        $response->assertForbidden();
    });

    it('includes admin relationship in response', function (): void {
        $admin = Staff::factory()->create(['name' => 'John Doe']);
        $log   = AdminActionLog::factory()->create(['admin_id' => $admin->id]);

        $response = $this->authorized_user([PermissionEnum::AUDIT_ADMIN_ACTIONS_VIEW])
            ->getJson($this->baseUrl.'/'.$log->id);

        $response->assertSuccessful();
        $data = $response->json('data');
        expect($data)->toHaveKey('admin');
        expect($data['admin']['name'])->toBe('John Doe');
        expect($data['admin']['id'])->toBe($admin->id);
    });

    it('includes resource relationship when resource exists', function (): void {
        $user = User::factory()->create(['first_name' => 'Test', 'last_name' => 'User']);
        $log  = AdminActionLog::factory()->create([
            'resource_type' => User::class,
            'resource_id'   => $user->id,
        ]);

        $response = $this->authorized_user([PermissionEnum::AUDIT_ADMIN_ACTIONS_VIEW])
            ->getJson($this->baseUrl.'/'.$log->id);

        $response->assertSuccessful();
        $data = $response->json('data');
        expect($data)->toHaveKey('resource');
        expect($data['resource']['first_name'])->toBe('Test');
        expect($data['resource']['last_name'])->toBe('User');
        expect($data['resource']['id'])->toBe($user->id);
    });

    it('handles missing resource gracefully', function (): void {
        $log = AdminActionLog::factory()->create([
            'resource_type' => User::class,
            'resource_id'   => 99999, // Non-existent user
        ]);

        $response = $this->authorized_user([PermissionEnum::AUDIT_ADMIN_ACTIONS_VIEW])
            ->getJson($this->baseUrl.'/'.$log->id);

        $response->assertSuccessful();
        $data = $response->json('data');
        // Resource should be null or not included when not found
        expect($data['resource'] ?? null)->toBeNull();
    });

    it('handles log with no resource relationship', function (): void {
        $log = AdminActionLog::factory()->create([
            'resource_type' => null,
            'resource_id'   => null,
        ]);

        $response = $this->authorized_user([PermissionEnum::AUDIT_ADMIN_ACTIONS_VIEW])
            ->getJson($this->baseUrl.'/'.$log->id);

        $response->assertSuccessful();
        $data = $response->json('data');
        expect($data['resource_type'])->toBeNull();
        expect($data['resource_id'])->toBeNull();
    });

    it('returns 404 for non-existent audit log', function (): void {
        $response = $this->authorized_user([PermissionEnum::AUDIT_ADMIN_ACTIONS_VIEW])
            ->getJson($this->baseUrl.'/99999');

        $response->assertNotFound();
    });

    it('shows correct data types for all fields', function (): void {
        $log = AdminActionLog::factory()->create([
            'request_data'    => ['key' => 'value'],
            'metadata'        => ['test' => 'data'],
            'response_status' => 200,
        ]);

        $response = $this->authorized_user([PermissionEnum::AUDIT_ADMIN_ACTIONS_VIEW])
            ->getJson($this->baseUrl.'/'.$log->id);

        $response->assertSuccessful();
        $data = $response->json('data');

        expect($data['id'])->toBeInt();
        expect($data['action_type'])->toBeString();
        expect($data['resource_type'])->toBeString();
        expect($data['route_name'])->toBeString();
        expect($data['http_method'])->toBeString();
        expect($data['request_data'])->toBeArray();
        expect($data['response_status'])->toBeInt();
        expect($data['ip_address'])->toBeString();
        expect($data['user_agent'])->toBeString();
        expect($data['session_id'])->toBeString();
        expect($data['risk_level'])->toBeString();
        expect($data['metadata'])->toBeArray();
        expect($data['created_at'])->toBeString();
    });

    it('shows high risk log correctly', function (): void {
        $log = AdminActionLog::factory()->create(['risk_level' => 'high']);

        $response = $this->authorized_user([PermissionEnum::AUDIT_ADMIN_ACTIONS_VIEW])
            ->getJson($this->baseUrl.'/'.$log->id);

        $response->assertSuccessful();
        $data = $response->json('data');
        expect($data['risk_level'])->toBe('high');
    });

    it('shows wallet-related log with correct data', function (): void {
        $log = AdminActionLog::factory()->create([
            'route_name'    => 'admin.wallet.transaction.create',
            'resource_type' => 'App\\Models\\WalletTransaction',
        ]);

        $response = $this->authorized_user([PermissionEnum::AUDIT_ADMIN_ACTIONS_VIEW])
            ->getJson($this->baseUrl.'/'.$log->id);

        $response->assertSuccessful();
        $data = $response->json('data');
        expect($data['route_name'])->toBe('admin.wallet.transaction.create');
        expect($data['resource_type'])->toBe('App\\Models\\WalletTransaction');
    });

    it('shows error response status correctly', function (): void {
        $log = AdminActionLog::factory()->create(['response_status' => 500]);

        $response = $this->authorized_user([PermissionEnum::AUDIT_ADMIN_ACTIONS_VIEW])
            ->getJson($this->baseUrl.'/'.$log->id);

        $response->assertSuccessful();
        $data = $response->json('data');
        expect($data['response_status'])->toBe(500);
    });

    it('handles different HTTP methods', function (): void {
        $methods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];

        foreach ($methods as $method) {
            $log = AdminActionLog::factory()->create(['http_method' => $method]);

            $response = $this->authorized_user([PermissionEnum::AUDIT_ADMIN_ACTIONS_VIEW])
                ->getJson($this->baseUrl.'/'.$log->id);

            $response->assertSuccessful();
            $data = $response->json('data');
            expect($data['http_method'])->toBe($method);
        }
    });

    it('shows complex request data correctly', function (): void {
        $complexRequestData = [
            'user' => [
                'name'        => 'John Doe',
                'email'       => 'john@example.com',
                'preferences' => [
                    'notifications' => true,
                    'theme'         => 'dark',
                ],
            ],
            'metadata' => [
                'source'   => 'admin_panel',
                'batch_id' => 'batch_123',
            ],
        ];

        $log = AdminActionLog::factory()->create([
            'request_data' => $complexRequestData,
        ]);

        $response = $this->authorized_user([PermissionEnum::AUDIT_ADMIN_ACTIONS_VIEW])
            ->getJson($this->baseUrl.'/'.$log->id);

        $response->assertSuccessful();
        $data = $response->json('data');
        expect($data['request_data'])->toEqual($complexRequestData);
    });
});
