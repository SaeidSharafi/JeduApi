<?php

declare(strict_types=1);

use App\Models\AdminActionLog;
use App\Models\Staff;
use App\Models\User;

describe('AdminActionLog Model', function () {

    it('can be created with factory', function () {
        $log = AdminActionLog::factory()->create();

        expect($log)->toBeInstanceOf(AdminActionLog::class);
        expect($log->admin_id)->toBeInt();
        expect($log->action_type)->toBeString();
        expect($log->resource_type)->toBeString();
        expect($log->route_name)->toBeString();
        expect($log->http_method)->toBeString();
        expect($log->risk_level)->toBeString();
        expect($log->response_status)->toBeInt();
    });

    it('has correct fillable attributes', function () {
        $fillable = [
            'admin_id',
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
        ];

        $log = new AdminActionLog();
        expect($log->getFillable())->toBe($fillable);
    });

    it('casts attributes correctly', function () {
        $log = AdminActionLog::factory()->create([
            'request_data' => ['key' => 'value'],
            'metadata' => ['test' => 'data'],
            'response_status' => 200,
        ]);

        expect($log->request_data)->toBeArray();
        expect($log->metadata)->toBeArray();
        expect($log->response_status)->toBeInt();
        expect($log->created_at?->utc())->toBeInstanceOf(Carbon\CarbonImmutable::class);
    });

    it('belongs to admin (staff)', function () {
        $staff = Staff::factory()->create();
        $log = AdminActionLog::factory()->create(['admin_id' => $staff->id]);

        expect($log->admin)->toBeInstanceOf(Staff::class);
        expect($log->admin->id)->toBe($staff->id);
    });

    it('has polymorphic resource relationship', function () {
        $user = User::factory()->create();
        $log = AdminActionLog::factory()->create([
            'resource_type' => User::class,
            'resource_id' => $user->id,
        ]);

        expect($log->resource)->toBeInstanceOf(User::class);
        expect($log->resource->id)->toBe($user->id);
    });

    // Business Logic Tests
    it('identifies high risk correctly', function () {
        $highRiskLog = AdminActionLog::factory()->create(['risk_level' => 'high']);
        $lowRiskLog = AdminActionLog::factory()->create(['risk_level' => 'low']);

        expect($highRiskLog->isHighRisk())->toBeTrue();
        expect($lowRiskLog->isHighRisk())->toBeFalse();
    });

    it('identifies medium risk correctly', function () {
        $mediumRiskLog = AdminActionLog::factory()->create(['risk_level' => 'medium']);
        $lowRiskLog = AdminActionLog::factory()->create(['risk_level' => 'low']);

        expect($mediumRiskLog->isMediumRisk())->toBeTrue();
        expect($lowRiskLog->isMediumRisk())->toBeFalse();
    });

    it('identifies low risk correctly', function () {
        $lowRiskLog = AdminActionLog::factory()->create(['risk_level' => 'low']);
        $highRiskLog = AdminActionLog::factory()->create(['risk_level' => 'high']);

        expect($lowRiskLog->isLowRisk())->toBeTrue();
        expect($highRiskLog->isLowRisk())->toBeFalse();
    });

    it('identifies successful responses correctly', function () {
        $successLog = AdminActionLog::factory()->create(['response_status' => 200]);
        $errorLog = AdminActionLog::factory()->create(['response_status' => 404]);
        $serverErrorLog = AdminActionLog::factory()->create(['response_status' => 500]);

        expect($successLog->isSuccessful())->toBeTrue();
        expect($errorLog->isSuccessful())->toBeFalse();
        expect($serverErrorLog->isSuccessful())->toBeFalse();
    });

    it('identifies wallet related actions correctly', function () {
        $walletRouteLog = AdminActionLog::factory()->create(['route_name' => 'admin.wallet.transaction.create']);
        $walletResourceLog = AdminActionLog::factory()->create(['resource_type' => 'App\Models\WalletTransaction']);
        $nonWalletLog = AdminActionLog::factory()->create([
            'route_name' => 'admin.users.index',
            'resource_type' => 'App\Models\User'
        ]);

        expect($walletRouteLog->isWalletRelated())->toBeTrue();
        expect($walletResourceLog->isWalletRelated())->toBeTrue();
        expect($nonWalletLog->isWalletRelated())->toBeFalse();
    });

    it('generates action summary correctly', function () {
        $log = AdminActionLog::factory()->create([
            'action_type' => 'create',
            'resource_type' => 'App\Models\User',
            'resource_id' => 123,
        ]);

        expect($log->getActionSummary())->toBe('Create User #123');
    });

    it('generates action summary without resource ID', function () {
        $log = AdminActionLog::factory()->create([
            'action_type' => 'view',
            'resource_type' => 'App\Models\User',
            'resource_id' => null,
        ]);

        expect($log->getActionSummary())->toBe('View User');
    });

    // Scopes Tests
    it('filters high risk logs with scope', function () {
        AdminActionLog::factory()->create(['risk_level' => 'high']);
        AdminActionLog::factory()->create(['risk_level' => 'medium']);
        AdminActionLog::factory()->create(['risk_level' => 'low']);

        $highRiskLogs = AdminActionLog::highRisk()->get();

        expect($highRiskLogs)->toHaveCount(1);
        expect($highRiskLogs->first()->risk_level)->toBe('high');
    });

    it('filters wallet actions with scope', function () {
        AdminActionLog::factory()->create(['route_name' => 'admin.wallet.transactions']);
        AdminActionLog::factory()->create(['resource_type' => 'App\Models\WalletTransaction']);
        AdminActionLog::factory()->create(['route_name' => 'admin.users.index',
                                           'resource_type' => 'App\Models\User']);

        $walletLogs = AdminActionLog::walletActions()->get();

        expect($walletLogs)->toHaveCount(2);
    });

    it('filters by admin with scope', function () {
        $admin = Staff::factory()->create();
        AdminActionLog::factory()->create(['admin_id' => $admin->id]);
        AdminActionLog::factory()->create(['admin_id' => $admin->id]);
        AdminActionLog::factory()->create(); // Different admin

        $adminLogs = AdminActionLog::byAdmin($admin->id)->get();

        expect($adminLogs)->toHaveCount(2);
        expect($adminLogs->every(fn($log) => $log->admin_id === $admin->id))->toBeTrue();
    });

    it('filters by date range with scope', function () {
        $startDate = now()->subDays(5);
        $endDate = now()->subDays(2);

        AdminActionLog::factory()->create(['created_at' => now()->subDays(3)]); // Within range
        AdminActionLog::factory()->create(['created_at' => now()->subDays(4)]); // Within range
        AdminActionLog::factory()->create(['created_at' => now()->subDays(7)]); // Before range
        AdminActionLog::factory()->create(['created_at' => now()]); // After range

        $rangeLogs = AdminActionLog::byDateRange($startDate, $endDate)->get();

        expect($rangeLogs)->toHaveCount(2);
    });

    it('filters by risk level with scope', function () {
        AdminActionLog::factory()->create(['risk_level' => 'high']);
        AdminActionLog::factory()->create(['risk_level' => 'high']);
        AdminActionLog::factory()->create(['risk_level' => 'medium']);

        $highRiskLogs = AdminActionLog::byRiskLevel('high')->get();

        expect($highRiskLogs)->toHaveCount(2);
        expect($highRiskLogs->every(fn($log) => $log->risk_level === 'high'))->toBeTrue();
    });

    it('serializes to array correctly', function () {
        $staff = Staff::factory()->create();
        $log = AdminActionLog::factory()->create([
            'admin_id' => $staff->id,
            'action_type' => 'create',
            'resource_type' => 'App\Models\User',
            'resource_id' => 123,
            'route_name' => 'admin.users.store',
            'http_method' => 'POST',
            'request_data' => ['name' => 'Test User'],
            'response_status' => 201,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test Agent',
            'session_id' => 'test-session',
            'risk_level' => 'low',
            'metadata' => ['test' => 'data'],
        ])->fresh();

        $array = $log->toArray();

        expect($array)->toHaveKeys([
            'id', 'admin_id', 'action_type', 'resource_type', 'resource_id',
            'route_name', 'http_method', 'request_data', 'response_status',
            'ip_address', 'user_agent', 'session_id', 'risk_level', 'metadata',
            'created_at', 'updated_at'
        ]);

        expect($array['admin_id'])->toBe($staff->id);
        expect($array['action_type'])->toBe('create');
        expect($array['request_data'])->toBe(['name' => 'Test User']);
        expect($array['metadata'])->toBe(['test' => 'data']);
    });
});
