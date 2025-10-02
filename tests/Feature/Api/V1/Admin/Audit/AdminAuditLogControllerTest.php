<?php

declare(strict_types=1);

use App\Enums\PermissionEnum;
use App\Models\AdminActionLog;
use App\Models\Staff;
use App\Models\User;
use Tests\AuthTestTrait;

use function Pest\Laravel\getJson;

uses(AuthTestTrait::class);

describe('AdminAuditLogIndexController', function (): void {

    beforeEach(function (): void {
        $this->admin   = Staff::factory()->create();
        $this->baseUrl = '/api/v1/admin/audit/admin-actions';
    });

    it('can list admin audit logs with proper permissions', function (): void {
        AdminActionLog::factory()->count(3)->create();
        $this->authorized_user([PermissionEnum::AUDIT_ADMIN_ACTIONS_VIEW]);
        $response = getJson($this->baseUrl);
        // "message" => "گزارش\u{200C}های حسابرسی مدیریت با موفقیت بارگیری شد"
        //  "data" => array:13 [
        //    "current_page" => 1
        //    "data" => array:3 [
        //      0 => array:11 [
        //        "id" => 1
        //        "admin" => array:8 [
        //          "id" => 2
        //          "name" => "Dr. Adelia Bruen PhD"
        //          "email" => "sipes.eunice@example.org"
        //          "phone" => "09126094148"
        //          "created_at" => "1404-06-14 15:26:55"
        //          "updated_at" => "1404-06-14 15:26:55"
        //          "is_admin" => false
        //          "roles" => null
        //        ]
        //        "action_type" => "create"
        //        "resource_type" => "App\Models\User"
        //        "resource_id" => 122
        //        "http_method" => "PUT"
        //        "response_status" => 200
        //        "ip_address" => "6.223.85.167"
        //        "risk_level" => "low"
        //        "created_at" => "1404-06-14 15:26:55"
        //        "action_summery" => "Create User #122"
        //      ]
        //      1 => array:11 [
        //        "id" => 2
        //        "admin" => array:8 [
        //          "id" => 3
        //          "name" => "Geoffrey Effertz"
        //          "email" => "mjast@example.com"
        //          "phone" => "09338067490"
        //          "created_at" => "1404-06-14 15:26:55"
        //          "updated_at" => "1404-06-14 15:26:55"
        //          "is_admin" => false
        //          "roles" => null
        //        ]
        //        "action_type" => "create"
        //        "resource_type" => "App\Models\Wallet"
        //        "resource_id" => 494
        //        "http_method" => "POST"
        //        "response_status" => 201
        //        "ip_address" => "136.227.83.83"
        //        "risk_level" => "medium"
        //        "created_at" => "1404-06-14 15:26:55"
        //        "action_summery" => "Create Wallet #494"
        //      ]
        //      2 => array:11 [
        //        "id" => 3
        //        "admin" => array:8 [
        //          "id" => 4
        //          "name" => "Prof. Rowena Pagac MD"
        //          "email" => "lawson19@example.net"
        //          "phone" => "09139217564"
        //          "created_at" => "1404-06-14 15:26:55"
        //          "updated_at" => "1404-06-14 15:26:55"
        //          "is_admin" => false
        //          "roles" => null
        //        ]
        //        "action_type" => "create"
        //        "resource_type" => "App\Models\User"
        //        "resource_id" => 835
        //        "http_method" => "DELETE"
        //        "response_status" => 204
        //        "ip_address" => "173.83.249.149"
        //        "risk_level" => "medium"
        //        "created_at" => "1404-06-14 15:26:55"
        //        "action_summery" => "Create User #835"
        //      ]
        //    ]
        //    "first_page_url" => "https://jedu.test/api/v1/admin/audit/admin-actions?page=1"
        //    "from" => 1
        //    "last_page" => 1
        //    "last_page_url" => "https://jedu.test/api/v1/admin/audit/admin-actions?page=1"
        //    "links" => array:3 [
        //      0 => array:4 [
        //        "url" => null
        //        "label" => "&laquo; قبلی"
        //        "page" => null
        //        "active" => false
        //      ]
        //      1 => array:4 [
        //        "url" => "https://jedu.test/api/v1/admin/audit/admin-actions?page=1"
        //        "label" => "1"
        //        "page" => 1
        //        "active" => true
        //      ]
        //      2 => array:4 [
        //        "url" => null
        //        "label" => "بعدی &raquo;"
        //        "page" => null
        //        "active" => false
        //      ]
        //    ]
        //  ]
        //  "metadata" => []
        $response
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'action_type',
                            'route_name',
                            'resource_type',
                            'resource_id',
                            'http_method',
                            'response_status',
                            'ip_address',
                            'risk_level',
                            'created_at',
                            'action_summery',
                            'admin' => [
                                'id',
                                'name',
                            ],
                            'action_type',
                            'resource_type',
                            'resource_id',
                            'http_method',
                            'response_status',
                            'ip_address',
                            'risk_level',
                            'created_at',
                            'action_summery',
                        ],
                    ],
                ],
            ]);
    });

    it('requires permission to view audit logs', function (): void {
        AdminActionLog::factory()->count(3)->create();

        $response = $this->authorized_user([])
            ->getJson($this->baseUrl);

        $response->assertForbidden();
    });

    it('can filter by admin_id', function (): void {
        $targetAdmin = Staff::factory()->create();
        AdminActionLog::factory()->create(['admin_id' => $targetAdmin->id]);
        AdminActionLog::factory()->create(['admin_id' => $this->admin->id]);

        $response = $this->authorized_user([PermissionEnum::AUDIT_ADMIN_ACTIONS_VIEW])
            ->getJson($this->baseUrl."?filter[admin_id]={$targetAdmin->id}");

        $response->assertSuccessful();
        $data = $response->json('data.data');
        expect($data)->toHaveCount(1);
        expect($data[0]['admin']['id'])->toBe($targetAdmin->id);
    });

    it('can filter by action_type', function (): void {
        AdminActionLog::factory()->create(['action_type' => 'create']);
        AdminActionLog::factory()->create(['action_type' => 'update']);
        AdminActionLog::factory()->create(['action_type' => 'delete']);

        $response = $this->authorized_user([PermissionEnum::AUDIT_ADMIN_ACTIONS_VIEW])
            ->getJson($this->baseUrl.'?filter[action_type]=create');

        $response->assertSuccessful();
        $data = $response->json('data.data');
        expect($data)->toHaveCount(1);
        expect($data[0]['action_type'])->toBe('create');
    });

    it('can filter by resource_type', function (): void {
        AdminActionLog::factory()->create(['resource_type' => 'App\\Models\\User']);
        AdminActionLog::factory()->create(['resource_type' => 'App\\Models\\WalletTransaction']);

        $response = $this->authorized_user([PermissionEnum::AUDIT_ADMIN_ACTIONS_VIEW])
            ->getJson($this->baseUrl.'?filter[resource_type]=App\\Models\\User');

        $response->assertSuccessful();
        $data = $response->json('data.data');
        expect($data)->toHaveCount(1);
        expect($data[0]['resource_type'])->toBe('App\\Models\\User');
    });

    it('can filter by risk_level', function (): void {
        AdminActionLog::factory()->create(['risk_level' => 'high']);
        AdminActionLog::factory()->create(['risk_level' => 'medium']);
        AdminActionLog::factory()->create(['risk_level' => 'low']);

        $response = $this->authorized_user([PermissionEnum::AUDIT_ADMIN_ACTIONS_VIEW])
            ->getJson($this->baseUrl.'?filter[risk_level]=high');

        $response->assertSuccessful();
        $data = $response->json('data.data');
        expect($data)->toHaveCount(1);
        expect($data[0]['risk_level'])->toBe('high');
    });

    it('can filter by http_method', function (): void {
        AdminActionLog::factory()->create(['http_method' => 'POST']);
        AdminActionLog::factory()->create(['http_method' => 'GET']);
        AdminActionLog::factory()->create(['http_method' => 'PUT']);

        $response = $this->authorized_user([PermissionEnum::AUDIT_ADMIN_ACTIONS_VIEW])
            ->getJson($this->baseUrl.'?filter[http_method]=POST');

        $response->assertSuccessful();
        $data = $response->json('data.data');
        expect($data)->toHaveCount(1);
        expect($data[0]['http_method'])->toBe('POST');
    });

    it('can filter by response_status', function (): void {
        AdminActionLog::factory()->create(['response_status' => 200]);
        AdminActionLog::factory()->create(['response_status' => 404]);
        AdminActionLog::factory()->create(['response_status' => 500]);

        $response = $this->authorized_user([PermissionEnum::AUDIT_ADMIN_ACTIONS_VIEW])
            ->getJson($this->baseUrl.'?filter[response_status]=404');

        $response->assertSuccessful();
        $data = $response->json('data.data');
        expect($data)->toHaveCount(1);
        expect($data[0]['response_status'])->toBe(404);
    });

    it('can filter by partial route_name', function (): void {
        AdminActionLog::factory()->create(['route_name' => 'admin.users.store']);
        AdminActionLog::factory()->create(['route_name' => 'admin.wallet.transaction.create']);
        AdminActionLog::factory()->create(['route_name' => 'api.v1.products.index']);

        $response = $this->authorized_user([PermissionEnum::AUDIT_ADMIN_ACTIONS_VIEW])
            ->getJson($this->baseUrl.'?filter[route_name]=wallet');

        $response->assertSuccessful();
        $data = $response->json('data.data');
        expect($data)->toHaveCount(1);
        expect($data[0]['route_name'])->toBe('admin.wallet.transaction.create');
    });

    it('can filter by ip_address', function (): void {
        AdminActionLog::factory()->create(['ip_address' => '192.168.1.1']);
        AdminActionLog::factory()->create(['ip_address' => '10.0.0.1']);

        $response = $this->authorized_user([PermissionEnum::AUDIT_ADMIN_ACTIONS_VIEW])
            ->getJson($this->baseUrl.'?filter[ip_address]=192.168.1.1');

        $response->assertSuccessful();
        $data = $response->json('data.data');
        expect($data)->toHaveCount(1);
        expect($data[0]['ip_address'])->toBe('192.168.1.1');
    });

    it('can filter by date_from', function (): void {
        $oldLog    = AdminActionLog::factory()->create(['created_at' => now()->subWeek()]);
        $recentLog = AdminActionLog::factory()->create(['created_at' => now()->subDay()]);

        $filterDate = now()->subDays(3)->format('Y-m-d');

        $response = $this->authorized_user([PermissionEnum::AUDIT_ADMIN_ACTIONS_VIEW])
            ->getJson($this->baseUrl."?filter[date_from]={$filterDate}");

        $response->assertSuccessful();
        $data = $response->json('data.data');
        expect($data)->toHaveCount(1);
        expect($data[0]['id'])->toBe($recentLog->id);
    });

    it('can filter by date_to', function (): void {
        $oldLog    = AdminActionLog::factory()->create(['created_at' => now()->subWeek()]);
        $recentLog = AdminActionLog::factory()->create(['created_at' => now()->subDay()]);

        $filterDate = now()->subDays(3)->format('Y-m-d');

        $response = $this->authorized_user([PermissionEnum::AUDIT_ADMIN_ACTIONS_VIEW])
            ->getJson($this->baseUrl."?filter[date_to]={$filterDate}");

        $response->assertSuccessful();
        $data = $response->json('data.data');
        expect($data)->toHaveCount(1);
        expect($data[0]['id'])->toBe($oldLog->id);
    });

    it('can search across multiple fields', function (): void {
        $targetAdmin = Staff::factory()->create(['name' => 'Admin Test']);
        AdminActionLog::factory()->create([
            'admin_id'   => $targetAdmin->id,
            'route_name' => 'admin.users.store',
        ]);
        AdminActionLog::factory()->create([
            'route_name' => 'admin.wallet.transaction.create',
        ]);

        $response = $this->authorized_user([PermissionEnum::AUDIT_ADMIN_ACTIONS_VIEW])
            ->getJson($this->baseUrl.'?filter[search]=Admin');

        $response->assertSuccessful();
        $data = $response->json('data.data');
        expect($data)->toHaveCount(2);
        $adminIds = array_column($data, 'admin');
        $adminIds = array_column($adminIds, 'id');
        expect($adminIds)->toContain($targetAdmin->id);
        $routeNames = array_column($data, 'route_name');
        expect($routeNames)->toContain('admin.users.store')
            ->and($routeNames)->toContain('admin.wallet.transaction.create');

    });

    it('can search by route name', function (): void {
        AdminActionLog::factory()->create(['route_name' => 'admin.users.store']);
        AdminActionLog::factory()->create(['route_name' => 'admin.wallet.transaction.create']);

        $response = $this->authorized_user([PermissionEnum::AUDIT_ADMIN_ACTIONS_VIEW])
            ->getJson($this->baseUrl.'?filter[search]=wallet');

        $response->assertSuccessful();
        $data = $response->json('data.data');
        expect($data)->toHaveCount(1);
        expect($data[0]['route_name'])->toBe('admin.wallet.transaction.create');
    });

    it('supports pagination', function (): void {
        AdminActionLog::factory()->count(25)->create();

        $response = $this->authorized_user([PermissionEnum::AUDIT_ADMIN_ACTIONS_VIEW])
            ->getJson($this->baseUrl.'?per_page=10');

        $response->assertSuccessful();
        $data = $response->json('data.data');
        expect($data)->toHaveCount(10);
    });

    it('supports sorting', function (): void {
        $oldLog = AdminActionLog::factory()->create(['created_at' => now()->subWeek()]);
        $newLog = AdminActionLog::factory()->create(['created_at' => now()]);

        $response = $this->authorized_user([PermissionEnum::AUDIT_ADMIN_ACTIONS_VIEW])
            ->getJson($this->baseUrl.'?sort=created_at');

        $response->assertSuccessful();
        $data = $response->json('data.data');
        expect($data[0]['id'])->toBe($oldLog->id);
        expect($data[1]['id'])->toBe($newLog->id);
    });

    it('supports descending sorting', function (): void {
        $oldLog = AdminActionLog::factory()->create(['created_at' => now()->subWeek()]);
        $newLog = AdminActionLog::factory()->create(['created_at' => now()]);

        $response = $this->authorized_user([PermissionEnum::AUDIT_ADMIN_ACTIONS_VIEW])
            ->getJson($this->baseUrl.'?sort=-created_at');

        $response->assertSuccessful();
        $data = $response->json('data.data');
        expect($data[0]['id'])->toBe($newLog->id);
        expect($data[1]['id'])->toBe($oldLog->id);
    });

    it('includes admin relationship', function (): void {
        $admin = Staff::factory()->create(['name' => 'Test Admin']);
        AdminActionLog::factory()->create(['admin_id' => $admin->id]);

        $response = $this->authorized_user([PermissionEnum::AUDIT_ADMIN_ACTIONS_VIEW])
            ->getJson($this->baseUrl);

        $response->assertSuccessful();
        $data = $response->json('data.data');
        expect($data[0])->toHaveKey('admin');
        expect($data[0]['admin']['name'])->toBe('Test Admin');
    });

    it('handles empty result set', function (): void {
        // No logs created
        $response = $this->authorized_user([PermissionEnum::AUDIT_ADMIN_ACTIONS_VIEW])
            ->getJson($this->baseUrl);

        $response->assertSuccessful();
        $data = $response->json('data.data');
        expect($data)->toBeArray();
        expect($data)->toHaveCount(0);
    });

    it('combines multiple filters correctly', function (): void {
        $targetAdmin = Staff::factory()->create();
        AdminActionLog::factory()->create([
            'admin_id'    => $targetAdmin->id,
            'action_type' => 'create',
            'risk_level'  => 'high',
        ]);
        AdminActionLog::factory()->create([
            'admin_id'    => $targetAdmin->id,
            'action_type' => 'update',
            'risk_level'  => 'low',
        ]);
        AdminActionLog::factory()->create([
            'action_type' => 'create',
            'risk_level'  => 'high',
        ]); // Different admin

        $response = $this->authorized_user([PermissionEnum::AUDIT_ADMIN_ACTIONS_VIEW])
            ->getJson($this->baseUrl
                ."?filter[admin_id]={$targetAdmin->id}&filter[action_type]=create&filter[risk_level]=high");

        $response->assertSuccessful();
        $data = $response->json('data.data');
        expect($data)->toHaveCount(1);
        expect($data[0]['admin']['id'])->toBe($targetAdmin->id);
        expect($data[0]['action_type'])->toBe('create');
        expect($data[0]['risk_level'])->toBe('high');
    });
});
